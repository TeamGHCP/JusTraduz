<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_role(['admin']);

$plans = database_table_exists($pdo, 'plans') ? fetch_all($pdo, 'SELECT * FROM plans WHERE active = 1 ORDER BY sort_order ASC') : [];
$users = fetch_all(
    $pdo,
    "SELECT id, nome, email, tipo
     FROM users
     WHERE status = 'ativo'
       AND (
           tipo = 'cliente'
           OR (tipo = 'advogado' AND (oab_verificado = TRUE OR oab_status = 'approved' OR status_cna = 'verificado'))
       )
     ORDER BY tipo, nome"
);
$subscriptions = database_table_exists($pdo, 'subscriptions') ? fetch_all(
    $pdo,
    "SELECT s.*, u.nome, u.email, u.tipo, p.name AS plan_name, p.slug AS plan_slug
     FROM subscriptions s
     INNER JOIN users u ON u.id = s.user_id AND u.tipo IN ('cliente', 'advogado')
     INNER JOIN plans p ON p.id = s.plan_id
     ORDER BY s.created_at DESC
     LIMIT 150"
) : [];

function billing_status_badge(string $status): string
{
    return match ($status) {
        'active', 'trialing' => 'badge-success',
        'past_due' => 'badge-warning',
        default => 'badge-danger',
    };
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta name="robots" content="noindex, nofollow">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Assinaturas | Admin JusTraduz</title>
  <link rel="icon" href="../assets/img/icon.ico" type="image/x-icon">
  <link rel="stylesheet" href="../assets/css/style.css?v=2026.07.02-vlibras-panel-1">
</head>
<body>
  <div class="app-shell admin-shell">
    <?php render_sidebar('admin', 'assinaturas.php', true); ?>
    <main class="app-main">
      <?php render_topbar('Assinaturas', 'Planos, ciclos, status e cobrança operacional para clientes e advogados verificados.', current_user_name()); ?>

      <section class="grid grid-4">
        <?= stat_card('Planos ativos', count($plans), 'sparkles') ?>
        <?= stat_card('Assinaturas', count($subscriptions), 'chart') ?>
        <?= stat_card('Ativas', count_query($pdo, "SELECT COUNT(*) FROM subscriptions WHERE status IN ('active', 'trialing')"), 'check') ?>
        <?= stat_card('Inadimplentes', count_query($pdo, "SELECT COUNT(*) FROM subscriptions WHERE status = 'past_due'"), 'shield') ?>
      </section>

      <form class="card admin-filter admin-filter-wide" action="<?= e(app_url('/backend/public/index.php?rota=/admin/p2/subscriptions/update')) ?>" method="post">
        <?= csrf_input() ?>
        <div class="field">
          <label for="user_id">Usuário</label>
          <select class="select" id="user_id" name="user_id" required>
            <?php foreach ($users as $user): ?>
              <option value="<?= (int) $user['id'] ?>"><?= e($user['nome'] . ' · ' . $user['email'] . ' · ' . $user['tipo']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="plan_id">Plano</label>
          <select class="select" id="plan_id" name="plan_id" required>
            <?php foreach ($plans as $plan): ?>
              <option value="<?= (int) $plan['id'] ?>"><?= e($plan['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="billing_cycle">Ciclo</label>
          <select class="select" id="billing_cycle" name="billing_cycle">
            <option value="monthly">Mensal</option>
            <option value="yearly">Anual</option>
          </select>
        </div>
        <div class="field">
          <label for="status">Status</label>
          <select class="select" id="status" name="status">
            <option value="active">Ativo</option>
            <option value="trialing">Trial</option>
            <option value="past_due">Inadimplente</option>
            <option value="canceled">Cancelado</option>
          </select>
        </div>
        <div class="form-actions"><button class="btn btn-primary" type="submit">Atualizar assinatura</button></div>
      </form>

      <section class="dash-section">
        <div class="dash-section-title"><h2>Histórico de assinaturas</h2><span class="badge badge-info"><?= e((string) count($subscriptions)) ?> registros</span></div>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Usuário</th><th>Plano</th><th>Ciclo</th><th>Status</th><th>Período</th></tr></thead>
            <tbody>
              <?php foreach ($subscriptions as $subscription): ?>
                <tr>
                  <td><strong><?= e($subscription['nome']) ?></strong><span class="table-subtext"><?= e($subscription['email']) ?></span></td>
                  <td><?= e($subscription['plan_name']) ?></td>
                  <td><?= e($subscription['billing_cycle']) ?></td>
                  <td><span class="badge <?= e(billing_status_badge((string) $subscription['status'])) ?>"><?= e($subscription['status']) ?></span></td>
                  <td><?= e((string) ($subscription['current_period_start'] ?? '-')) ?><span class="table-subtext">até <?= e((string) ($subscription['current_period_end'] ?? '-')) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
  <?php render_vlibras(); ?>
</body>
</html>
