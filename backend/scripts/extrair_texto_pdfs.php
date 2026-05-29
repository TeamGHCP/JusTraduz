<?php

require_once dirname(__DIR__) . '/app/config/database.php';
require_once dirname(__DIR__) . '/app/services/PdfTextExtractor.php';

$stmt = $pdo->query(
    "SELECT id, caminho
     FROM documents
     WHERE tipo_arquivo = 'pdf'
     AND (texto_extraido IS NULL OR texto_extraido = '')"
);

$documents = $stmt->fetchAll();
$updated = 0;

foreach ($documents as $document) {
    $filePath = dirname(__DIR__, 2) . '/' . ltrim((string) $document['caminho'], '/');

    if (!is_file($filePath)) {
        echo "Documento #{$document['id']}: arquivo não encontrado.\n";
        continue;
    }

    $text = PdfTextExtractor::extract($filePath);
    if ($text === '') {
        $text = 'Não foi possível extrair texto selecionável deste PDF. O arquivo pode estar escaneado como imagem e precisar de OCR.';
    }

    $update = $pdo->prepare('UPDATE documents SET texto_extraido = ? WHERE id = ?');
    $update->execute([$text, (int) $document['id']]);
    $updated++;

    echo "Documento #{$document['id']}: texto extraído.\n";
}

echo "Finalizado. {$updated} documento(s) atualizado(s).\n";
