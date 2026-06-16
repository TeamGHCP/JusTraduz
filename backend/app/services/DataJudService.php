<?php

require_once dirname(__DIR__) . '/config/database.php';

class DataJudService
{
    private const DEFAULT_BASE_URL = 'https://api-publica.datajud.cnj.jus.br';

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

    public function syncProcessByCnj(int $userId, string $cpf, string $processNumber, bool $lgpdConsent): array
    {
        $cpf = preg_replace('/\D+/', '', $cpf) ?? '';
        if (!$this->isValidCpf($cpf)) {
            return $this->failure('Cadastre um CPF valido no perfil antes de consultar processos.');
        }

        if (!$lgpdConsent) {
            return $this->failure('Aceite o termo LGPD para consultar o processo informado.');
        }

        $cnj = $this->normalizeCnj($processNumber);
        if (!$this->isValidCnj($cnj)) {
            return $this->failure('Informe um numero de processo CNJ valido.');
        }

        $cached = $this->cachedProcess($userId, $cnj);
        if ($cached) {
            return [
                'success' => true,
                'configured' => true,
                'imported' => 0,
                'cached' => true,
                'message' => 'Processo encontrado no cache auditavel por CNJ.',
            ];
        }

        $tribunal = $this->tribunalFromCnj($cnj) ?: '';

        $search = $this->findProcess($cnj, $tribunal);
        if (!$search['ok']) {
            return $this->failure('Falha ao consultar DataJud/CNJ: ' . $search['message'], (bool) ($search['configured'] ?? true));
        }

        $hit = is_array($search['hit'] ?? null) ? $search['hit'] : [];
        if (!$hit) {
            return $this->failure('O DataJud nao encontrou dados publicos para este numero de processo nos tribunais consultados.');
        }

        $process = $this->normalizeProcess($hit, $cnj, (string) $search['tribunal'], $cpf, $lgpdConsent);
        if (!$this->saveProcess($userId, $process)) {
            return $this->failure('O DataJud retornou dados, mas nao foi possivel salvar o cache do processo.');
        }

        return [
            'success' => true,
            'configured' => true,
            'imported' => 1,
            'message' => 'Processo consultado no DataJud e salvo em cache.',
        ];
    }

    private function findProcess(string $cnj, string $preferredTribunal): array
    {
        $errors = [];
        $tribunals = $this->candidateTribunalsForCnj($cnj, $preferredTribunal);

        foreach ($tribunals as $tribunal) {
            $response = $this->queryTribunal($cnj, $tribunal);
            if (!$response['ok']) {
                if ((int) ($response['status'] ?? 0) === 401) {
                    return [
                        'ok' => false,
                        'configured' => false,
                        'message' => $response['message'],
                    ];
                }

                $errors[] = strtoupper($tribunal) . ': ' . $response['message'];
                continue;
            }

            $hit = $this->firstHit($response['data']);
            if ($hit) {
                return [
                    'ok' => true,
                    'tribunal' => $tribunal,
                    'hit' => $hit,
                ];
            }
        }

        return [
            'ok' => true,
            'tribunal' => $preferredTribunal,
            'hit' => [],
            'errors' => $errors,
        ];
    }

    private function candidateTribunalsForCnj(string $cnj, string $preferredTribunal): array
    {
        if ($preferredTribunal !== '') {
            return [$preferredTribunal];
        }

        $branch = substr($cnj, 13, 1);
        return match ($branch) {
            '4' => array_map(static fn (int $region): string => 'trf' . $region, range(1, 6)),
            '5' => array_merge(['tst'], array_map(static fn (int $region): string => 'trt' . $region, range(1, 24))),
            '6' => array_merge(['tse'], array_map(static fn (string $uf): string => 'tre-' . strtolower($uf === 'DF' ? 'dft' : $uf), array_values($this->stateUfAliases()))),
            '8' => array_values($this->stateCourtAliases()),
            '9' => ['tjmmg', 'tjmrs', 'tjmsp'],
            '1' => ['stf'],
            '2' => ['cnj'],
            '3' => ['stj'],
            '7' => ['stm'],
            default => [],
        };
    }

