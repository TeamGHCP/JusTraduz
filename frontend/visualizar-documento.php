<?php
require_once __DIR__ . '/app/bootstrap.php';
require_login();

$type = current_user_type();
$userId = current_user_id();
$documentId = (int) ($_GET['id'] ?? 0);

if ($documentId) {
    if ($type === 'cliente') {
        $document = fetch_one($pdo, 'SELECT d.*, ar.resumo, ar.explicacao, ar.confianca FROM documents d LEFT JOIN ai_results ar ON ar.document_id = d.id WHERE d.id = ? AND d.user_id = ?', [$documentId, $userId]);
    } else {
        $document = fetch_one($pdo, 'SELECT d.*, ar.resumo, ar.explicacao, ar.confianca FROM documents d LEFT JOIN ai_results ar ON ar.document_id = d.id WHERE d.id = ?', [$documentId]);
    }
} else {
    $document = null;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Documento | JusTraduz</title>
  <link rel="icon" href="assets/img/logo.png">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar($type, 'visualizar-documento.php'); ?>

    <main class="app-main">
      <header class="topbar">
        <div>
          <h1>Documento</h1>
          <p>Texto extraído e resultado da análise quando disponível.</p>
        </div>
        <?php if ($document && $document['confianca'] !== null): ?>
          <span class="badge badge-success">Confiança <?= e((string) $document['confianca']) ?>%</span>
        <?php endif; ?>
      </header>

      <?php if (!$document): ?>
        <?= empty_state('Nenhum documento selecionado ou disponível.') ?>
      <?php else: ?>
        <section class="doc-view">
          <article class="card doc-pane">
            <div class="dash-section-title"><h2>Texto extraído</h2><span class="badge badge-info"><?= e(strtoupper($document['tipo_arquivo'] ?? '')) ?></span></div>
            <div class="doc-text"><?= nl2br(e($document['texto_extraido'] ?: 'Texto ainda não extraído.')) ?></div>
          </article>
          <article class="card doc-pane">
            <div class="dash-section-title"><h2>Linguagem simples</h2></div>
            <div class="doc-text"><?= nl2br(e($document['explicacao'] ?: 'Análise por IA ainda não disponível.')) ?></div>
          </article>
        </section>
        <section class="dash-section card">
          <div class="dash-section-title">
            <h2>Resumo</h2>
            <?php if ($type === 'cliente'): ?><a class="btn btn-primary btn-sm" href="solicitar-ajuda.php"><?= icon_svg('help') ?> Pedir ajuda</a><?php endif; ?>
          </div>
          <p><?= nl2br(e($document['resumo'] ?: 'Resumo ainda não disponível.')) ?></p>
        </section>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
