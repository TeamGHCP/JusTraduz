<?php

require_once __DIR__ . '/ProcessRunnerService.php';

class PdfTextExtractor
{
    public static function extract(string $filePath): string
    {
        $externalText = self::extractWithPdftotext($filePath);
        if ($externalText !== '') {
            return $externalText;
        }

        $contents = @file_get_contents($filePath);
        if ($contents === false || $contents === '') {
            return '';
        }

        $texts = [];
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $contents, $matches)) {
            foreach ($matches[1] as $stream) {
                $stream = ltrim($stream);
                $decoded = self::decodeStream($stream);

                $text = self::extractTextFromStream($decoded);
                if ($text !== '') {
                    $texts[] = $text;
                }
            }
        }

        return self::cleanText(implode("\n", $texts));
    }

    private static function decodeStream(string $stream): string
    {
        foreach ([$stream, substr($stream, 2)] as $candidate) {
            if ($candidate === '') {
                continue;
            }

            try {
                $decoded = @gzuncompress($candidate);
                if ($decoded !== false) {
                    return $decoded;
                }
            } catch (Throwable) {
                // Nem todo stream PDF usa FlateDecode.
            }

            try {
                $decoded = @gzdecode($candidate);
                if ($decoded !== false) {
                    return $decoded;
                }
            } catch (Throwable) {
                // Tenta o proximo formato e preserva o stream original como fallback.
            }
        }

        return $stream;
    }

    private static function extractWithPdftotext(string $filePath): string
    {
        if (!function_exists('proc_open')) {
            return '';
        }

        $binary = self::pdftotextBinary();
        if ($binary === '') {
            return '';
        }

        $result = ProcessRunnerService::run([$binary, '-layout', '-enc', 'UTF-8', $filePath, '-'], 15);
        if ((int) $result['exit_code'] !== 0 || !empty($result['timed_out'])) {
            return '';
        }

        return self::cleanText((string) $result['stdout']);
    }

    private static function pdftotextBinary(): string
    {
        $configured = trim((string) getenv('PDFTOTEXT_BINARY'));
        if ($configured !== '') {
            return $configured;
        }

        $isWindows = stripos(PHP_OS, 'WIN') === 0;
        $finder = $isWindows ? ['where.exe', 'pdftotext'] : ['/bin/sh', '-lc', 'command -v pdftotext'];
        $result = ProcessRunnerService::run($finder, 5);
        if ((int) $result['exit_code'] !== 0) {
            return '';
        }

        return trim((string) strtok((string) $result['stdout'], "\r\n"));
    }

    private static function extractTextFromStream(string $stream): string
    {
        $texts = [];

        if (preg_match_all('/\((?:\\\\.|[^\\\\()])*\)\s*Tj/s', $stream, $matches)) {
            foreach ($matches[0] as $match) {
                if (preg_match('/\(((?:\\\\.|[^\\\\()])*)\)\s*Tj/s', $match, $textMatch)) {
                    $texts[] = self::decodePdfString($textMatch[1]);
                }
            }
        }

        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $stream, $matches)) {
            foreach ($matches[1] as $arrayText) {
                if (preg_match_all('/\((?:\\\\.|[^\\\\()])*\)|<([0-9A-Fa-f\s]+)>/s', $arrayText, $parts)) {
                    $line = '';
                    foreach ($parts[0] as $part) {
                        if (substr($part, 0, 1) === '(') {
                            $line .= self::decodePdfString(substr($part, 1, -1));
                        } elseif (preg_match('/<([0-9A-Fa-f\s]+)>/', $part, $hexMatch)) {
                            $line .= self::decodeHexString($hexMatch[1]);
                        }
                    }
                    $texts[] = $line;
                }
            }
        }

        if (preg_match_all('/<([0-9A-Fa-f\s]+)>\s*Tj/s', $stream, $matches)) {
            foreach ($matches[1] as $hex) {
                $texts[] = self::decodeHexString($hex);
            }
        }

        if (preg_match_all('/\((?:\\\\.|[^\\\\()])*\)\s*[\'"]/s', $stream, $matches)) {
            foreach ($matches[0] as $match) {
                if (preg_match('/\(((?:\\\\.|[^\\\\()])*)\)\s*[\'"]/s', $match, $textMatch)) {
                    $texts[] = self::decodePdfString($textMatch[1]);
                }
            }
        }

        return self::cleanText(implode("\n", $texts));
    }

    private static function decodePdfString(string $value): string
    {
        $value = preg_replace_callback('/\\\\([0-7]{1,3})/', function ($match) {
            return chr(octdec($match[1]));
        }, $value);

        $replacements = [
            '\\n' => "\n",
            '\\r' => "\n",
            '\\t' => "\t",
            '\\b' => '',
            '\\f' => '',
            '\\(' => '(',
            '\\)' => ')',
            '\\\\' => '\\',
        ];

        return strtr($value, $replacements);
    }

    private static function decodeHexString(string $hex): string
    {
        $hex = preg_replace('/\s+/', '', $hex);
        if ($hex === '') {
            return '';
        }

        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        $binary = @hex2bin($hex);
        if ($binary === false) {
            return '';
        }

        if (substr($binary, 0, 2) === "\xFE\xFF") {
            return mb_convert_encoding(substr($binary, 2), 'UTF-8', 'UTF-16BE');
        }

        if (strpos($binary, "\0") !== false) {
            return mb_convert_encoding($binary, 'UTF-8', 'UTF-16BE');
        }

        return mb_convert_encoding($binary, 'UTF-8', 'Windows-1252');
    }

    private static function cleanText(string $text): string
    {
        $text = str_replace("\0", '', $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\R{3,}/', "\n\n", $text);
        return trim((string) $text);
    }
}
