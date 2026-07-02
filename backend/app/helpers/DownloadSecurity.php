<?php

namespace App\Helpers;

class DownloadSecurity
{
    private const INLINE_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public static function disposition(string $path, string $mime, bool $forceAttachment): string
    {
        if ($forceAttachment) {
            return 'attachment';
        }

        $mime = strtolower(trim(explode(';', $mime, 2)[0]));
        if (in_array($mime, self::INLINE_MIMES, true)) {
            return 'inline';
        }

        if ($mime === 'image/svg+xml' && self::isSafeSvg($path)) {
            return 'inline';
        }

        return 'attachment';
    }

    public static function safeFilename(string $filename, string $fallback): string
    {
        $filename = trim(preg_replace('/[^\w.\- ]+/u', '_', $filename) ?? '');
        return $filename !== '' ? $filename : $fallback;
    }

    private static function isSafeSvg(string $path): bool
    {
        if (!is_readable($path) || filesize($path) > 1024 * 1024) {
            return false;
        }

        $contents = (string) file_get_contents($path);
        if ($contents === '') {
            return false;
        }

        if (preg_match('/<\s*script\b|on[a-z]+\s*=|javascript\s*:|data\s*:/i', $contents) === 1) {
            return false;
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($contents);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $xml !== false && strtolower($xml->getName()) === 'svg';
    }
}
