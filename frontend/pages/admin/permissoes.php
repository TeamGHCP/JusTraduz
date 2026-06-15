<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_role(['admin']);

$successMessage = trim((string) ($_GET['sucesso'] ?? ''));
$errorMessage = trim((string) ($_GET['erro'] ?? ''));
$users = fetch_all($pdo, "SELECT id, nome, email, tipo FROM users WHERE status = 'ativo' ORDER BY nome");
$permissions = database_table_exists($pdo, 'user_permissions') ? fetch_all(
    $pdo,
    'SELECT up.*, u.nome, u.email FROM user_permissions up INNER JOIN users u ON u.id = up.user_id ORDER BY up.created_at DESC LIMIT 150'
) : [];
$knownPermissions = [
    'documents.view_own', 'documents.view_assigned', 'documents.create', 'documents.delete_own',
    'cases.view_own', 'cases.view_assigned', 'cases.create', 'cases.manage_assigned', 'cases.message',
    'tasks.manage_assigned', 'agenda.book', 'agenda.manage_own', 'reports.view_own', 'profile.manage',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Permissões | Admin JusTraduz</title>
  <link rel="icon" href="../assets/img/icon.ico" type="image/x-icon">
  <link rel="stylesheet" href="../assets/css/style.css?v=pricing-front-1">
</head>
<body>
  <div class="app-shell admin-shell">
    <?php render_sidebar('admin', 'permissoes.php', true); ?>
    <main class="app-main">
      <?php render_topbar('Permissões', 'RBAC granular por usuário e recurso.', current_user_name()); ?>
      <?php if ($successMessage !== ''): ?><div class="alert is-visible alert-success"><?= e($successMessage) ?></div><?php endif; ?>
      <?php if ($errorMessage !== ''): ?><div class="alert is-visible alert-error"><?= e($errorMessage) ?></div><?php endif; ?>

      <form class="card admin-filter admin-filter-wide" action="<?= e(app_url('/backend/public/index.php?rota=/admin/p2/permissions/update')) ?>" method="post">
        <?= csrf_input() ?>
        <div class="field"><label for="user_id">Usuário</label><select class="select" id="user_id" name="user_id"><?php foreach ($users as $user): ?><option value="<?= (int) $user['id'] ?>"><?= e($user['nome'] . ' · ' . $user['tipo']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="permission_key">Permissão</label><select class="select" id="permission_key" name="permission_key"><?php foreach ($knownPermissions as $permission): ?><option value="<?= e($permission) ?>"><?= e($permission) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="allowed">Regra</label><select class="select" id="allowed" name="allowed"><option value="1">Permitir</option><option value="0">Bloquear</option></select></div>
        <div class="form-actions"><button class="btn btn-primary" type="submit">Salvar permissão</button></div>
      </form>

      <section class="dash-section">
        <div class="dash-section-title"><h2>Permissões customizadas</h2><span class="badge badge-info"><?= e((string) count($permissions)) ?> registros</span></div>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Usuário</th><th>Permissão</th><th>Regra</th><th>Criada em</th></tr></thead>
            <tbody><?php foreach ($permissions as $permission): ?><tr><td><strong><?= e($permission['nome']) ?></strong><span class="table-subtext"><?= e($permission['email']) ?></span></td><td><?= e($permission['permission_key']) ?></td><td><span class="badge <?= (int) $permission['allowed'] === 1 ? 'badge-success' : 'badge-warning' ?>"><?= (int) $permission['allowed'] === 1 ? 'Permitir' : 'Bloquear' ?></span></td><td><?= e((string) $permission['created_at']) ?></td></tr><?php endforeach; ?></tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
  <?php render_vlibras(); ?>
</body>
</html>
