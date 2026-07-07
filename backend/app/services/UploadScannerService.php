<?php

namespace App\Services {
    use App\Services\ProcessRunnerService;
    use RuntimeException;
    use Throwable;

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
            if ($this->hasUnsafeDoubleExtension($originalName)) {
                return false;
            }

            if (!$this->matchesExpectedMagicBytes($sample, $mime)) {
                return false;
            }

            if ($this->matchesUnsafeSignature($sample, $originalName, $mime)) {
                return false;
            }

            return $this->scanWithClamAv($path);
        }

        private function hasUnsafeDoubleExtension(string $originalName): bool
        {
            $parts = array_values(array_filter(explode('.', strtolower($originalName)), static fn ($part) => $part !== ''));
            if (count($parts) < 3) {
                return false;
            }

            array_pop($parts);
            $unsafe = ['php', 'phtml', 'phar', 'cgi', 'exe', 'bat', 'cmd', 'sh', 'js', 'html', 'htm', 'svg'];
            foreach ($parts as $part) {
                if (in_array($part, $unsafe, true)) {
                    $this->lastError = 'Arquivo bloqueado por dupla extensão perigosa.';
                    return true;
                }
            }

            return false;
        }

        private function matchesExpectedMagicBytes(string $sample, string $mime): bool
        {
            if ($sample === '') {
                $this->lastError = 'Arquivo vazio ou ilegível.';
                return false;
            }

            $ok = match (strtolower($mime)) {
                'application/pdf' => str_starts_with($sample, '%PDF-'),
                'image/png' => str_starts_with($sample, "\x89PNG\r\n\x1A\n"),
                'image/jpeg', 'image/jpg' => str_starts_with($sample, "\xFF\xD8\xFF"),
                'image/webp' => str_starts_with($sample, 'RIFF') && substr($sample, 8, 4) === 'WEBP',
                'application/zip',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => str_starts_with($sample, "PK\x03\x04") || str_starts_with($sample, "PK\x05\x06") || str_starts_with($sample, "PK\x07\x08"),
                'application/msword' => str_starts_with($sample, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"),
                'text/plain' => !str_contains(substr($sample, 0, 4096), "\0"),
                default => true,
            };

            if (!$ok) {
                $this->lastError = 'Assinatura real do arquivo não corresponde ao tipo declarado.';
            }

            return $ok;
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

            if (str_contains($binary, DIRECTORY_SEPARATOR) && !is_executable($binary)) {
                return true;
            }

            $timeout = max(1, (int) ($this->envValue('CLAMAV_TIMEOUT_SECONDS') ?: 15));
            $command = escapeshellcmd($binary) . ' --no-summary --infected ' . escapeshellarg($path) . ' 2>&1';
            $result = ProcessRunnerService::run(['/bin/sh', '-lc', $command], $timeout);
            $scanOutput = trim((string) $result['stdout'] . PHP_EOL . (string) $result['stderr']);
            $exitCode = (int) $result['exit_code'];

            if ($exitCode === 0) {
                return true;
            }

            if (!empty($result['timed_out'])) {
                $this->lastError = 'Scanner antimalware excedeu o tempo limite.';
                return false;
            }

            if ($this->isClamAvUnavailable($exitCode, $scanOutput)) {
                return true;
            }

            if ($exitCode >= 2) {
                $details = $scanOutput !== '' ? mb_substr($scanOutput, 0, 180) : 'erro desconhecido';
                $this->lastError = 'Scanner antimalware falhou durante a verificação: ' . $details;
                return false;
            }

            if ($exitCode === 1 || stripos($scanOutput, 'FOUND') !== false) {
                $this->lastError = 'Scanner antimalware reprovou o arquivo: ameaça detectada.';
                return false;
            }

            $details = $scanOutput !== '' ? mb_substr($scanOutput, 0, 180) : 'erro desconhecido';
            $this->lastError = 'Scanner antimalware falhou durante a verificação: ' . $details;
            return false;
        }

        private function isClamAvUnavailable(int $exitCode, string $scanOutput): bool
        {
            if ($exitCode !== 126 && $exitCode !== 127) {
                return false;
            }

            $normalized = strtolower($scanOutput);
            if ($normalized === '') {
                return true;
            }

            return str_contains($normalized, 'not found')
                || str_contains($normalized, 'no such file or directory')
                || str_contains($normalized, 'command not found')
                || str_contains($normalized, 'cannot execute')
                || str_contains($normalized, 'unable to start process');
        }

        public static function convertToWebp(string $tmpPath, string $mime): ?string
        {
            if (!function_exists('imagecreatetruecolor') || !function_exists('imagewebp')) {
                return null;
            }

            $image = match ($mime) {
                'image/jpeg', 'image/jpg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($tmpPath) : false,
                'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($tmpPath) : false,
                'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmpPath) : false,
                default => false,
            };

            if (!$image) {
                return null;
            }

            $webpPath = $tmpPath . '.webp';
            if (@imagewebp($image, $webpPath, 80)) {
                imagedestroy($image);
                @unlink($tmpPath);
                return $webpPath;
            }

            imagedestroy($image);
            return null;
        }

        public static function stripImageMetadata(string $filePath, string $mime): void
        {
            if (!function_exists('imagecreatetruecolor')) {
                return;
            }

            $image = match ($mime) {
                'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($filePath) : false,
                'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($filePath) : false,
                'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($filePath) : false,
                default => false,
            };

            if (!$image) {
                return;
            }

            $tempPath = $filePath . '.tmp';
            $saved = match ($mime) {
                'image/jpeg' => imagejpeg($image, $tempPath, 88),
                'image/png' => imagepng($image, $tempPath, 6),
                'image/webp' => function_exists('imagewebp') ? imagewebp($image, $tempPath, 86) : false,
                default => false,
            };
            imagedestroy($image);

            if ($saved && is_file($tempPath)) {
                @unlink($filePath);
                rename($tempPath, $filePath);
            } else {
                @unlink($tempPath);
            }
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
}

namespace {
    if (!class_exists('UploadScannerService')) {
        class_alias('App\Services\UploadScannerService', 'UploadScannerService');
    }
}
