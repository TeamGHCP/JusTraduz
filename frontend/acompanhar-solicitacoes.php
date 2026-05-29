<?php
require_once __DIR__ . '/app/bootstrap.php';
require_login();

$type = current_user_type();
$userId = current_user_id();

if ($type === 'cliente') {
    $cases = fetch_all($pdo, 'SELECT c.*, u.nome AS advogado FROM cases c LEFT JOIN users u ON u.id = c.advogado_id WHERE c.cliente_id = ? ORDER BY c.created_at DESC', [$userId]);
} elseif ($type === 'advogado') {
    $cases = fetch_all($pdo, 'SELECT c.*, u.nome AS cliente FROM cases c INNER JOIN users u ON u.id = c.cliente_id WHERE c.advogado_id = ? OR c.advogado_id IS NULL ORDER BY c.created_at DESC', [$userId]);
} else {
    $cases = fetch_all($pdo, 'SELECT c.*, cli.nome AS cliente, adv.nome AS advogado FROM cases c INNER JOIN users cli ON cli.id = c.cliente_id LEFT JOIN users adv ON adv.id = c.advogado_id ORDER BY c.created_at DESC');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Solicitações | JusTraduz</title>
  <link rel="icon" href="assets/img/logo.png">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar($type, 'acompanhar-solicitacoes.php'); ?>

    <main class="app-main">
      <?php render_topbar('Acompanhar solicitações', 'Veja status e profissional responsável.', current_user_name()); ?>

      <?php if ($type === 'cliente'): ?>
        <div class="form-actions">
          <a class="btn btn-primary" href="solicitar-ajuda.php"><?= icon_svg('help') ?> Nova solicitação</a>
        </div>
      <?php endif; ?>

      <section class="dash-section">
        <?php if (!$cases): ?>
          <?= empty_state('Nenhuma solicitação cadastrada ainda.') ?>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Caso</th><th>Cliente</th><th>Advogado</th><th>Prioridade</th><th>Status</th><th>Ação</th></tr></thead>
              <tbody>
                <?php foreach ($cases as $case): ?>
                  <tr>
                    <td><?= e($case['titulo']) ?></td>
                    <td><?= e($case['cliente'] ?? current_user_name()) ?></td>
                    <td><?= e($case['advogado'] ?? 'Aguardando') ?></td>
                    <td><?= e($case['prioridade']) ?></td>
                    <td><?= e($case['status']) ?></td>
                    <td>
                      <?php if ($type === 'advogado' && empty($case['advogado_id'])): ?>
                        <a href="../backend/public/index.php?rota=/cases/accept&id=<?= (int) $case['id'] ?>">Aceitar</a>
                      <?php else: ?>
                        <a href="chat.php?case_id=<?= (int) $case['id'] ?>">Abrir chat</a>
                      <?php endif; ?>
                    </td>
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
