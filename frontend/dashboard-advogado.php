<?php
require_once __DIR__ . '/app/bootstrap.php';
require_role(['advogado']);

$userId = current_user_id();
$assignedCount = count_query($pdo, 'SELECT COUNT(*) FROM cases WHERE advogado_id = ?', [$userId]);
$openCount = count_query($pdo, "SELECT COUNT(*) FROM cases WHERE advogado_id IS NULL AND status = 'aberto'");
$taskCount = count_query($pdo, 'SELECT COUNT(*) FROM tasks t INNER JOIN cases c ON c.id = t.case_id WHERE c.advogado_id = ?', [$userId]);
$messageCount = count_query($pdo, 'SELECT COUNT(*) FROM messages m INNER JOIN cases c ON c.id = m.case_id WHERE c.advogado_id = ?', [$userId]);
$openCases = fetch_all($pdo, "SELECT c.id, c.titulo, c.prioridade, c.created_at, u.nome AS cliente FROM cases c INNER JOIN users u ON u.id = c.cliente_id WHERE c.advogado_id IS NULL AND c.status = 'aberto' ORDER BY c.created_at ASC LIMIT 8");
$assignedCases = fetch_all($pdo, 'SELECT c.id, c.titulo, c.status, c.created_at, u.nome AS cliente FROM cases c INNER JOIN users u ON u.id = c.cliente_id WHERE c.advogado_id = ? ORDER BY c.created_at DESC LIMIT 8', [$userId]);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard do advogado | JusTraduz</title>
  <link rel="icon" href="assets/img/logo.png">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar('advogado', 'dashboard-advogado.php'); ?>

    <main class="app-main">
      <?php render_topbar('Área do advogado', 'Gerencie solicitações, casos e conversas com clientes.', current_user_name()); ?>

      <section class="grid grid-4">
        <?= stat_card('Casos atribuídos', $assignedCount, 'case') ?>
        <?= stat_card('Solicitações abertas', $openCount, 'help') ?>
        <?= stat_card('Tarefas', $taskCount, 'file') ?>
        <?= stat_card('Mensagens', $messageCount, 'chat') ?>
      </section>

      <section class="dash-section">
        <div class="dash-section-title"><h2>Solicitações abertas</h2></div>
        <?php if (!$openCases): ?>
          <?= empty_state('Nenhuma solicitação aberta no momento.') ?>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Cliente</th><th>Assunto</th><th>Prioridade</th><th>Criada em</th><th>Ação</th></tr></thead>
              <tbody>
                <?php foreach ($openCases as $case): ?>
                  <tr>
                    <td><?= e($case['cliente']) ?></td>
                    <td><?= e($case['titulo']) ?></td>
                    <td><?= e($case['prioridade']) ?></td>
                    <td><?= e(date('d/m/Y H:i', strtotime($case['created_at']))) ?></td>
                    <td><a href="../backend/public/index.php?rota=/cases/accept&id=<?= (int) $case['id'] ?>">Aceitar</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>

      <section class="dash-section">
        <div class="dash-section-title"><h2>Meus casos</h2></div>
        <?php if (!$assignedCases): ?>
          <?= empty_state('Você ainda não possui casos atribuídos.') ?>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Caso</th><th>Cliente</th><th>Status</th><th>Criado em</th><th>Ação</th></tr></thead>
              <tbody>
                <?php foreach ($assignedCases as $case): ?>
                  <tr>
                    <td><?= e($case['titulo']) ?></td>
                    <td><?= e($case['cliente']) ?></td>
                    <td><?= e($case['status']) ?></td>
                    <td><?= e(date('d/m/Y H:i', strtotime($case['created_at']))) ?></td>
                    <td><a href="chat.php?case_id=<?= (int) $case['id'] ?>">Abrir chat</a></td>
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
