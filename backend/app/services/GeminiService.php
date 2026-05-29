<?php

class GeminiService
{
    private string $apiKey;
    private string $model;

    public function __construct(?string $apiKey = null, ?string $model = null)
    {
        $this->apiKey = $apiKey ?: self::readConfigValue('AQ.Ab8RN6JXMJw6gZz_4RG_OGcNRGE8COnxNxb9ueXTb89qnPEwew');
        $this->model = $model ?: self::readConfigValue('GEMINI_MODEL', 'gemini-2.5-flash');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function analyzeDocument(string $text): ?array
    {
        $text = trim($text);
        if (!$this->isConfigured() || $text === '') {
            return null;
        }

        $prompt = "Você é um assistente jurídico brasileiro. Analise o texto extraído de um documento jurídico e responda somente em JSON válido, sem markdown, com as chaves resumo, explicacao e confianca.\n\n"
            . "Regras:\n"
            . "- resumo: resumo objetivo em português do Brasil, com os pontos principais.\n"
            . "- explicacao: reescreva em linguagem simples para uma pessoa sem conhecimento jurídico.\n"
            . "- confianca: número de 0 a 100 indicando segurança da análise conforme a qualidade do texto extraído.\n"
            . "- Não invente dados que não estejam no texto.\n\n"
            . "Texto extraído:\n" . mb_substr($text, 0, 28000);

        $response = $this->generateContent($prompt);
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

        return [
            'resumo' => trim((string) ($data['resumo'] ?? '')),
            'explicacao' => trim((string) ($data['explicacao'] ?? '')),
            'confianca' => max(0, min(100, (float) ($data['confianca'] ?? 70))),
        ];
    }

    private function generateContent(string $prompt): ?string
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($this->model) . ':generateContent';
        $payload = json_encode([
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'responseMimeType' => 'application/json',
            ],
        ]);

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
        curl_close($ch);

        if ($raw === false || $httpCode < 200 || $httpCode >= 300) {
            return null;
        }

        $data = json_decode((string) $raw, true);
        return trim((string) ($data['candidates'][0]['content']['parts'][0]['text'] ?? ''));
    }

    private static function readConfigValue(string $key, string $default = ''): string
    {
        $env = getenv($key);
        if ($env !== false && trim($env) !== '') {
            return trim($env);
        }

        $configPath = dirname(__DIR__) . '/config/gemini.php';
        if (is_file($configPath)) {
            $config = require $configPath;
            if (is_array($config) && !empty($config[$key])) {
                return trim((string) $config[$key]);
            }
        }

        return $default;
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
