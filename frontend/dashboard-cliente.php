<?php
require_once __DIR__ . '/app/bootstrap.php';
require_role(['cliente']);

$userId = current_user_id();
$documentCount = count_query($pdo, 'SELECT COUNT(*) FROM documents WHERE user_id = ?', [$userId]);
$analysisCount = count_query($pdo, 'SELECT COUNT(*) FROM ai_results ar INNER JOIN documents d ON d.id = ar.document_id WHERE d.user_id = ?', [$userId]);
$caseCount = count_query($pdo, "SELECT COUNT(*) FROM cases WHERE cliente_id = ? AND status <> 'finalizado'", [$userId]);
$messageCount = count_query($pdo, 'SELECT COUNT(*) FROM messages m INNER JOIN cases c ON c.id = m.case_id WHERE c.cliente_id = ?', [$userId]);
$documents = fetch_all($pdo, 'SELECT id, nome_arquivo, tipo_arquivo, created_at FROM documents WHERE user_id = ? ORDER BY created_at DESC LIMIT 8', [$userId]);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard do cliente | JusTraduz</title>
  <link rel="icon" href="assets/img/logo.png">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar('cliente', 'dashboard-cliente.php'); ?>

    <main class="app-main">
      <?php render_topbar('Olá, ' . current_user_name(), 'Envie documentos, acompanhe análises e peça ajuda especializada.', 'Cliente'); ?>

      <section class="grid grid-4">
        <?= stat_card('Documentos', $documentCount, 'file') ?>
        <?= stat_card('Análises feitas', $analysisCount, 'chart') ?>
        <?= stat_card('Casos ativos', $caseCount, 'case') ?>
        <?= stat_card('Mensagens', $messageCount, 'chat') ?>
      </section>

      <section class="dash-section">
        <form class="card" action="../backend/public/index.php?rota=/documents/upload" method="post" enctype="multipart/form-data">
          <div class="dash-section-title">
            <h2>Novo documento</h2>
            <span class="badge badge-info">Máx. 50 MB</span>
          </div>
          <label class="upload-box" data-upload-box>
            <input class="sr-only" type="file" name="documento" accept=".pdf,.png,.jpg,.jpeg,.webp" data-upload-input required>
            <?= icon_svg('upload') ?>
            <strong>Arraste seu arquivo ou clique para selecionar</strong>
            <p data-file-name>PDF, PNG, JPEG ou WebP</p>
            <span class="btn btn-primary">Selecionar arquivo</span>
          </label>
          <p class="mt-14 text-muted">A análise automática não substitui orientação jurídica profissional.</p>
          <button class="btn btn-primary mt-16" type="submit">Enviar documento</button>
        </form>
      </section>

      <section class="dash-section">
        <div class="dash-section-title">
          <h2>Histórico de documentos</h2>
          <a class="btn btn-soft btn-sm" href="solicitar-ajuda.php"><?= icon_svg('help') ?> Pedir ajuda</a>
        </div>
        <?php if (!$documents): ?>
          <?= empty_state('Nenhum documento enviado ainda.') ?>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Documento</th><th>Tipo</th><th>Data</th><th>Ação</th></tr></thead>
              <tbody>
                <?php foreach ($documents as $document): ?>
                  <tr>
                    <td><?= e($document['nome_arquivo']) ?></td>
                    <td><?= e(strtoupper($document['tipo_arquivo'] ?? '')) ?></td>
                    <td><?= e(date('d/m/Y H:i', strtotime($document['created_at']))) ?></td>
                    <td><a href="visualizar-documento.php?id=<?= (int) $document['id'] ?>">Abrir</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    </main>
  </div>
  <script src="assets/js/upload.js"></script>
</body>
</html>
