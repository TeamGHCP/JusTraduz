<?php

class OabService
{
    private const BASE_URL = 'https://consulta.oab.org.br/cna-interno/api';

    public function lookup(
        string $registration,
        string $uf = '',
        string $accountType = '',
        string $name = '',
        string $recaptchaToken = '',
        string $recaptchaVersion = 'v3'
    ): array {
        $registration = preg_replace('/\D+/', '', $registration) ?? '';
        $uf = strtoupper(trim($uf));
        $accountType = strtolower(trim($accountType));

        if ($registration === '') {
            return $this->invalid('Informe o número da OAB.');
        }

        $search = $this->search($registration, $uf, $accountType, $recaptchaToken, $recaptchaVersion);
        if (!$search['ok']) {
            return $search;
        }

        $items = $this->normalizeItems($search['items'] ?? []);
        if (!$items) {
            return $this->invalid('Nenhum registro foi encontrado no CNA para a inscrição informada.');
        }

        $candidate = $this->selectBestMatch($items, $registration, $uf, $accountType);
        if (!$candidate) {
            return $this->invalid('Registro encontrado no CNA, mas UF ou tipo de inscrição não conferem.');
        }

        if (!empty($candidate['parametro']) && $recaptchaToken !== '') {
            $detail = $this->detail((string) $candidate['parametro'], $recaptchaToken, $recaptchaVersion);
            if ($detail) {
                $candidate = array_merge($candidate, $detail);
            }
        }

        $typeLabel = $this->normalizeType((string) ($candidate['tipoInscOab'] ?? $candidate['tipoInscricao'] ?? ''));
        $situation = strtoupper((string) ($candidate['situacao'] ?? ''));
        $expectedTypes = $this->expectedTypes($accountType);

        if ($expectedTypes && !in_array($typeLabel, $expectedTypes, true)) {
            return $this->invalid('O tipo de inscrição do CNA não corresponde ao perfil escolhido.');
        }

        if ($situation !== '' && $situation !== 'REGULAR') {
            return $this->invalid('A inscrição foi encontrada, mas não está regular no CNA.');
        }

        return [
            'ok' => true,
            'verified' => true,
            'source_available' => true,
            'message' => 'Inscrição validada no CNA.',
            'data' => [
                'nome' => $candidate['nome'] ?? null,
                'inscricao' => $candidate['inscricao'] ?? $registration,
                'uf' => strtoupper((string) ($candidate['uf'] ?? $uf)),
                'tipo' => $typeLabel,
                'situacao' => $situation ?: 'REGULAR',
                'parametro' => $candidate['parametro'] ?? null,
                'nome_conferido' => $name === '' || $this->namesLookCompatible($name, (string) ($candidate['nome'] ?? '')),
            ],
        ];
    }

    private function search(string $registration, string $uf, string $accountType, string $recaptchaToken, string $recaptchaVersion): array
    {
        $params = ['Inscricao' => $registration];

        if ($uf !== '') {
            $params['Uf'] = $uf;
        }

        $typeCode = $this->typeCode($accountType);
        if ($typeCode !== null) {
            $params['TipoInscricao'] = $typeCode;
        }

        $response = $this->request('/advogado/search?' . http_build_query($params), $recaptchaToken, $recaptchaVersion, 'lawyer_search');

        if (($response['status'] ?? 0) === 400 && str_contains(strtolower((string) $response['body']), 'recaptcha')) {
            return [
                'ok' => false,
                'verified' => false,
                'source_available' => false,
                'message' => 'O CNA exige reCAPTCHA para esta consulta. O cadastro ficará pendente se continuar sem validação automática.',
            ];
        }

        if (($response['status'] ?? 0) < 200 || ($response['status'] ?? 0) >= 300) {
            return [
                'ok' => false,
                'verified' => false,
                'source_available' => false,
                'message' => 'Não foi possível consultar o CNA agora.',
            ];
        }

        $payload = json_decode((string) $response['body'], true);
        if (!is_array($payload)) {
            return [
                'ok' => false,
                'verified' => false,
                'source_available' => false,
                'message' => 'O CNA retornou uma resposta inesperada.',
            ];
        }

        $data = $payload['data'] ?? $payload;
        return [
            'ok' => true,
            'verified' => false,
            'source_available' => true,
            'items' => $data['items'] ?? $data['Items'] ?? [],
        ];
    }

