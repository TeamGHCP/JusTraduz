<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_role(['admin']);

$userId = (int) ($_GET['user_id'] ?? 0);
$action = trim((string) ($_GET['action'] ?? ''));
$entity = trim((string) ($_GET['entity_type'] ?? ''));
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

if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $where[] = 'DATE(a.created_at) = ?';
    $params[] = $date;
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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Auditoria | Admin JusTraduz</title>
  <link rel="icon" href="../assets/img/logo.png">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <div class="app-shell admin-shell">
    <?php render_sidebar('admin', 'auditoria.php', true); ?>

    <main class="app-main">
      <?php render_topbar('Auditoria', 'Logs de ações sensíveis do sistema, usuários e integrações.', current_user_name()); ?>

      <form class="card audit-filter" method="get">
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
          <label for="date">Data</label>
          <input class="input" id="date" name="date" type="date" value="<?= e($date) ?>">
        </div>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit">Filtrar</button>
          <a class="btn btn-outline" href="auditoria.php">Limpar</a>
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
              <thead><tr><th>Data</th><th>Usuário</th><th>Ação</th><th>Entidade</th><th>Detalhes</th><th>Origem</th></tr></thead>
              <tbody>
                <?php foreach ($logs as $log): ?>
                  <tr>
                    <td><?= e(date('d/m/Y H:i:s', strtotime($log['created_at']))) ?></td>
                    <td><?= e($log['nome'] ?: 'Sistema') ?><span class="table-subtext"><?= e($log['email'] ?? '') ?></span></td>
                    <td><strong><?= e($log['action']) ?></strong></td>
                    <td><?= e(($log['entity_type'] ?: '-') . ($log['entity_id'] ? ' #' . $log['entity_id'] : '')) ?></td>
                    <td><code><?= e($log['details'] ?: '{}') ?></code></td>
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
</body>
</html>
