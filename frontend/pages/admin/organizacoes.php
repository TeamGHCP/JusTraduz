<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_role(['admin']);

$successMessage = trim((string) ($_GET['sucesso'] ?? ''));
$errorMessage = trim((string) ($_GET['erro'] ?? ''));
$users = fetch_all($pdo, "SELECT id, nome, email, tipo FROM users WHERE status = 'ativo' ORDER BY nome");
$organizations = database_table_exists($pdo, 'organizations') ? fetch_all(
    $pdo,
    "SELECT o.*, u.nome AS owner_name,
            (SELECT COUNT(*) FROM organization_members om WHERE om.organization_id = o.id AND om.status = 'active') AS member_count
     FROM organizations o
     LEFT JOIN users u ON u.id = o.owner_user_id
     ORDER BY o.created_at DESC"
) : [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Organizações | Admin JusTraduz</title>
  <link rel="icon" href="../assets/img/icon.ico" type="image/x-icon">
  <link rel="stylesheet" href="../assets/css/style.css?v=pricing-front-1">
</head>
<body>
  <div class="app-shell admin-shell">
    <?php render_sidebar('admin', 'organizacoes.php', true); ?>
    <main class="app-main">
      <?php render_topbar('Organizações', 'Escritórios, membros, papéis e isolamento de dados.', current_user_name()); ?>
      <?php if ($successMessage !== ''): ?><div class="alert is-visible alert-success"><?= e($successMessage) ?></div><?php endif; ?>
      <?php if ($errorMessage !== ''): ?><div class="alert is-visible alert-error"><?= e($errorMessage) ?></div><?php endif; ?>

      <section class="grid grid-2">
        <form class="card auth-form" action="<?= e(app_url('/backend/public/index.php?rota=/admin/p2/organizations/create')) ?>" method="post">
          <?= csrf_input() ?>
          <div class="dash-section-title"><h2>Novo escritório</h2></div>
          <div class="field"><label for="name">Nome</label><input class="input" id="name" name="name" required></div>
          <div class="field">
            <label for="owner_user_id">Proprietário</label>
            <select class="select" id="owner_user_id" name="owner_user_id" required>
              <?php foreach ($users as $user): ?><option value="<?= (int) $user['id'] ?>"><?= e($user['nome'] . ' · ' . $user['email']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <button class="btn btn-primary" type="submit">Criar organização</button>
        </form>

        <form class="card auth-form" action="<?= e(app_url('/backend/public/index.php?rota=/admin/p2/organizations/member')) ?>" method="post">
          <?= csrf_input() ?>
          <div class="dash-section-title"><h2>Adicionar membro</h2></div>
          <div class="field">
            <label for="organization_id">Organização</label>
            <select class="select" id="organization_id" name="organization_id" required>
              <?php foreach ($organizations as $organization): ?><option value="<?= (int) $organization['id'] ?>"><?= e($organization['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="member_user_id">Usuário</label>
            <select class="select" id="member_user_id" name="user_id" required>
              <?php foreach ($users as $user): ?><option value="<?= (int) $user['id'] ?>"><?= e($user['nome'] . ' · ' . $user['tipo']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="role">Papel</label>
            <select class="select" id="role" name="role"><option value="member">Membro</option><option value="admin">Admin da organização</option><option value="viewer">Leitor</option></select>
          </div>
          <button class="btn btn-primary" type="submit">Salvar membro</button>
        </form>
      </section>

      <section class="dash-section">
        <div class="dash-section-title"><h2>Escritórios</h2><span class="badge badge-info"><?= e((string) count($organizations)) ?> registros</span></div>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Organização</th><th>Dono</th><th>Membros</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($organizations as $organization): ?>
                <tr><td><strong><?= e($organization['name']) ?></strong><span class="table-subtext"><?= e($organization['slug']) ?></span></td><td><?= e($organization['owner_name'] ?? '-') ?></td><td><?= (int) $organization['member_count'] ?></td><td><span class="badge badge-success"><?= e($organization['status']) ?></span></td></tr>
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
