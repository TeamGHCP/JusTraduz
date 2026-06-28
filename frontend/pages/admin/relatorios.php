<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once PROJECT_ROOT_PATH . '/backend/app/services/SlaService.php';
require_permission('reports.view');

$usersByRole = fetch_all($pdo, "SELECT tipo, COUNT(*) AS total FROM users WHERE tipo IN ('cliente', 'advogado', 'admin') GROUP BY tipo ORDER BY tipo");
$documentsTotal = count_query($pdo, 'SELECT COUNT(*) FROM documents');
$documentsAnalyzed = count_query($pdo, 'SELECT COUNT(DISTINCT document_id) FROM ai_results');
$casesByStatus = fetch_all($pdo, 'SELECT status, COUNT(*) AS total FROM cases GROUP BY status ORDER BY status');
$casesByPriority = fetch_all($pdo, 'SELECT prioridade, COUNT(*) AS total FROM cases GROUP BY prioridade ORDER BY prioridade');
$pendingOab = count_query(
    $pdo,
    "SELECT COUNT(*) FROM users
     WHERE tipo = 'advogado'
       AND status = 'ativo'
       AND oab_verificado = FALSE
       AND COALESCE(status_cna, 'pendente') = 'pendente'"
);
$aiErrors = count_query($pdo, "SELECT COUNT(*) FROM audit_logs WHERE action = 'document.ai_error'");
$recentDocuments = fetch_all(
    $pdo,
    'SELECT DATE(created_at) AS dia, COUNT(*) AS total
     FROM documents
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
     GROUP BY DATE(created_at)
     ORDER BY dia DESC
     LIMIT 30'
);
$activeCases = fetch_all(
    $pdo,
    "SELECT id, titulo, status, prioridade, created_at
     FROM cases
     WHERE status <> 'finalizado'
     ORDER BY created_at ASC"
);
$overdueCases = 0;
$dueSoonCases = 0;
foreach ($activeCases as $case) {
    $sla = SlaService::statusForCase($case);
    if ($sla['state'] === 'overdue') {
        $overdueCases++;
    } elseif ($sla['state'] === 'due_soon') {
        $dueSoonCases++;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Relatórios | JusTraduz</title>
  <link rel="icon" href="../assets/img/icon.ico" type="image/x-icon">
  <link rel="manifest" href="../site.webmanifest">
  <meta name="theme-color" content="#008f80">
  <link rel="stylesheet" href="../assets/css/style.css?v=site-polish-20260625">
  <script src="../assets/js/pwa.js" defer></script>
</head>
<body>
  <div class="app-shell admin-shell">
    <?php render_sidebar('admin', 'relatorios.php', true); ?>

    <main class="app-main">
      <?php render_topbar('Relatórios', 'Indicadores gerenciais para operação, SLA, documentos e IA.', current_user_name()); ?>

      <section class="grid grid-4">
        <?= stat_card('Documentos', $documentsTotal, 'folder') ?>
        <?= stat_card('Análises IA', $documentsAnalyzed, 'chart') ?>
        <?= stat_card('SLA vencido', $overdueCases, 'case') ?>
        <?= stat_card('OAB pendente', $pendingOab, 'shield') ?>
      </section>

      <section class="admin-dashboard-grid mt-16">
        <article class="card admin-chart-card">
          <div class="dash-section-title">
            <h2>Usuários por perfil</h2>
          </div>
          <div class="horizontal-bars">
            <?php foreach ($usersByRole as $row): ?>
              <div class="hbar-row">
                <div><span><?= e(ucfirst((string) $row['tipo'])) ?></span><strong><?= e((string) $row['total']) ?></strong></div>
                <span class="hbar-track"><i style="--bar: 100%"></i></span>
              </div>
            <?php endforeach; ?>
          </div>
        </article>

        <article class="card admin-chart-card">
          <div class="dash-section-title">
            <h2>Casos por status</h2>
          </div>
          <div class="horizontal-bars">
            <?php foreach ($casesByStatus as $row): ?>
              <div class="hbar-row">
                <div><span><?= e(status_label((string) $row['status'])) ?></span><strong><?= e((string) $row['total']) ?></strong></div>
                <span class="hbar-track"><i style="--bar: 100%"></i></span>
              </div>
            <?php endforeach; ?>
          </div>
        </article>

        <article class="card admin-chart-card">
          <div class="dash-section-title">
            <h2>Prioridade operacional</h2>
            <span class="badge <?= $dueSoonCases > 0 ? 'badge-warning' : 'badge-success' ?>"><?= e((string) $dueSoonCases) ?> próximo(s)</span>
          </div>
          <div class="horizontal-bars">
            <?php foreach ($casesByPriority as $row): ?>
              <div class="hbar-row">
                <div><span><?= e((string) $row['prioridade']) ?></span><strong><?= e((string) $row['total']) ?></strong></div>
                <span class="hbar-track"><i style="--bar: 100%"></i></span>
              </div>
            <?php endforeach; ?>
          </div>
        </article>

        <article class="card admin-chart-card">
          <div class="dash-section-title">
            <h2>IA e documentos</h2>
            <span class="badge <?= $aiErrors > 0 ? 'badge-warning' : 'badge-success' ?>"><?= e((string) $aiErrors) ?> erro(s)</span>
          </div>
          <p class="text-muted">Documentos analisados: <?= e((string) $documentsAnalyzed) ?> de <?= e((string) $documentsTotal) ?>.</p>
          <a class="btn btn-soft btn-sm mt-16" href="documentos.php?analysis=pendente">Ver pendências</a>
        </article>
      </section>

      <section class="dash-section mt-16">
        <div class="dash-section-title">
          <h2>Documentos nos últimos 30 dias</h2>
          <div class="inline-actions">
            <a class="btn btn-soft btn-sm" href="<?= e(app_url('/backend/public/index.php?rota=/api/v1/admin/reports/summary')) ?>">API v1</a>
            <a class="btn btn-soft btn-sm" href="<?= e(app_url('/backend/public/index.php?rota=/api/v1/admin/reports/export&type=cases')) ?>">CSV casos</a>
            <a class="btn btn-soft btn-sm" href="<?= e(app_url('/backend/public/index.php?rota=/api/v1/admin/reports/export&type=users')) ?>">CSV usuários</a>
            <a class="btn btn-soft btn-sm" href="<?= e(app_url('/backend/public/index.php?rota=/api/v1/admin/reports/export&type=documents')) ?>">CSV documentos</a>
          </div>
        </div>
        <?php if (!$recentDocuments): ?>
          <?= empty_state('Nenhum documento enviado no período.') ?>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Dia</th><th>Total</th></tr></thead>
              <tbody>
                <?php foreach ($recentDocuments as $row): ?>
                  <tr>
                    <td><?= e(date('d/m/Y', strtotime((string) $row['dia']))) ?></td>
                    <td><?= e((string) $row['total']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    </main>
  </div>
  <?php render_vlibras(); ?>
</body>
</html>
