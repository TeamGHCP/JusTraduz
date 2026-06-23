<?php

require_once __DIR__ . '/ProcessRunnerService.php';

class OcrService
{
    private ?string $lastError = null;

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function extract(string $path, string $mime): string
    {
        $this->lastError = null;

        if (!$this->enabled()) {
            $this->lastError = 'OCR não configurado.';
            return '';
        }

        $binary = $this->envValue('OCR_TESSERACT_BINARY') ?: 'tesseract';
        $language = $this->envValue('OCR_LANGUAGE') ?: 'por+eng';
        $tmpBase = tempnam(sys_get_temp_dir(), 'justraduz_ocr_');
        if ($tmpBase === false) {
            $this->lastError = 'Não foi possível criar arquivo temporário para OCR.';
            return '';
        }

        @unlink($tmpBase);
        $timeout = max(1, (int) ($this->envValue('OCR_TIMEOUT_SECONDS') ?: 30));
        $result = ProcessRunnerService::run([$binary, $path, $tmpBase, '-l', $language], $timeout);

        $textPath = $tmpBase . '.txt';
        if ((int) $result['exit_code'] !== 0 || !is_file($textPath)) {
            $details = trim((string) $result['stdout'] . ' ' . (string) $result['stderr']);
            $this->lastError = !empty($result['timed_out'])
                ? 'OCR excedeu o tempo limite.'
                : 'Falha ao executar OCR' . ($details !== '' ? ': ' . mb_substr($details, 0, 180) : '.');
            @unlink($textPath);
            return '';
        }

        $text = trim((string) file_get_contents($textPath));
        @unlink($textPath);

        return $text;
    }

    public function fallbackMessage(string $mime): string
    {
        if ($this->enabled()) {
            return 'OCR não conseguiu extrair texto legível deste arquivo. A qualidade da imagem ou PDF pode estar baixa.';
        }

        return 'Este arquivo pode estar escaneado como imagem. Configure OCR_TESSERACT_BINARY e OCR_ENABLED=true para extrair texto automaticamente.';
    }

    private function enabled(): bool
    {
        return filter_var($this->envValue('OCR_ENABLED'), FILTER_VALIDATE_BOOLEAN);
    }

    private function envValue(string $key): string
    {
        $value = getenv($key);
        if ($value !== false) {
            return trim((string) $value);
        }

        $env = function_exists('database_env_values') ? database_env_values(dirname(__DIR__, 2) . '/.env') : [];
        return trim((string) ($env[$key] ?? ''));
    }
}
