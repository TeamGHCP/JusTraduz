<?php

require_once dirname(__DIR__) . '/app/config/database.php';
require_once dirname(__DIR__) . '/app/services/GeminiService.php';

$gemini = new GeminiService();

if (!$gemini->isConfigured()) {
    throw new RuntimeException("Configure GEMINI_API_KEY como variável de ambiente ou copie backend/app/config/gemini.example.php para gemini.php.");
}

$stmt = $pdo->query(
    "SELECT d.id, d.caminho, d.texto_extraido
     FROM documents d
     LEFT JOIN ai_results ar ON ar.document_id = d.id
     WHERE ar.id IS NULL"
);

$documents = $stmt->fetchAll();
$updated = 0;

foreach ($documents as $document) {
    $text = (string) ($document['texto_extraido'] ?? '');
    if (is_extraction_failure($text)) {
        $text = '';
    }

    $path = dirname(__DIR__, 2) . '/' . ltrim(str_replace('\\', '/', (string) $document['caminho']), '/');
    $mime = is_file($path) ? (mime_content_type($path) ?: '') : '';

    if (is_file($path) && GeminiService::isSupportedFileMime($mime)) {
        $analysis = $gemini->analyzeDocumentFile($path, $mime, $text);
    } elseif (trim($text) !== '') {
        $analysis = $gemini->analyzeDocument($text);
    } else {
        echo "Documento #{$document['id']}: sem arquivo ou texto analisável.\n";
        continue;
    }

    if (!$analysis) {
        echo "Documento #{$document['id']}: análise não gerada. " . ($gemini->getLastError() ?: 'Sem detalhes.') . "\n";
        continue;
    }

    $insert = $pdo->prepare('INSERT INTO ai_results (document_id, resumo, explicacao, confianca) VALUES (?, ?, ?, ?)');
    $insert->execute([
        (int) $document['id'],
        $analysis['resumo'],
        $analysis['explicacao'],
        $analysis['confianca'],
    ]);

    $updated++;
    echo "Documento #{$document['id']}: análise criada.\n";
}

echo "Finalizado. {$updated} documento(s) analisado(s).\n";

function is_extraction_failure(string $text): bool
{
    $text = mb_strtolower($text);
    return str_contains($text, 'foi poss')
        && str_contains($text, 'extrair texto')
        && str_contains($text, 'pdf');
}
