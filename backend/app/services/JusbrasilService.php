<?php

require_once dirname(__DIR__) . '/config/database.php';

class JusbrasilService
{
    private PDO $pdo;
    private ?string $lastError = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function syncCpfProcesses(int $userId, string $cpf): array
    {
        $cpf = preg_replace('/\D+/', '', $cpf) ?? '';
        if (strlen($cpf) !== 11) {
            return $this->failure('CPF invalido para consulta Jusbrasil.');
        }

        $apiKey = $this->envValue('JUSBRASIL_API_KEY');
        if ($apiKey === '') {
            return $this->failure('Configure JUSBRASIL_API_KEY no backend/.env para consultar processos por CPF.', false);
        }

        $baseUrl = rtrim($this->envValue('JUSBRASIL_API_BASE_URL') ?: 'https://api.jusbrasil.com.br', '/');
        $types = ['criminal', 'civil', 'trabalhista'];
        $imported = 0;
        $errors = [];

        foreach ($types as $type) {
            $cursor = '';
            $page = 0;

            do {
                $page++;
                $response = $this->requestJson(
                    'POST',
                    $baseUrl . '/background-check/lawsuits/' . $type,
                    [
                        'Content-Type: application/json',
                        'apikey: ' . $apiKey,
                    ],
                    [
                        'documentNumber' => $cpf,
                        'pagination' => [
                            'cursor' => $cursor,
                            'size' => 100,
                        ],
                    ]
                );

                if (!$response['ok']) {
                    $errors[] = strtoupper($type) . ': ' . $response['message'];
                    break;
                }

                $data = is_array($response['data']) ? $response['data'] : [];
                foreach ((array) ($data['processos'] ?? []) as $process) {
                    if (is_array($process) && $this->saveProcess($userId, 'cliente', 'cpf', $cpf, $process)) {
                        $imported++;
                    }
                }

                $pagination = is_array($data['pagination'] ?? null) ? $data['pagination'] : [];
                $hasNextPage = (bool) ($pagination['hasNextPage'] ?? false);
                $cursor = (string) ($pagination['endCursor'] ?? '');
            } while ($hasNextPage && $cursor !== '' && $page < $this->maxPages());
        }

        if ($errors && $imported === 0) {
            return $this->failure('Falha ao consultar Jusbrasil: ' . implode(' | ', $errors));
        }

        return [
            'success' => true,
            'configured' => true,
            'imported' => $imported,
            'message' => $errors
                ? 'Consulta parcial concluida. Erros: ' . implode(' | ', $errors)
                : 'Processos sincronizados pelo CPF.',
        ];
    }

    public function syncOabProcesses(
        int $userId,
        string $name,
        string $oabNumber,
        string $oabUf,
        ?string $correlationId = null,
        string $ownerType = 'advogado'
    ): array {
        $token = $this->envValue('JUSBRASIL_OAB_TOKEN');
        if ($token === '') {
            return $this->failure('Configure JUSBRASIL_OAB_TOKEN no backend/.env para monitorar processos por OAB.', false);
        }

        $number = preg_replace('/\D+/', '', $oabNumber) ?? '';
        $region = strtoupper(trim($oabUf));
        if ($number === '' || $region === '') {
            return $this->failure('OAB e UF sao obrigatorias para consulta Jusbrasil.');
        }

        $baseUrl = rtrim($this->envValue('JUSBRASIL_OAB_BASE_URL') ?: 'https://op.digesto.com.br/api', '/');
        $oabId = null;

        if (!$correlationId) {
            $response = $this->requestJson(
                'POST',
                $baseUrl . '/monitoramento/oab/acompanhamento/',
                [
                    'accept: application/json',
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json',
                ],
                [[
                    'name' => $name,
                    'number' => (int) $number,
                    'region' => $region,
                    'is_active' => true,
                ]]
            );

            if (!$response['ok']) {
                return $this->failure('Falha ao cadastrar OAB no Jusbrasil: ' . $response['message']);
            }

            $first = is_array($response['data'][0] ?? null) ? $response['data'][0] : [];
            $correlationId = (string) ($first['correlation_id'] ?? '');
            $oabId = isset($first['id']) ? (int) $first['id'] : null;
        }

        if (!$correlationId && !$oabId) {
            return $this->failure('Jusbrasil nao retornou identificador da OAB monitorada.');
        }

        $imported = 0;
        $page = 1;
        do {
            $query = $correlationId
                ? ['correlation_id' => $correlationId, 'per_page' => 500, 'page' => $page]
                : ['oab_id' => $oabId, 'per_page' => 500, 'page' => $page];

            $response = $this->requestJson(
                'GET',
                $baseUrl . '/monitoramento/oab/vinculos/processos/oab?' . http_build_query($query),
                [
                    'accept: application/json',
                    'Authorization: Bearer ' . $token,
                ]
            );

            if (!$response['ok']) {
                return $this->failure('OAB cadastrada, mas falhou ao listar processos: ' . $response['message']);
            }

            $rows = is_array($response['data']) ? $response['data'] : [];
            foreach ($rows as $row) {
                if (is_array($row) && $this->saveProcess($userId, $ownerType === 'estagiario' ? 'estagiario' : 'advogado', 'oab', $region . $number, $row)) {
                    $imported++;
                }
            }

            $page++;
        } while (count($rows) === 500 && $page <= $this->maxPages());

        return [
            'success' => true,
            'configured' => true,
            'imported' => $imported,
            'correlation_id' => $correlationId,
            'oab_id' => $oabId,
            'message' => $imported > 0
                ? 'Processos vinculados a OAB sincronizados.'
                : 'OAB enviada para monitoramento. A coleta pode ser assincrona e aparecer depois.',
        ];
    }

