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

            $timeout = max(1, (int) ($this->envValue('CLAMAV_TIMEOUT_SECONDS') ?: 15));
            $result = ProcessRunnerService::run([$binary, '--no-summary', $path], $timeout);

            if ((int) $result['exit_code'] === 0) {
                return true;
            }

            if (!empty($result['timed_out'])) {
                $this->lastError = 'Scanner antimalware excedeu o tempo limite.';
                return false;
            }

            $details = trim((string) $result['stdout'] . ' ' . (string) $result['stderr']);
            $this->lastError = 'Scanner antimalware reprovou o arquivo' . ($details !== '' ? ': ' . mb_substr($details, 0, 180) : '.');
            return false;
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