    private function queryTribunal(string $cnj, string $tribunal): array
    {
        return $this->requestJson(
            'POST',
            rtrim($this->envValue('DATAJUD_API_BASE_URL') ?: self::DEFAULT_BASE_URL, '/') . '/api_publica_' . strtolower($tribunal) . '/_search',
            $this->headers(),
            [
                'size' => 1,
                'query' => [
                    'match' => [
                        'numeroProcesso' => $cnj,
                    ],
                ],
                'sort' => [
                    ['@timestamp' => ['order' => 'desc']],
                ],
            ]
        );
    }

    private function normalizeProcess(array $source, string $cnj, string $tribunal, string $cpf, bool $lgpdConsent): array
    {
        $movements = $this->movements($source);
        $lastMovement = $movements[0] ?? [];
        $lastMovementText = trim((string) ($lastMovement['descricao'] ?? ''));
        $lastMovementDate = $this->dateOrNull($lastMovement['dataHora'] ?? null);
        $updatedAt = $this->dateOrNull($source['@timestamp'] ?? $source['dataHoraUltimaAtualizacao'] ?? null);
        $simpleSummary = $this->simpleSummary($source, $movements);

        $payload = $source;
        $payload['justraduz'] = [
            'consulta' => 'datajud_cnj',
            'cpf_cliente_hash' => hash('sha256', $cpf),
            'lgpd_consentimento' => $lgpdConsent,
            'ultimas_movimentacoes' => array_slice($movements, 0, 5),
            'resumo_linguagem_simples' => $simpleSummary,
        ];

        return [
            'query_value' => $cnj,
            'process_number' => $this->formatCnj($cnj),
            'tribunal' => $tribunal,
            'uf' => $this->ufFromTribunal($tribunal),
            'comarca' => $this->orgaoJulgador($source),
            'tipo_processo' => $source['grau'] ?? null,
            'classe_processual' => $this->namedValue($source['classe'] ?? null),
            'assunto' => $this->subject($source),
            'status_inferido' => $lastMovementText !== '' ? mb_substr($lastMovementText, 0, 120) : 'Consultado no DataJud',
            'status_normalizado' => $this->statusFromMovement($lastMovementText),
            'link' => null,
            'data_ultima_atualizacao' => $updatedAt,
            'data_andamento_mais_recente' => $lastMovementDate ?: $updatedAt,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    private function saveProcess(int $userId, array $process): bool
    {
        $this->ensureCnjQueryType();

        $stmt = $this->pdo->prepare(
            'INSERT INTO external_processes
                (user_id, owner_type, source, query_type, query_value, process_number, tribunal, uf, comarca,
                 tipo_processo, classe_processual, assunto, status_inferido, status_normalizado, link,
                 data_ultima_atualizacao, data_andamento_mais_recente, payload_json, last_synced_at)
             VALUES
                (:user_id, "cliente", "datajud", "cnj", :query_value, :process_number, :tribunal, :uf, :comarca,
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
            ':query_value' => $process['query_value'],
            ':process_number' => $process['process_number'],
            ':tribunal' => $process['tribunal'],
            ':uf' => $process['uf'],
            ':comarca' => $process['comarca'],
            ':tipo_processo' => $process['tipo_processo'],
            ':classe_processual' => $process['classe_processual'],
            ':assunto' => $process['assunto'],
            ':status_inferido' => $process['status_inferido'],
            ':status_normalizado' => $process['status_normalizado'],
            ':link' => $process['link'],
            ':data_ultima_atualizacao' => $process['data_ultima_atualizacao'],
            ':data_andamento_mais_recente' => $process['data_andamento_mais_recente'],
            ':payload_json' => $process['payload_json'] ?: null,
        ]);

        return true;
    }

    private function cachedProcess(int $userId, string $cnj): ?array
    {
        $ttlHours = (int) ($this->envValue('DATAJUD_CACHE_TTL_HOURS') ?: 24);
        if ($ttlHours <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM external_processes
             WHERE user_id = ?
               AND source = "datajud"
               AND query_type = "cnj"
               AND query_value = ?
               AND last_synced_at >= ?
             LIMIT 1'
        );
        $stmt->execute([$userId, $cnj, date('Y-m-d H:i:s', time() - ($ttlHours * 3600))]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function ensureCnjQueryType(): void
    {
        try {
            $this->pdo->exec("ALTER TABLE external_processes MODIFY query_type ENUM('cpf', 'oab', 'cnj') NOT NULL");
        } catch (Throwable $exception) {
            $this->lastError = $exception->getMessage();
        }
    }

    private function firstHit(array $data): array
    {
        $hits = $data['hits']['hits'] ?? [];
        $first = is_array($hits[0] ?? null) ? $hits[0] : [];
        $source = is_array($first['_source'] ?? null) ? $first['_source'] : [];
        return $source;
    }

    private function headers(): array
    {
        $headers = ['Content-Type: application/json'];
        $apiKey = $this->envValue('DATAJUD_API_KEY');
        if ($apiKey !== '') {
            $headers[] = 'Authorization: APIKey ' . $apiKey;
        }

        return $headers;
    }

    private function movements(array $source): array
    {
        $items = is_array($source['movimentos'] ?? null) ? $source['movimentos'] : [];
        usort($items, static fn (array $a, array $b): int => strcmp((string) ($b['dataHora'] ?? ''), (string) ($a['dataHora'] ?? '')));

        return array_map(function (array $movement): array {
            $name = $this->namedValue($movement['movimentoNacional'] ?? null);
            $complements = [];
            foreach ((array) ($movement['complementosTabelados'] ?? []) as $complement) {
                $value = $this->namedValue($complement);
                if ($value !== '') {
                    $complements[] = $value;
                }
            }

            return [
                'dataHora' => $movement['dataHora'] ?? null,
                'descricao' => trim($name . ($complements ? ' - ' . implode('; ', $complements) : '')),
            ];
        }, $items);
    }

    private function simpleSummary(array $source, array $movements): string
    {
        $last = trim((string) ($movements[0]['descricao'] ?? ''));
        if ($last === '') {
            return 'O processo foi encontrado no DataJud, mas nao ha movimentacoes recentes suficientes para explicar o andamento com seguranca.';
        }

        $class = $this->namedValue($source['classe'] ?? null);
        $prefix = $class !== '' ? 'Este processo aparece como ' . $class . '. ' : '';

        if (preg_match('/juntad|documento|peti[cç][aã]o/i', $last)) {
            return $prefix . 'A ultima movimentacao indica que um documento foi juntado ao processo. Isso significa que alguma parte enviou uma nova informacao ou prova. Agora, o juiz ou a vara deve analisar esse documento antes do proximo andamento.';
        }

        if (preg_match('/conclus|juiz|magistrad/i', $last)) {
            return $prefix . 'O processo esta aguardando analise do juiz. A ultima movimentacao indica que os autos foram encaminhados para decisao, despacho ou verificacao interna.';
        }

        if (preg_match('/intima|citac|cita[cç][aã]o|publica/i', $last)) {
            return $prefix . 'A ultima movimentacao indica uma comunicacao oficial no processo. Uma parte ou interessado pode precisar tomar conhecimento e, dependendo do caso, cumprir algum prazo.';
        }

        return $prefix . 'A ultima movimentacao registrada foi: ' . $last . '. Em termos simples, houve um novo andamento no processo e a proxima etapa depende da analise da vara, do juiz ou da manifestacao das partes.';
    }

    private function tribunalFromCnj(string $cnj): ?string
    {
        $branch = substr($cnj, 13, 1);
        $court = substr($cnj, 14, 2);

        if ($branch === '8') {
            return $this->stateCourtAliases()[$court] ?? null;
        }

        if ($branch === '4') {
            if ($court === '00') {
                return null;
            }

            return 'trf' . ((int) $court);
        }

        if ($branch === '5') {
            if ($court === '00') {
                return 'tst';
            }

            return 'trt' . ((int) $court);
        }

        if ($branch === '6') {
            if ($court === '00') {
                return 'tse';
            }

            $uf = $this->ufFromTribunal($this->stateCourtAliases()[$court] ?? '');

            return $uf ? 'tre-' . strtolower($uf) : null;
        }

        if ($branch === '7') {
            return 'stm';
        }

        if ($branch === '9') {
            return [
                '13' => 'tjmmg',
                '21' => 'tjmrs',
                '26' => 'tjmsp',
            ][$court] ?? null;
        }

        return [
            '1' => 'stf',
            '2' => 'cnj',
            '3' => 'stj',
        ][$branch] ?? null;
    }

    private function allTribunalAliases(): array
    {
        return array_merge(
            ['stj', 'tst', 'tse', 'stm'],
            array_map(static fn (int $region): string => 'trf' . $region, range(1, 6)),
            array_values($this->stateCourtAliases()),
            array_map(static fn (int $region): string => 'trt' . $region, range(1, 24)),
            array_map(static fn (string $uf): string => 'tre-' . strtolower($uf === 'DF' ? 'dft' : $uf), array_values($this->stateUfAliases())),
            ['tjmmg', 'tjmrs', 'tjmsp']
        );
    }

    private function stateCourtAliases(): array
    {
        return [
            '01' => 'tjac', '02' => 'tjal', '03' => 'tjam', '04' => 'tjap', '05' => 'tjba',
            '06' => 'tjce', '07' => 'tjdft', '08' => 'tjes', '09' => 'tjgo', '10' => 'tjma',
            '11' => 'tjmt', '12' => 'tjms', '13' => 'tjmg', '14' => 'tjpa', '15' => 'tjpb',
            '16' => 'tjpr', '17' => 'tjpe', '18' => 'tjpi', '19' => 'tjrj', '20' => 'tjrn',
            '21' => 'tjrs', '22' => 'tjro', '23' => 'tjrr', '24' => 'tjsc', '25' => 'tjse',
            '26' => 'tjsp', '27' => 'tjto',
        ];
    }

    private function stateUfAliases(): array
    {
        return [
            '01' => 'AC', '02' => 'AL', '03' => 'AM', '04' => 'AP', '05' => 'BA',
            '06' => 'CE', '07' => 'DF', '08' => 'ES', '09' => 'GO', '10' => 'MA',
            '11' => 'MT', '12' => 'MS', '13' => 'MG', '14' => 'PA', '15' => 'PB',
            '16' => 'PR', '17' => 'PE', '18' => 'PI', '19' => 'RJ', '20' => 'RN',
            '21' => 'RS', '22' => 'RO', '23' => 'RR', '24' => 'SC', '25' => 'SE',
            '26' => 'SP', '27' => 'TO',
        ];
    }

    private function ufFromTribunal(string $tribunal): ?string
    {
        return [
            'tjac' => 'AC', 'tjal' => 'AL', 'tjam' => 'AM', 'tjap' => 'AP', 'tjba' => 'BA',
            'tjce' => 'CE', 'tjdft' => 'DF', 'tjes' => 'ES', 'tjgo' => 'GO', 'tjma' => 'MA',
            'tjmt' => 'MT', 'tjms' => 'MS', 'tjmg' => 'MG', 'tjpa' => 'PA', 'tjpb' => 'PB',
            'tjpr' => 'PR', 'tjpe' => 'PE', 'tjpi' => 'PI', 'tjrj' => 'RJ', 'tjrn' => 'RN',
            'tjrs' => 'RS', 'tjro' => 'RO', 'tjrr' => 'RR', 'tjsc' => 'SC', 'tjse' => 'SE',
            'tjsp' => 'SP', 'tjto' => 'TO',
        ][strtolower($tribunal)] ?? null;
    }

    private function orgaoJulgador(array $source): ?string
    {
        $orgao = $source['orgaoJulgador'] ?? null;
        if (is_array($orgao)) {
            return $this->namedValue($orgao) ?: null;
        }

        return is_scalar($orgao) ? trim((string) $orgao) ?: null : null;
    }

    private function subject(array $source): ?string
    {
        $subjects = [];
        foreach ((array) ($source['assuntos'] ?? []) as $subject) {
            $value = $this->namedValue($subject);
            if ($value !== '') {
                $subjects[] = $value;
            }
        }

        return $subjects ? mb_substr(implode('; ', $subjects), 0, 255) : null;
    }

    private function namedValue($value): string
    {
        if (is_array($value)) {
            return $this->cleanText($value['nome'] ?? $value['descricao'] ?? $value['codigo'] ?? '');
        }

        return $this->cleanText($value);
    }

    private function cleanText($value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return trim(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function statusFromMovement(string $movement): string
    {
        if ($movement === '') {
            return 'em andamento';
        }

        if (preg_match('/arquivad|baixad|transitad|definitiv|extint/i', $movement)) {
            return 'encerrado';
        }

        return 'em andamento';
    }

    private function normalizeCnj(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function isValidCnj(string $cnj): bool
    {
        return strlen($cnj) === 20;
    }

    private function formatCnj(string $cnj): string
    {
        if (!$this->isValidCnj($cnj)) {
            return $cnj;
        }

        return substr($cnj, 0, 7) . '-' . substr($cnj, 7, 2) . '.' . substr($cnj, 9, 4) . '.' . substr($cnj, 13, 1) . '.' . substr($cnj, 14, 2) . '.' . substr($cnj, 16, 4);
    }

    private function dateOrNull($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        return $timestamp ? date('Y-m-d', $timestamp) : null;
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
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout(),
            CURLOPT_TIMEOUT => $this->requestTimeout(),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => $this->sslVerify(),
            CURLOPT_SSL_VERIFYHOST => $this->sslVerify() ? 2 : 0,
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
                ? $this->errorMessage($data, $status)
                : ('HTTP ' . $status);

            return ['ok' => false, 'message' => $message, 'data' => $data, 'status' => $status];
        }

        return ['ok' => true, 'message' => 'ok', 'data' => is_array($data) ? $data : [], 'status' => $status];
    }

    private function errorMessage(array $data, int $status): string
    {
        foreach (['message', 'detail', 'error_description'] as $key) {
            if (!empty($data[$key]) && is_scalar($data[$key])) {
                return (string) $data[$key];
            }
        }

        $error = $data['error'] ?? null;
        if (is_scalar($error)) {
            return (string) $error;
        }

        if (is_array($error)) {
            foreach (['reason', 'message', 'type'] as $key) {
                if (!empty($error[$key]) && is_scalar($error[$key])) {
                    return (string) $error[$key];
                }
            }
        }

        return 'HTTP ' . $status;
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

    private function isValidCpf(?string $cpf): bool
    {
        $digits = preg_replace('/\D+/', '', (string) $cpf) ?? '';

        if (strlen($digits) !== 11 || preg_match('/^(\d)\1{10}$/', $digits)) {
            return false;
        }

        for ($position = 9; $position <= 10; $position++) {
            $sum = 0;
            for ($index = 0; $index < $position; $index++) {
                $sum += (int) $digits[$index] * (($position + 1) - $index);
            }

            $checkDigit = ($sum * 10) % 11;
            if ($checkDigit === 10) {
                $checkDigit = 0;
            }

            if ($checkDigit !== (int) $digits[$position]) {
                return false;
            }
        }

        return true;
    }

    private function sslVerify(): bool
    {
        $value = strtolower($this->envValue('DATAJUD_SSL_VERIFY'));
        return !in_array($value, ['0', 'false', 'no', 'off'], true);
    }

    private function connectTimeout(): int
    {
        $value = (int) $this->envValue('DATAJUD_CONNECT_TIMEOUT');
        return $value > 0 ? min($value, 15) : 5;
    }

    private function requestTimeout(): int
    {
        $value = (int) $this->envValue('DATAJUD_TIMEOUT');
        return $value > 0 ? min($value, 60) : 12;
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