    private function saveProcess(int $userId, string $ownerType, string $queryType, string $queryValue, array $process): bool
    {
        $number = $this->processNumber($process);
        if ($number === '') {
            return false;
        }

        $status = is_array($process['status'] ?? null) ? $process['status'] : [];
        $payload = json_encode($process, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $stmt = $this->pdo->prepare(
            'INSERT INTO external_processes
                (user_id, owner_type, source, query_type, query_value, process_number, tribunal, uf, comarca,
                 tipo_processo, classe_processual, assunto, status_inferido, status_normalizado, link,
                 data_ultima_atualizacao, data_andamento_mais_recente, payload_json, last_synced_at)
             VALUES
                (:user_id, :owner_type, "jusbrasil", :query_type, :query_value, :process_number, :tribunal, :uf, :comarca,
                 :tipo_processo, :classe_processual, :assunto, :status_inferido, :status_normalizado, :link,
                 :data_ultima_atualizacao, :data_andamento_mais_recente, :payload_json, NOW())
             ON DUPLICATE KEY UPDATE
                tribunal = VALUES(tribunal),
                uf = VALUES(uf),
                comarca = VALUES(comarca),
                tipo_processo = VALUES(tipo_processo),
                classe_processual = VALUES(classe_processual),
                assunto = VALUES(assunto),
                status_inferido = VALUES(status_inferido),
                status_normalizado = VALUES(status_normalizado),
                link = VALUES(link),
                data_ultima_atualizacao = VALUES(data_ultima_atualizacao),
                data_andamento_mais_recente = VALUES(data_andamento_mais_recente),
                payload_json = VALUES(payload_json),
                last_synced_at = NOW()'
        );

        $stmt->execute([
            ':user_id' => $userId,
            ':owner_type' => $ownerType,
            ':query_type' => $queryType,
            ':query_value' => $queryValue,
            ':process_number' => $number,
            ':tribunal' => $process['tribunal'] ?? null,
            ':uf' => $process['UF'] ?? ($process['uf'] ?? null),
            ':comarca' => $process['comarca'] ?? null,
            ':tipo_processo' => $process['tipo_processo'] ?? null,
            ':classe_processual' => $process['classe_processual'] ?? null,
            ':assunto' => $process['assunto'] ?? null,
            ':status_inferido' => $status['inferido'] ?? null,
            ':status_normalizado' => $status['normalizado'] ?? null,
            ':link' => $process['link'] ?? null,
            ':data_ultima_atualizacao' => $this->dateOrNull($process['data_ultima_atualizacao'] ?? null),
            ':data_andamento_mais_recente' => $this->dateOrNull($process['data_andamento_mais_recente'] ?? null),
            ':payload_json' => $payload ?: null,
        ]);

        return true;
    }

    private function processNumber(array $process): string
    {
        return trim((string) ($process['numero_processo'] ?? $process['cnj'] ?? ''));
    }

    private function dateOrNull($value): ?string
    {
        $value = trim((string) $value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function requestJson(string $method, string $url, array $headers = [], $body = null): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'message' => 'Extensao cURL do PHP nao esta habilitada.', 'data' => null];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 35,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'message' => $error ?: 'Falha HTTP sem detalhes.', 'data' => null, 'status' => $status];
        }

        $data = json_decode((string) $raw, true);
        if ($status < 200 || $status >= 300) {
            $message = is_array($data)
                ? (string) ($data['message'] ?? $data['detail'] ?? $data['error'] ?? ('HTTP ' . $status))
                : ('HTTP ' . $status);

            return ['ok' => false, 'message' => $message, 'data' => $data, 'status' => $status];
        }

        return ['ok' => true, 'message' => 'ok', 'data' => is_array($data) ? $data : [], 'status' => $status];
    }

    private function maxPages(): int
    {
        $value = (int) ($this->envValue('JUSBRASIL_MAX_PAGES') ?: 3);
        return max(1, min(10, $value));
    }

    private function failure(string $message, bool $configured = true): array
    {
        $this->lastError = $message;

        return [
            'success' => false,
            'configured' => $configured,
            'imported' => 0,
            'message' => $message,
        ];
    }

    private function envValue(string $key): string
    {
        $value = getenv($key);
        if ($value !== false) {
            return trim((string) $value);
        }

        $env = database_env_values(dirname(__DIR__, 2) . '/.env');
        return trim((string) ($env[$key] ?? ''));
    }
}
