<?php

class UploadScannerService
{
    private ?string $lastError = null;

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function scan(string $path, string $originalName, string $mime): bool
    {
        $this->lastError = null;

        if (!is_readable($path)) {
            $this->lastError = 'Arquivo temporário indisponível para varredura.';
            return false;
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            $this->lastError = 'Não foi possível ler o arquivo para varredura.';
            return false;
        }
        $sample = (string) fread($handle, 1024 * 1024);
        fclose($handle);
        if ($this->matchesUnsafeSignature($sample, $originalName, $mime)) {
            return false;
        }

        return $this->scanWithClamAv($path);
    }

    private function matchesUnsafeSignature(string $sample, string $originalName, string $mime): bool
    {
        if (str_contains($sample, 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE')) {
            $this->lastError = 'Arquivo bloqueado pelo scanner antimalware.';
            return true;
        }

        if (preg_match('/<\?(php|=)|<script\b|eval\s*\(|base64_decode\s*\(/i', $sample) === 1) {
            $this->lastError = 'Arquivo contém conteúdo executável ou script.';
            return true;
        }

        if (preg_match('/\.(php|phtml|phar|cgi|exe|bat|cmd|sh|js)$/i', $originalName) === 1) {
            $this->lastError = 'Extensão de arquivo não permitida.';
            return true;
        }

        if (str_starts_with($mime, 'text/') && preg_match('/\.(pdf|png|jpe?g|webp|docx?)$/i', $originalName) !== 1) {
            $this->lastError = 'Tipo de arquivo inseguro para upload.';
            return true;
        }

        return false;
    }

    private function scanWithClamAv(string $path): bool
    {
        $binary = $this->envValue('CLAMAV_BINARY');
        if ($binary === '') {
            return true;
        }

        $command = escapeshellarg($binary) . ' --no-summary ' . escapeshellarg($path);
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode === 0) {
            return true;
        }

        $this->lastError = 'Scanner antimalware reprovou o arquivo: ' . trim(implode(' ', $output));
        return false;
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
