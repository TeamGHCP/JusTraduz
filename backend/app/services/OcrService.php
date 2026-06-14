<?php

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
            $this->lastError = 'OCR nao configurado.';
            return '';
        }

        $binary = $this->envValue('OCR_TESSERACT_BINARY') ?: 'tesseract';
        $language = $this->envValue('OCR_LANGUAGE') ?: 'por+eng';
        $tmpBase = tempnam(sys_get_temp_dir(), 'justraduz_ocr_');
        if ($tmpBase === false) {
            $this->lastError = 'Nao foi possivel criar arquivo temporario para OCR.';
            return '';
        }

        @unlink($tmpBase);
        $command = escapeshellarg($binary) . ' ' . escapeshellarg($path) . ' ' . escapeshellarg($tmpBase) . ' -l ' . escapeshellarg($language);
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        $textPath = $tmpBase . '.txt';
        if ($exitCode !== 0 || !is_file($textPath)) {
            $this->lastError = 'Falha ao executar OCR: ' . trim(implode(' ', $output));
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
            return 'OCR nao conseguiu extrair texto legivel deste arquivo. A qualidade da imagem ou PDF pode estar baixa.';
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
