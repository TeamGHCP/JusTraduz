<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_role(['admin']);

if (database_table_has_column($pdo, 'cases', 'sla_status')) {
    $pdo->exec("UPDATE cases SET sla_status = 'vencido' WHERE status <> 'finalizado' AND sla_due_at IS NOT NULL AND sla_due_at < NOW()");
    $pdo->exec("UPDATE cases SET sla_status = 'em_risco' WHERE status <> 'finalizado' AND sla_due_at IS NOT NULL AND sla_due_at >= NOW() AND sla_due_at <= DATE_ADD(NOW(), INTERVAL 12 HOUR)");
    $pdo->exec("UPDATE cases SET sla_status = 'ok' WHERE status <> 'finalizado' AND sla_due_at IS NOT NULL AND sla_due_at > DATE_ADD(NOW(), INTERVAL 12 HOUR)");
}

$byStatus = fetch_all($pdo, 'SELECT status, COUNT(*) AS total FROM cases GROUP BY status ORDER BY total DESC');
$byPriority = fetch_all($pdo, 'SELECT prioridade, COUNT(*) AS total FROM cases GROUP BY prioridade ORDER BY total DESC');
$byProfessional = fetch_all($pdo, "SELECT COALESCE(u.nome, 'Sem responsável') AS nome, COUNT(*) AS total FROM cases c LEFT JOIN users u ON u.id = c.advogado_id GROUP BY COALESCE(u.nome, 'Sem responsável') ORDER BY total DESC LIMIT 10");
$sla = database_table_has_column($pdo, 'cases', 'sla_status') ? fetch_all($pdo, 'SELECT sla_status, COUNT(*) AS total FROM cases GROUP BY sla_status ORDER BY total DESC') : [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Relatórios | Admin JusTraduz</title>
  <link rel="icon" href="../assets/img/icon.ico" type="image/x-icon">
  <link rel="stylesheet" href="../assets/css/style.css?v=pricing-front-1">
</head>
<body>
  <div class="app-shell admin-shell">
    <?php render_sidebar('admin', 'relatorios.php', true); ?>
    <main class="app-main">
      <?php render_topbar('Relatórios', 'Produtividade, status, SLA e origem de demanda.', current_user_name()); ?>

      <section class="grid grid-4">
        <?= stat_card('Casos totais', count_query($pdo, 'SELECT COUNT(*) FROM cases'), 'case') ?>
        <?= stat_card('Finalizados', count_query($pdo, "SELECT COUNT(*) FROM cases WHERE status = 'finalizado'"), 'check') ?>
        <?= stat_card('SLA vencido', database_table_has_column($pdo, 'cases', 'sla_status') ? count_query($pdo, "SELECT COUNT(*) FROM cases WHERE sla_status = 'vencido'") : 0, 'shield') ?>
        <?= stat_card('Receita eventos', database_table_exists($pdo, 'payment_events') ? 'R$ ' . number_format(((int) $pdo->query("SELECT COALESCE(SUM(amount_cents),0) FROM payment_events WHERE status = 'paid'")->fetchColumn()) / 100, 0, ',', '.') : 'R$ 0', 'chart') ?>
      </section>

      <section class="grid grid-2">
        <?= report_table('Status dos casos', 'status', $byStatus) ?>
        <?= report_table('Prioridade', 'prioridade', $byPriority) ?>
        <?= report_table('Responsável', 'nome', $byProfessional) ?>
        <?= report_table('SLA', 'sla_status', $sla) ?>
      </section>
    </main>
  </div>
  <?php render_vlibras(); ?>
</body>
</html>

<?php
function report_table(string $title, string $labelKey, array $rows): string
{
    ob_start();
    ?>
    <section class="card">
      <div class="dash-section-title"><h2><?= e($title) ?></h2></div>
      <?php if (!$rows): ?>
        <?= empty_state('Sem dados para este relatório.') ?>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Indicador</th><th>Total</th></tr></thead>
            <tbody><?php foreach ($rows as $row): ?><tr><td><?= e((string) ($row[$labelKey] ?? '-')) ?></td><td><strong><?= e((string) (int) $row['total']) ?></strong></td></tr><?php endforeach; ?></tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
    <?php
    return ob_get_clean();
}
