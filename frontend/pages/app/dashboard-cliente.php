<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_role(['cliente']);

$userId = current_user_id();
$documentCount = count_query($pdo, 'SELECT COUNT(*) FROM documents WHERE user_id = ?', [$userId]);
$analysisCount = count_query($pdo, 'SELECT COUNT(*) FROM ai_results ar INNER JOIN documents d ON d.id = ar.document_id WHERE d.user_id = ?', [$userId]);
$pendingAnalysisCount = count_query($pdo, 'SELECT COUNT(*) FROM documents d LEFT JOIN ai_results ar ON ar.document_id = d.id WHERE d.user_id = ? AND ar.id IS NULL', [$userId]);
$caseCount = count_query($pdo, "SELECT COUNT(*) FROM cases WHERE cliente_id = ? AND status <> 'finalizado'", [$userId]);
$messageCount = count_query($pdo, 'SELECT COUNT(*) FROM messages m INNER JOIN cases c ON c.id = m.case_id WHERE c.cliente_id = ?', [$userId]);
$documents = fetch_all(
    $pdo,
    'SELECT d.id, d.nome_arquivo, d.tipo_arquivo, d.created_at, ar.id AS analysis_id
     FROM documents d
     LEFT JOIN ai_results ar ON ar.document_id = d.id
     WHERE d.user_id = ?
     ORDER BY d.created_at DESC
     LIMIT 8',
    [$userId]
);
$lastDocument = $documents[0] ?? null;
$lastCase = fetch_one($pdo, 'SELECT id, titulo, status, created_at FROM cases WHERE cliente_id = ? ORDER BY created_at DESC LIMIT 1', [$userId]);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard do cliente | JusTraduz</title>
  <link rel="icon" href="assets/img/icon.ico" type="image/x-icon">
  <link rel="stylesheet" href="assets/css/style.css?v=theme-slow-2">
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar('cliente', 'dashboard-cliente.php'); ?>

    <main class="app-main">
      <?php render_topbar('Olá, ' . current_user_name(), 'Entenda documentos, peça ajuda e acompanhe seu atendimento.', current_user_name()); ?>

      <section class="client-command">
        <article class="command-card command-card-primary">
          <span class="badge badge-info">Fluxo principal</span>
          <h2>Envie um documento e transforme juridiquês em próximos passos.</h2>
          <p>O JusTraduz organiza análise, solicitação, chat e agenda para você sair da dúvida com segurança.</p>
          <div class="form-actions">
            <a class="btn btn-primary" href="#novo-documento"><?= icon_svg('upload') ?> Enviar documento</a>
            <a class="btn btn-outline" href="visualizar-documento.php"><?= icon_svg('file') ?> Ver documentos</a>
          </div>
        </article>

        <article class="command-card">
          <span>Último documento</span>
          <?php if ($lastDocument): ?>
            <strong><?= e($lastDocument['nome_arquivo']) ?></strong>
            <p><?= !empty($lastDocument['analysis_id']) ? 'Análise disponível para consulta.' : 'Análise ainda pendente.' ?></p>
            <a class="btn btn-soft btn-sm" href="visualizar-documento.php?id=<?= (int) $lastDocument['id'] ?>">Abrir</a>
          <?php else: ?>
            <strong>Nenhum envio</strong>
            <p>Comece pelo upload de PDF, PNG, JPEG ou WebP.</p>
          <?php endif; ?>
        </article>

        <article class="command-card">
          <span>Atendimento</span>
          <?php if ($lastCase): ?>
            <strong><?= e($lastCase['titulo']) ?></strong>
            <p>Status: <?= e(status_label($lastCase['status'] ?? '')) ?></p>
            <a class="btn btn-soft btn-sm" href="chat.php?case_id=<?= (int) $lastCase['id'] ?>">Abrir chat</a>
          <?php else: ?>
            <strong>Sem solicitação</strong>
            <p>Peça ajuda quando quiser orientação profissional.</p>
            <a class="btn btn-soft btn-sm" href="solicitar-ajuda.php">Pedir ajuda</a>
          <?php endif; ?>
        </article>
      </section>

      <section class="grid grid-4">
        <?= stat_card('Documentos', $documentCount, 'file') ?>
        <?= stat_card('Análises feitas', $analysisCount, 'chart') ?>
        <?= stat_card('Pendentes de IA', $pendingAnalysisCount, 'help') ?>
        <?= stat_card('Casos ativos', $caseCount, 'case') ?>
      </section>

      <section class="dash-section" id="novo-documento">
        <form class="card upload-card" action="<?= e(app_url('/backend/public/index.php?rota=/documents/upload')) ?>" method="post" enctype="multipart/form-data" data-upload-form>
          <?= csrf_input() ?>
          <div class="dash-section-title">
            <div>
              <h2>Novo documento</h2>
              <p class="text-muted">Envie o arquivo, autorize IA se fizer sentido e acompanhe a análise depois.</p>
            </div>
            <span class="badge badge-success">Máx. 50 MB</span>
          </div>
          <label class="upload-box upload-box-featured" data-upload-box>
            <input class="sr-only" type="file" name="documento" accept=".pdf,.png,.jpg,.jpeg,.webp" data-upload-input required>
            <?= icon_svg('upload') ?>
            <strong>Arraste seu arquivo ou clique para selecionar</strong>
            <p data-file-name>PDF, PNG, JPEG ou WebP</p>
            <span class="btn btn-primary">Selecionar arquivo</span>
          </label>
          <label class="checkline mt-14">
            <input type="checkbox" name="autorizar_ia" value="1">
            <span>Autorizo enviar este documento para análise automática por IA.</span>
          </label>
          <p class="mt-14 text-muted">A análise automática é informativa e não substitui orientação jurídica profissional.</p>
          <button class="btn btn-primary mt-16" type="submit" data-upload-submit>Enviar documento</button>
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
              <thead><tr><th>Documento</th><th>Tipo</th><th>Análise</th><th>Data</th><th>Ação</th></tr></thead>
              <tbody>
                <?php foreach ($documents as $document): ?>
                  <tr>
                    <td><strong><?= e($document['nome_arquivo']) ?></strong></td>
                    <td><?= e(strtoupper($document['tipo_arquivo'] ?? '')) ?></td>
                    <td><span class="badge <?= !empty($document['analysis_id']) ? 'badge-success' : 'badge-warning' ?>"><?= !empty($document['analysis_id']) ? 'Gerada' : 'Pendente' ?></span></td>
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
