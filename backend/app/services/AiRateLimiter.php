<?php

require_once dirname(__DIR__) . '/support/session.php';

class AiRateLimiter
{
    private const SESSION_LIMIT = 10;
    private const SESSION_WINDOW_SECONDS = 300;
    private const IP_LIMIT = 40;
    private const IP_WINDOW_SECONDS = 3600;

    public function consume(): array
    {
        secure_session_start();

        $now = time();
        $sessionResult = $this->consumeSession($now);
        if (!$sessionResult['allowed']) {
            return $sessionResult;
        }

        return $this->consumeIp($now);
    }

    private function consumeSession(int $now): array
    {
        $attempts = array_values(array_filter(
            is_array($_SESSION['_ai_chat_attempts'] ?? null) ? $_SESSION['_ai_chat_attempts'] : [],
            static fn ($timestamp): bool => is_int($timestamp) && $timestamp > ($now - self::SESSION_WINDOW_SECONDS)
        ));

        if (count($attempts) >= self::SESSION_LIMIT) {
            $retryAfter = max(1, self::SESSION_WINDOW_SECONDS - ($now - $attempts[0]));
            return ['allowed' => false, 'retry_after' => $retryAfter];
        }

        $attempts[] = $now;
        $_SESSION['_ai_chat_attempts'] = $attempts;
        return ['allowed' => true, 'retry_after' => 0];
    }

    private function consumeIp(int $now): array
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $key = hash('sha256', $ip);
        $directory = dirname(__DIR__, 2) . '/storage/rate-limits';

        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            return ['allowed' => true, 'retry_after' => 0];
        }

        $path = $directory . '/ai-' . $key . '.json';
        $handle = @fopen($path, 'c+');
        if ($handle === false) {
            return ['allowed' => true, 'retry_after' => 0];
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return ['allowed' => true, 'retry_after' => 0];
            }

            $raw = stream_get_contents($handle);
            $decoded = json_decode(is_string($raw) ? $raw : '', true);
            $attempts = is_array($decoded) ? $decoded : [];
            $attempts = array_values(array_filter(
                $attempts,
                static fn ($timestamp): bool => is_int($timestamp) && $timestamp > ($now - self::IP_WINDOW_SECONDS)
            ));

            if (count($attempts) >= self::IP_LIMIT) {
                $retryAfter = max(1, self::IP_WINDOW_SECONDS - ($now - $attempts[0]));
                return ['allowed' => false, 'retry_after' => $retryAfter];
            }

            $attempts[] = $now;
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($attempts));
            fflush($handle);
            return ['allowed' => true, 'retry_after' => 0];
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
