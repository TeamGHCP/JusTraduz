<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_role(['admin']);

function audit_severity(string $action): string
{
    if (str_contains($action, 'failed') || str_contains($action, 'error') || str_contains($action, 'delete')) {
        return 'critical';
    }

    if (str_starts_with($action, 'admin.') || str_starts_with($action, 'case.') || str_starts_with($action, 'schedule.')) {
        return 'warning';
    }

    return 'info';
}

function audit_severity_label(string $severity): string
{
    return match ($severity) {
        'critical' => 'Crítico',
        'warning' => 'Atenção',
        default => 'Informativo',
    };
}

function audit_severity_badge(string $severity): string
{
    return match ($severity) {
        'critical' => 'badge-danger',
        'warning' => 'badge-warning',
        default => 'badge-info',
    };
}

function audit_pretty_details(?string $details): string
{
    $details = trim((string) $details);
    if ($details === '') {
        return '{}';
    }

    $decoded = json_decode($details, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return $details;
    }

    return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
}

$userId = (int) ($_GET['user_id'] ?? 0);
$action = trim((string) ($_GET['action'] ?? ''));
$entity = trim((string) ($_GET['entity_type'] ?? ''));
$daté = trim((string) ($_GET['date'] ?? ''));
$severityFilter = $_GET['severity'] ?? '';
$date = trim((string) ($_GET['date'] ?? ''));
$where = [];
$params = [];

if ($userId > 0) {
    $where[] = 'a.user_id = ?';
    $params[] = $userId;
}

if ($action !== '') {
    $where[] = 'a.action LIKE ?';
    $params[] = '%' . $action . '%';
}

if ($entity !== '') {
    $where[] = 'a.entity_type = ?';
    $params[] = $entity;
}

if ($daté !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $where[] = 'DATE(a.created_at) = ?';
    $params[] = $date;
}

if ($severityFilter === 'critical') {
    $where[] = "(a.action LIKE '%failed%' OR a.action LIKE '%error%' OR a.action LIKE '%delete%')";
} elseif ($severityFilter === 'warning') {
    $where[] = "(a.action LIKE 'admin.%' OR a.action LIKE 'case.%' OR a.action LIKE 'schedule.%')";
} elseif ($severityFilter === 'info') {
    $where[] = "(a.action NOT LIKE '%failed%' AND a.action NOT LIKE '%error%' AND a.action NOT LIKE '%delete%' AND a.action NOT LIKE 'admin.%' AND a.action NOT LIKE 'case.%' AND a.action NOT LIKE 'schedule.%')";
}

$sql = 'SELECT a.*, u.nome, u.email
        FROM audit_logs a
        LEFT JOIN users u ON u.id = a.user_id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY a.created_at DESC LIMIT 300';

$logs = fetch_all($pdo, $sql, $params);
$users = fetch_all($pdo, 'SELECT id, nome, email FROM users ORDER BY nome');
$entities = fetch_all($pdo, 'SELECT DISTINCT entity_type FROM audit_logs WHERE entity_type IS NOT NULL ORDER BY entity_type');

$criticalCount = count(array_filter($logs, static fn ($log): bool => audit_severity((string) $log['action']) === 'critical'));
$warningCount = count(array_filter($logs, static fn ($log): bool => audit_severity((string) $log['action']) === 'warning'));
$infoCount = max(0, count($logs) - $criticalCount - $warningCount);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta name="robots" content="noindex, nofollow">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Auditoria | Admin JusTraduz</title>
  <link rel="icon" href="../assets/img/icon.ico" type="image/x-icon">
  <link rel="apple-touch-icon" href="../assets/img/apple-touch-icon.png">
  <link rel="manifest" href="../site.webmanifest">
  <meta name="theme-color" content="#008f80">
  <meta name="application-name" content="JusTraduz">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="JusTraduz">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="msapplication-TileColor" content="#008f80">
  <link rel="stylesheet" href="../assets/css/style.css?v=2026.07.02-vlibras-panel-1">
  <script src="../assets/js/pwa.js" defer></script>
