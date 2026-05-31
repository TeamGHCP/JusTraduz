<?php

class GeminiService
{
    private const MAX_INLINE_BYTES = 19 * 1024 * 1024;
    private const SUPPORTED_FILE_MIMES = [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'image/webp',
    ];

    private string $apiKey;
    private string $model;
    private ?string $lastError = null;

    public function __construct(?string $apiKey = null, ?string $model = null)
    {
        $this->apiKey = $apiKey ?: self::readConfigValue('GEMINI_API_KEY');
        $this->model = $model ?: self::readConfigValue('GEMINI_MODEL', 'gemini-2.5-flash');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function analyzeDocument(string $text): ?array
    {
        $text = trim($text);
        if (!$this->isConfigured() || $text === '') {
            $this->lastError = !$this->isConfigured()
                ? 'A chave GEMINI_API_KEY não está configurada.'
                : 'Não há texto para analisar.';
            return null;
        }

        return $this->requestAnalysis([
            ['text' => self::buildPrompt($text, false)],
        ]);
    }

    public function analyzeDocumentFile(string $filePath, string $mimeType, ?string $extractedText = null): ?array
    {
        $extractedText = trim((string) $extractedText);

        if (!$this->isConfigured()) {
            $this->lastError = 'A chave GEMINI_API_KEY não está configurada.';
            return null;
        }

        if (!is_readable($filePath)) {
            return $extractedText !== '' ? $this->analyzeDocument($extractedText) : null;
        }

        if (!in_array($mimeType, self::SUPPORTED_FILE_MIMES, true)) {
            return $extractedText !== '' ? $this->analyzeDocument($extractedText) : null;
        }

        $fileSize = (int) filesize($filePath);
        if ($fileSize <= 0) {
            $this->lastError = 'O arquivo está vazio.';
            return $extractedText !== '' ? $this->analyzeDocument($extractedText) : null;
        }

        if ($fileSize > self::MAX_INLINE_BYTES) {
            if ($extractedText !== '') {
                return $this->analyzeDocument($extractedText);
            }

            $this->lastError = 'O arquivo é grande demais para análise direta. Envie um PDF com texto selecionável ou menor que 19 MB.';
            return null;
        }

        $contents = file_get_contents($filePath);
        if ($contents === false) {
            $this->lastError = 'Não foi possível ler o arquivo salvo.';
            return $extractedText !== '' ? $this->analyzeDocument($extractedText) : null;
        }

        return $this->requestAnalysis([
            ['text' => self::buildPrompt($extractedText, true)],
            [
                'inline_data' => [
                    'mime_type' => $mimeType,
                    'data' => base64_encode($contents),
                ],
            ],
        ]);
    }

    public static function isSupportedFileMime(string $mimeType): bool
    {
        return in_array($mimeType, self::SUPPORTED_FILE_MIMES, true);
    }

    private function requestAnalysis(array $parts): ?array
    {
        $response = $this->generateContent($parts);
        if ($response === null) {
            return null;
        }

        $json = self::extractJson($response);
        $data = json_decode($json, true);

        if (!is_array($data)) {
            return [
                'resumo' => $response,
                'explicacao' => 'Não foi possível separar automaticamente a explicação em linguagem simples. Revise o resumo gerado.',
                'confianca' => 60,
            ];
        }

        $analysis = [
            'resumo' => trim((string) ($data['resumo'] ?? '')),
            'explicacao' => trim((string) ($data['explicacao'] ?? '')),
            'confianca' => max(0, min(100, (float) ($data['confianca'] ?? 70))),
        ];

        if ($analysis['resumo'] === '' && $analysis['explicacao'] === '') {
            $this->lastError = 'A resposta da Gemini veio sem resumo ou explicação.';
            return null;
        }

        return $analysis;
    }

    private function generateContent(array $parts): ?string
    {
        if (!function_exists('curl_init')) {
            $this->lastError = 'A extensão curl do PHP não está habilitada.';
            return null;
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($this->model) . ':generateContent';
        $payload = json_encode([
            'contents' => [
                [
                    'parts' => $parts,
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'responseMimeType' => 'application/json',
            ],
        ], JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            $this->lastError = 'Não foi possível montar a requisição JSON para a Gemini.';
            return null;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 45,
        ]);

        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            $this->lastError = 'Erro de conexão com a Gemini: ' . ($curlError ?: 'sem detalhes.');
            return null;
        }

        $data = json_decode((string) $raw, true);

        if ($httpCode < 200 || $httpCode >= 300) {
            $message = is_array($data) ? (string) ($data['error']['message'] ?? '') : '';
            $this->lastError = 'Gemini retornou HTTP ' . $httpCode . ($message !== '' ? ': ' . $message : '.');
            return null;
        }

        $text = trim((string) ($data['candidates'][0]['content']['parts'][0]['text'] ?? ''));
        if ($text === '') {
            $reason = (string) ($data['promptFeedback']['blockReason'] ?? $data['candidates'][0]['finishReason'] ?? '');
            $this->lastError = 'A Gemini não retornou conteúdo' . ($reason !== '' ? ' (' . $reason . ')' : '') . '.';
            return null;
        }

        $this->lastError = null;
        return $text;
    }

    private static function readConfigValue(string $key, string $default = ''): string
    {
        $aliases = self::configAliases($key);

        foreach ($aliases as $alias) {
            $env = getenv($alias);
            if ($env !== false && trim($env) !== '') {
                return trim($env);
            }
        }

        $envConfig = self::readEnvFile(dirname(__DIR__, 2) . '/.env');
        foreach ($aliases as $alias) {
            if (!empty($envConfig[$alias])) {
                return trim((string) $envConfig[$alias]);
            }
        }

        $configPath = dirname(__DIR__) . '/config/gemini.php';
        if (is_file($configPath)) {
            $config = require $configPath;
            if (is_array($config)) {
                foreach ($aliases as $alias) {
                    if (!empty($config[$alias])) {
                        return trim((string) $config[$alias]);
                    }
                }
            }
        }

        return $default;
    }

    private static function buildPrompt(string $text, bool $hasFile): string
    {
        $prompt = "Você é um assistente jurídico brasileiro. Analise o documento jurídico e responda somente em JSON válido, sem markdown, com as chaves resumo, explicacao e confianca.\n\n"
            . "Regras:\n"
            . "- resumo: resumo objetivo em português do Brasil, com os pontos principais.\n"
            . "- explicacao: reescreva em linguagem simples para uma pessoa sem conhecimento jurídico.\n"
            . "- confianca: número de 0 a 100 indicando segurança da análise conforme a qualidade do documento.\n"
            . "- Não invente dados que não estejam no documento.\n"
            . "- Se algum trecho estiver ilegível, diga isso sem completar informações por conta própria.\n\n";

        if ($hasFile) {
            $prompt .= "Use o arquivo anexado como fonte principal.\n\n";
        }

        if ($text !== '') {
            $prompt .= "Texto extraído do documento, quando disponível:\n" . mb_substr($text, 0, 28000);
        }

        return $prompt;
    }

    private static function configAliases(string $key): array
    {
        return match ($key) {
            'GEMINI_API_KEY' => ['GEMINI_API_KEY', 'api_key', 'key'],
            'GEMINI_MODEL' => ['GEMINI_MODEL', 'model'],
            default => [$key],
        };
    }

    private static function readEnvFile(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $values = [];
        foreach ((array) file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $value = trim($value);
            $values[trim($key)] = trim($value, "\"'");
        }

        return $values;
    }

    private static function extractJson(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/^```json\s*|\s*```$/', '', $text);

        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start !== false && $end !== false && $end > $start) {
            return substr($text, $start, $end - $start + 1);
        }

        return $text;
    }
}
