<?php

require_once dirname(__DIR__) . '/app/config/database.php';
require_once dirname(__DIR__) . '/app/services/GeminiService.php';

$gemini = new GeminiService();

if (!$gemini->isConfigured()) {
    echo "Configure GEMINI_API_KEY como variável de ambiente ou copie backend/app/config/gemini.example.php para gemini.php.\n";
    exit(1);
}

$stmt = $pdo->query(
    "SELECT d.id, d.texto_extraido
     FROM documents d
     LEFT JOIN ai_results ar ON ar.document_id = d.id
     WHERE d.texto_extraido IS NOT NULL
     AND d.texto_extraido <> ''
     AND d.texto_extraido NOT LIKE 'Não foi possível extrair texto selecionável%'
     AND ar.id IS NULL"
);

$documents = $stmt->fetchAll();
$updated = 0;

foreach ($documents as $document) {
    $analysis = $gemini->analyzeDocument((string) $document['texto_extraido']);

    if (!$analysis) {
        echo "Documento #{$document['id']}: análise não gerada.\n";
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