</head>
<body>
  <div class="app-shell admin-shell">
    <?php render_sidebar('admin', 'auditoria.php', true); ?>

    <main class="app-main">
      <?php render_topbar('Auditoria', 'Logs de ações sensíveis, segurança e integrações.', current_user_name()); ?>

      <section class="grid grid-4">
        <?= stat_card('Eventos filtrados', count($logs), 'shield') ?>
        <?= stat_card('Críticos', $criticalCount, 'lock') ?>
        <?= stat_card('Atenção', $warningCount, 'help') ?>
        <?= stat_card('Informativos', $infoCount, 'chart') ?>
      </section>

      <form class="card audit-filter audit-filter-wide" method="get">
        <div class="field">
          <label for="user_id">Usuário</label>
          <select class="select" id="user_id" name="user_id">
            <option value="">Todos</option>
            <?php foreach ($users as $user): ?>
              <option value="<?= (int) $user['id'] ?>" <?= $userId === (int) $user['id'] ? 'selected' : '' ?>><?= e($user['nome'] . ' - ' . $user['email']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="action">Ação</label>
          <input class="input" id="action" name="action" value="<?= e($action) ?>" placeholder="auth.login, schedule...">
        </div>
        <div class="field">
          <label for="entity_type">Entidade</label>
          <select class="select" id="entity_type" name="entity_type">
            <option value="">Todas</option>
            <?php foreach ($entities as $item): ?>
              <option value="<?= e($item['entity_type']) ?>" <?= $entity === $item['entity_type'] ? 'selected' : '' ?>><?= e($item['entity_type']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="severity">Severidade</label>
          <select class="select" id="severity" name="severity">
            <option value="">Todas</option>
            <option value="critical" <?= $severityFilter === 'critical' ? 'selected' : '' ?>>Crítico</option>
            <option value="warning" <?= $severityFilter === 'warning' ? 'selected' : '' ?>>Atenção</option>
            <option value="info" <?= $severityFilter === 'info' ? 'selected' : '' ?>>Informativo</option>
          </select>
        </div>
        <div class="field">
          <label for="date">Data</label>
          <input class="input" id="date" name="date" type="date" value="<?= e($date) ?>">
        </div>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit">Filtrar</button>
          <a class="btn btn-outline" href="auditoria.php">Limpar</a>
          <a class="btn btn-outline" href="<?= e(app_url('/backend/public/index.php?rota=/admin/audit/export&' . http_build_query($_GET))) ?>"><?= icon_svg('download') ?> CSV</a>
        </div>
      </form>

      <section class="dash-section">
        <div class="dash-section-title">
          <h2>Eventos registrados</h2>
          <span class="badge badge-info"><?= e((string) count($logs)) ?> registros</span>
        </div>

        <?php if (!$logs): ?>
          <?= empty_state('Nenhum log encontrado para os filtros selecionados.') ?>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table audit-table">
              <thead><tr><th>Data</th><th>Severidade</th><th>Usuário</th><th>Ação</th><th>Entidade</th><th>Detalhes</th><th>Origem</th></tr></thead>
              <tbody>
                <?php foreach ($logs as $log): ?>
                  <?php $severity = audit_severity((string) $log['action']); ?>
                  <tr>
                    <td><?= e(date('d/m/Y H:i:s', strtotime($log['created_at']))) ?></td>
                    <td><span class="badge <?= e(audit_severity_badge($severity)) ?>"><?= e(audit_severity_label($severity)) ?></span></td>
                    <td><?= e($log['nome'] ?: 'Sistema') ?><span class="table-subtext"><?= e($log['email'] ?? '') ?></span></td>
                    <td><strong><?= e($log['action']) ?></strong></td>
                    <td><?= e(($log['entity_type'] ?: '-') . ($log['entity_id'] ? ' #' . $log['entity_id'] : '')) ?></td>
                    <td><pre class="audit-json"><code><?= e(audit_pretty_details($log['details'] ?? null)) ?></code></pre></td>
                    <td><?= e($log['ip_address'] ?: '-') ?><span class="table-subtext"><?= e($log['user_agent'] ?: '') ?></span></td>
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
