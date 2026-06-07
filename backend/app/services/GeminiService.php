<?php

class GeminiService
{
    private const MAX_INLINE_BYTES = 19 * 1024 * 1024;
    private const PROMPT_VERSION = '2026-06-06-document-v2';
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

    public function modelName(): string
    {
        return $this->model;
    }

    public static function promptVersion(): string
    {
        return self::PROMPT_VERSION;
    }

    public function analyzeDocument(string $text): ?array
    {
        $text = trim($text);
        if (!$this->isConfigured() || $text === '') {
            $this->lastError = !$this->isConfigured()
                ? 'A chave GEMINI_API_KEY nao esta configurada.'
                : 'Nao ha texto para analisar.';
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
            $this->lastError = 'A chave GEMINI_API_KEY nao esta configurada.';
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
            $this->lastError = 'O arquivo esta vazio.';
            return $extractedText !== '' ? $this->analyzeDocument($extractedText) : null;
        }

        if ($fileSize > self::MAX_INLINE_BYTES) {
            if ($extractedText !== '') {
                return $this->analyzeDocument($extractedText);
            }

            $this->lastError = 'O arquivo e grande demais para analise direta. Envie um PDF com texto selecionavel ou menor que 19 MB.';
            return null;
        }

        $contents = file_get_contents($filePath);
        if ($contents === false) {
            $this->lastError = 'Nao foi possivel ler o arquivo salvo.';
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

        return $this->normalizeAnalysisResponse($response, $data);
    }

    private function normalizeAnalysisResponse(string $response, mixed $data): ?array
    {
        if (!is_array($data)) {
            return [
                'resumo' => self::plainText($response),
                'explicacao' => self::composeStructuredExplanation(
                    'A IA retornou uma resposta em texto livre. Leia com cuidado e confirme os pontos importantes com um profissional.',
                    [],
                    ['A resposta nao veio no formato estruturado esperado.'],
                    ['Revise o documento original antes de tomar qualquer decisao.'],
                    'Esta analise e informativa e nao substitui orientacao juridica profissional.'
                ),
                'confianca' => 60,
            ];
        }

        $simpleExplanation = self::plainText((string) ($data['explicacao_simples'] ?? $data['explicacao'] ?? ''));
        $importantPoints = self::listFromMixed($data['pontos_importantes'] ?? []);
        $risks = self::listFromMixed($data['riscos'] ?? $data['pontos_de_atencao'] ?? []);
        $nextSteps = self::listFromMixed($data['proximos_passos'] ?? []);
        $notice = self::plainText((string) ($data['aviso_informativo'] ?? 'Esta analise e informativa e nao substitui orientacao juridica profissional.'));

        $analysis = [
            'resumo' => self::plainText((string) ($data['resumo'] ?? '')),
            'explicacao' => self::composeStructuredExplanation($simpleExplanation, $importantPoints, $risks, $nextSteps, $notice),
            'confianca' => max(0, min(100, (float) ($data['confianca'] ?? 70))),
        ];

        if ($analysis['resumo'] === '' && $analysis['explicacao'] === '') {
            $this->lastError = 'A resposta da Gemini veio sem resumo ou explicacao.';
            return null;
        }

        return $analysis;
    }

    private function generateContent(array $parts): ?string
    {
        if (!function_exists('curl_init')) {
            $this->lastError = 'A extensao curl do PHP nao esta habilitada.';
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
            $this->lastError = 'Nao foi possivel montar a requisicao JSON para a Gemini.';
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
            $this->lastError = 'Erro de conexao com a Gemini: ' . ($curlError ?: 'sem detalhes.');
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
            $this->lastError = 'A Gemini nao retornou conteudo' . ($reason !== '' ? ' (' . $reason . ')' : '') . '.';
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
        $prompt = "Voce e um assistente juridico brasileiro focado em explicar documentos para pessoas leigas. Analise o documento e responda somente em JSON valido, sem markdown.\n\n"
            . "Formato obrigatorio:\n"
            . "{\n"
            . "  \"resumo\": \"resumo objetivo em ate 3 frases\",\n"
            . "  \"explicacao_simples\": \"explicacao clara, sem juridiques, sobre o que o documento quer dizer\",\n"
            . "  \"pontos_importantes\": [\"ponto 1\", \"ponto 2\", \"ponto 3\"],\n"
            . "  \"riscos\": [\"risco ou ponto de atencao 1\", \"risco ou ponto de atencao 2\"],\n"
            . "  \"proximos_passos\": [\"acao segura 1\", \"acao segura 2\", \"acao segura 3\"],\n"
            . "  \"aviso_informativo\": \"aviso curto dizendo que isso nao substitui orientacao juridica profissional\",\n"
            . "  \"confianca\": 0\n"
            . "}\n\n"
            . "Regras:\n"
            . "- Use portugues do Brasil, frases curtas e termos simples.\n"
            . "- Nao entregue parecer juridico definitivo, estrategia processual ou promessa de resultado.\n"
            . "- Nao invente dados, prazos, valores, partes ou fundamentos que nao estejam no documento.\n"
            . "- Se algum trecho estiver ilegivel, diga isso em riscos e reduza a confianca.\n"
            . "- Se o arquivo nao parecer juridico, diga isso claramente e coloque confianca baixa.\n"
            . "- proximos_passos devem ser acoes prudentes: guardar comprovantes, conferir prazos, procurar profissional, separar documentos.\n"
            . "- confianca deve ser numero de 0 a 100 conforme legibilidade e completude do documento.\n\n";

        if ($hasFile) {
            $prompt .= "Use o arquivo anexado como fonte principal.\n\n";
        }

        if ($text !== '') {
            $prompt .= "Texto extraido do documento, quando disponivel:\n" . mb_substr($text, 0, 28000);
        }

        return $prompt;
    }

    private static function composeStructuredExplanation(
        string $simpleExplanation,
        array $importantPoints,
        array $risks,
        array $nextSteps,
        string $notice
    ): string {
        $sections = [];

        if ($simpleExplanation !== '') {
            $sections[] = "## Explicacao em linguagem simples\n" . $simpleExplanation;
        }

        if ($importantPoints) {
            $sections[] = "## Pontos importantes\n" . self::bulletList($importantPoints);
        }

        if ($risks) {
            $sections[] = "## Riscos e pontos de atencao\n" . self::bulletList($risks);
        }

        if ($nextSteps) {
            $sections[] = "## Proximos passos sugeridos\n" . self::bulletList($nextSteps);
        }

        if ($notice !== '') {
            $sections[] = "## Aviso informativo\n" . $notice;
        }

        return trim(implode("\n\n", $sections));
    }

    private static function listFromMixed(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/\r?\n|;/', $value) ?: [];
        }

        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $item = implode(' ', array_map(static fn (mixed $part): string => is_scalar($part) ? (string) $part : '', $item));
            }

            $text = self::plainText(is_scalar($item) ? (string) $item : '');
            $text = preg_replace('/^[-*]\s*/', '', $text);
            if ($text !== '') {
                $items[] = $text;
            }
        }

        return array_values(array_slice(array_unique($items), 0, 6));
    }

    private static function bulletList(array $items): string
    {
        return implode("\n", array_map(static fn (string $item): string => '- ' . $item, $items));
    }

    private static function plainText(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\R{3,}/', "\n\n", (string) $text);
        return trim((string) $text);
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
