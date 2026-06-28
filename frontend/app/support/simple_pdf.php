<?php

function simple_pdf_text(string $value): string
{
    $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
    if ($encoded === false) {
        $encoded = preg_replace('/[^\x20-\x7E]/', '?', $value) ?? $value;
    }

    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
}

function simple_pdf_wrap(string $text, int $maxChars): array
{
    $words = preg_split('/\s+/', trim($text));
    if (!$words) {
        return [''];
    }

    $lines = [];
    $line = '';
    foreach ($words as $word) {
        $next = $line === '' ? $word : $line . ' ' . $word;
        if (mb_strlen($next) > $maxChars && $line !== '') {
            $lines[] = $line;
            $line = $word;
            continue;
        }
        $line = $next;
    }

    if ($line !== '') {
        $lines[] = $line;
    }

    return $lines ?: [''];
}

function simple_pdf_download(string $filename, array $document): void
{
    $width = 595.28;
    $height = 841.89;
    $margin = 48;
    $y = 790;
    $content = [];

    $cmd = static function (string $line) use (&$content): void {
        $content[] = $line;
    };

    $text = static function (float $x, float $yPos, string $value, int $size = 11, string $font = 'F1') use ($cmd): void {
        $cmd(sprintf('BT /%s %d Tf %.2F %.2F Td (%s) Tj ET', $font, $size, $x, $yPos, simple_pdf_text($value)));
    };

    $line = static function (float $x1, float $y1, float $x2, float $y2) use ($cmd): void {
        $cmd(sprintf('%.2F %.2F m %.2F %.2F l S', $x1, $y1, $x2, $y2));
    };

    $rect = static function (float $x, float $yPos, float $w, float $h, string $rgb = '0.95 0.97 0.99') use ($cmd): void {
        $cmd(sprintf('q %s rg %.2F %.2F %.2F %.2F re f Q', $rgb, $x, $yPos, $w, $h));
    };

    $text($margin, $y, (string) ($document['title'] ?? 'Documento'), 28, 'F2');
    $text(455, $y + 2, 'JusTraduz', 22, 'F2');
    $y -= 30;
    $text($margin, $y, (string) ($document['number'] ?? ''), 11);
    $line($margin, $y - 14, $width - $margin, $y - 14);
    $y -= 48;

    foreach (($document['meta'] ?? []) as $label => $value) {
        $text($margin, $y, (string) $label . ':', 10, 'F2');
        $text($margin + 95, $y, (string) $value, 10);
        $y -= 16;
    }

    $y -= 16;
    $rect($margin, $y - 84, 236, 104);
    $rect(311, $y - 84, 236, 104);
    $text($margin + 14, $y, (string) ($document['left_title'] ?? 'Emitido por'), 12, 'F2');
    $text(325, $y, (string) ($document['right_title'] ?? 'Cliente'), 12, 'F2');
    $partyY = $y - 20;
    foreach (($document['left_lines'] ?? []) as $partyLine) {
        $text($margin + 14, $partyY, (string) $partyLine, 10);
        $partyY -= 14;
    }
    $partyY = $y - 20;
    foreach (($document['right_lines'] ?? []) as $partyLine) {
        $text(325, $partyY, (string) $partyLine, 10);
        $partyY -= 14;
    }

    $y -= 132;
    $text($margin, $y, (string) ($document['summary'] ?? ''), 17, 'F2');
    $y -= 34;

    $headers = $document['headers'] ?? [];
    $rows = $document['rows'] ?? [];
    $columns = $document['columns'] ?? [260, 95, 95];
    $x = $margin;
    foreach ($headers as $index => $header) {
        $text($x, $y, (string) $header, 9, 'F2');
        $x += (float) ($columns[$index] ?? 120);
    }
    $line($margin, $y - 9, $width - $margin, $y - 9);
    $y -= 28;

    foreach ($rows as $row) {
        $x = $margin;
        $rowHeight = 20;
        foreach ($row as $index => $cell) {
            $cellLines = simple_pdf_wrap((string) $cell, $index === 0 ? 42 : 18);
            $cellY = $y;
            foreach ($cellLines as $cellLine) {
                $text($x, $cellY, $cellLine, 10);
                $cellY -= 13;
            }
            $rowHeight = max($rowHeight, count($cellLines) * 13 + 8);
            $x += (float) ($columns[$index] ?? 120);
        }
        $y -= $rowHeight;
    }

    $line($margin, $y + 7, $width - $margin, $y + 7);
    $y -= 26;

    foreach (($document['totals'] ?? []) as $label => $value) {
        $text(350, $y, (string) $label, 10, $label === 'Total' || $label === 'Valor devido' ? 'F2' : 'F1');
        $text(470, $y, (string) $value, 10, $label === 'Total' || $label === 'Valor devido' ? 'F2' : 'F1');
        $y -= 17;
    }

    if (!empty($document['history_headers']) && !empty($document['history_rows'])) {
        $y -= 28;
        $text($margin, $y, 'Histórico do pagamento', 14, 'F2');
        $y -= 26;
        $x = $margin;
        $historyColumns = $document['history_columns'] ?? [120, 100, 90, 170];
        foreach ($document['history_headers'] as $index => $header) {
            $text($x, $y, (string) $header, 9, 'F2');
            $x += (float) ($historyColumns[$index] ?? 120);
        }
        $line($margin, $y - 9, $width - $margin, $y - 9);
        $y -= 28;
        foreach ($document['history_rows'] as $row) {
            $x = $margin;
            foreach ($row as $index => $cell) {
                $text($x, $y, (string) $cell, 10);
                $x += (float) ($historyColumns[$index] ?? 120);
            }
            $y -= 18;
        }
    }

    $footerY = 58;
    $line($margin, $footerY + 22, $width - $margin, $footerY + 22);
    foreach (($document['footer'] ?? []) as $footerLine) {
        $text($margin, $footerY, (string) $footerLine, 8);
        $footerY -= 12;
    }

    $stream = implode("\n", $content) . "\n";
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $width . ' ' . $height . '] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream",
    ];

    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [0];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}
