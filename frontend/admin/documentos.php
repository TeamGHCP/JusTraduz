<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_role(['admin']);

$documents = fetch_all(
    $pdo,
    'SELECT d.id, d.nome_arquivo, d.tipo_arquivo, d.texto_extraido, d.created_at, u.nome AS usuario, u.email
     FROM documents d
     INNER JOIN users u ON u.id = d.user_id
     ORDER BY d.created_at DESC'
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Documentos | Admin JusTraduz</title>
  <link rel="icon" href="../assets/img/logo.png">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <div class="app-shell admin-shell">
    <?php render_sidebar('admin', 'documentos.php', true); ?>

    <main class="app-main">
      <?php render_topbar('Documentos', 'Audite envios e acompanhe extrações de texto.', current_user_name()); ?>

      <section class="grid grid-3">
        <?= stat_card('Total de documentos', count($documents), 'folder') ?>
        <?= stat_card('Com texto extraído', count(array_filter($documents, fn ($doc) => !empty($doc['texto_extraido']))), 'file') ?>
        <?= stat_card('Pendentes', count(array_filter($documents, fn ($doc) => empty($doc['texto_extraido']))), 'help') ?>
      </section>

      <section class="dash-section">
        <div class="dash-section-title">
          <h2>Envios recentes</h2>
          <span class="badge badge-info"><?= e((string) count($documents)) ?> registros</span>
        </div>
        <?php if (!$documents): ?>
          <?= empty_state('Nenhum documento enviado ainda.') ?>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Documento</th><th>Usuário</th><th>Tipo</th><th>Extração</th><th>Enviado em</th><th>Ação</th></tr></thead>
              <tbody>
                <?php foreach ($documents as $document): ?>
                  <tr>
                    <td><strong><?= e($document['nome_arquivo']) ?></strong><span class="table-subtext">#<?= (int) $document['id'] ?></span></td>
                    <td><?= e($document['usuario']) ?><span class="table-subtext"><?= e($document['email']) ?></span></td>
                    <td><?= e(strtoupper($document['tipo_arquivo'] ?? '')) ?></td>
                    <td><span class="badge <?= !empty($document['texto_extraido']) ? 'badge-success' : 'badge-warning' ?>"><?= !empty($document['texto_extraido']) ? 'Concluída' : 'Pendente' ?></span></td>
                    <td><?= e(date('d/m/Y H:i', strtotime($document['created_at']))) ?></td>
                    <td><a href="../visualizar-documento.php?id=<?= (int) $document['id'] ?>">Abrir</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    </main>
  </div>
</body>
</html>