    private function detail(string $parameter, string $recaptchaToken, string $recaptchaVersion): ?array
    {
        $response = $this->request('/advogado/detail?' . http_build_query(['parametro' => $parameter]), $recaptchaToken, $recaptchaVersion, 'lawyer_detail');

        if (($response['status'] ?? 0) < 200 || ($response['status'] ?? 0) >= 300) {
            return null;
        }

        $payload = json_decode((string) $response['body'], true);
        if (!is_array($payload)) {
            return null;
        }

        $data = $payload['data'] ?? $payload;
        return is_array($data) ? $data : null;
    }

    private function request(string $path, string $recaptchaToken, string $recaptchaVersion, string $action): array
    {
        $url = self::BASE_URL . $path;
        $headers = [
            'Accept: application/json',
            'User-Agent: JusTraduz/1.0',
        ];

        if ($recaptchaToken !== '') {
            if ($recaptchaVersion === 'v2') {
                $headers[] = 'X-Recaptcha-V2-Token: ' . $recaptchaToken;
            } else {
                $headers[] = 'X-Recaptcha-Token: ' . $recaptchaToken;
                $headers[] = 'X-Recaptcha-Action: ' . $action;
            }
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_HTTPHEADER => $headers,
            ]);

            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            return ['status' => $status, 'body' => $body === false ? $error : $body];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);
        $body = file_get_contents($url, false, $context);
        $status = 0;

        foreach (($http_response_header ?? []) as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
                $status = (int) $matches[1];
                break;
            }
        }

        return ['status' => $status, 'body' => $body ?: ''];
    }

    private function normalizeItems(array $items): array
    {
        return array_values(array_filter($items, static fn ($item): bool => is_array($item)));
    }

    private function selectBestMatch(array $items, string $registration, string $uf, string $accountType): ?array
    {
        foreach ($items as $item) {
            $itemRegistration = preg_replace('/\D+/', '', (string) ($item['inscricao'] ?? '')) ?? '';
            $itemUf = strtoupper((string) ($item['uf'] ?? ''));
            $itemType = $this->normalizeType((string) ($item['tipoInscOab'] ?? $item['tipoInscricao'] ?? ''));
            $expectedTypes = $this->expectedTypes($accountType);

            if ($itemRegistration !== $registration) {
                continue;
            }

            if ($uf !== '' && $itemUf !== $uf) {
                continue;
            }

            if ($expectedTypes && !in_array($itemType, $expectedTypes, true)) {
                continue;
            }

            return $item;
        }

        if (count($items) !== 1) {
            return null;
        }

        $item = $items[0];
        $itemRegistration = preg_replace('/\D+/', '', (string) ($item['inscricao'] ?? '')) ?? '';
        $itemUf = strtoupper((string) ($item['uf'] ?? ''));
        $itemType = $this->normalizeType((string) ($item['tipoInscOab'] ?? $item['tipoInscricao'] ?? ''));
        $expectedTypes = $this->expectedTypes($accountType);

        if ($itemRegistration !== $registration) {
            return null;
        }

        if ($uf !== '' && $itemUf !== $uf) {
            return null;
        }

        if ($expectedTypes && !in_array($itemType, $expectedTypes, true)) {
            return null;
        }

        return $item;
    }

    private function typeCode(string $accountType): ?string
    {
        return match ($accountType) {
            'estagiario' => '2',
            'suplementar' => '3',
            default => null,
        };
    }

    private function expectedTypes(string $accountType): array
    {
        return match ($accountType) {
            'advogado' => ['advogado', 'suplementar'],
            'estagiario' => ['estagiario'],
            'suplementar' => ['suplementar'],
            default => [],
        };
    }

    private function normalizeType(string $type): string
    {
        $type = $this->normalizeText($type);

        if (str_contains($type, 'estagi')) {
            return 'estagiario';
        }

        if (str_contains($type, 'suplement')) {
            return 'suplementar';
        }

        if (str_contains($type, 'advog')) {
            return 'advogado';
        }

        return $type;
    }

    private function namesLookCompatible(string $submitted, string $official): bool
    {
        $submitted = $this->normalizeText($submitted);
        $official = $this->normalizeText($official);

        if ($submitted === '' || $official === '') {
            return true;
        }

        return $submitted === $official || str_contains($official, $submitted) || str_contains($submitted, $official);
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        return preg_replace('/[^a-z0-9]+/', ' ', $converted ?: $value) ?? '';
    }

    private function invalid(string $message): array
    {
        return [
            'ok' => false,
            'verified' => false,
            'source_available' => true,
            'message' => $message,
        ];
    }
}
