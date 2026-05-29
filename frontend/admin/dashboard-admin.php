<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_role(['admin']);

$userCount = count_query($pdo, 'SELECT COUNT(*) FROM users');
$documentCount = count_query($pdo, 'SELECT COUNT(*) FROM documents');
$activeCaseCount = count_query($pdo, "SELECT COUNT(*) FROM cases WHERE status <> 'finalizado'");
$openCaseCount = count_query($pdo, "SELECT COUNT(*) FROM cases WHERE status = 'aberto'");
$users = fetch_all($pdo, 'SELECT id, nome, email, tipo, status, created_at FROM users ORDER BY created_at DESC LIMIT 10');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin | JusTraduz</title>
  <link rel="icon" href="../assets/img/logo.png">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar('admin', 'dashboard-admin.php', true); ?>

    <main class="app-main">
      <?php render_topbar('Dashboard administrativa', 'Visão geral de usuários, documentos e solicitações.', current_user_name()); ?>

      <section class="grid grid-4">
        <?= stat_card('Usuários', $userCount, 'users') ?>
        <?= stat_card('Documentos enviados', $documentCount, 'file') ?>
        <?= stat_card('Casos ativos', $activeCaseCount, 'case') ?>
        <?= stat_card('Solicitações abertas', $openCaseCount, 'help') ?>
      </section>

      <section class="dash-section">
        <div class="dash-section-title"><h2>Usuários recentes</h2></div>
        <?php if (!$users): ?>
          <?= empty_state('Nenhum usuário cadastrado.') ?>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Nome</th><th>E-mail</th><th>Tipo</th><th>Status</th><th>Criado em</th></tr></thead>
              <tbody>
                <?php foreach ($users as $user): ?>
                  <tr>
                    <td><?= e($user['nome']) ?></td>
                    <td><?= e($user['email']) ?></td>
                    <td><?= e($user['tipo']) ?></td>
                    <td><?= e($user['status']) ?></td>
                    <td><?= e(date('d/m/Y H:i', strtotime($user['created_at']))) ?></td>
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
