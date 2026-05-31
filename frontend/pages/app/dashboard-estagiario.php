<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_role(['estagiario']);

$messageCount = count_query($pdo, 'SELECT COUNT(*) FROM messages');
$documentCount = count_query($pdo, 'SELECT COUNT(*) FROM documents');
$openCaseCount = count_query($pdo, "SELECT COUNT(*) FROM cases WHERE status = 'aberto'");
$taskCount = count_query($pdo, "SELECT COUNT(*) FROM tasks WHERE status <> 'concluida'");
$recentCases = fetch_all(
    $pdo,
    'SELECT c.id, c.titulo, c.status, c.prioridade, c.created_at, cli.nome AS cliente, adv.nome AS advogado
     FROM cases c
     INNER JOIN users cli ON cli.id = c.cliente_id
     LEFT JOIN users adv ON adv.id = c.advogado_id
     ORDER BY c.created_at DESC
     LIMIT 8'
);
$recentTasks = fetch_all(
    $pdo,
    'SELECT t.id, t.titulo, t.status, c.id AS case_id, c.titulo AS caso
     FROM tasks t
     INNER JOIN cases c ON c.id = t.case_id
     ORDER BY t.created_at DESC
     LIMIT 8'
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard do estagiário | JusTraduz</title>
  <link rel="icon" href="assets/img/logo.png">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar('estagiario', 'dashboard-estagiario.php'); ?>

    <main class="app-main">
      <?php render_topbar('Área do estagiário', 'Auxilie em dúvidas simples e visualize informações sem alterar dados críticos.', current_user_name()); ?>

      <section class="grid grid-4">
        <?= stat_card('Casos abertos', $openCaseCount, 'case') ?>
        <?= stat_card('Documentos', $documentCount, 'file') ?>
        <?= stat_card('Mensagens', $messageCount, 'chat') ?>
        <?= stat_card('Tarefas ativas', $taskCount, 'check') ?>
      </section>

      <section class="dash-section">
        <div class="dash-section-title">
          <h2>Solicitações recentes</h2>
          <a class="btn btn-soft btn-sm" href="acompanhar-solicitacoes.php">Ver todas</a>
        </div>
        <?php if (!$recentCases): ?>
          <?= empty_state('Nenhuma solicitação cadastrada.') ?>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Caso</th><th>Cliente</th><th>Advogado</th><th>Prioridade</th><th>Status</th><th>Ação</th></tr></thead>
              <tbody>
                <?php foreach ($recentCases as $case): ?>
                  <tr>
                    <td><?= e($case['titulo']) ?></td>
                    <td><?= e($case['cliente']) ?></td>
                    <td><?= e($case['advogado'] ?? 'Aguardando') ?></td>
                    <td><?= e($case['prioridade']) ?></td>
                    <td><?= e($case['status']) ?></td>
                    <td><a href="chat.php?case_id=<?= (int) $case['id'] ?>">Chat</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>

      <section class="dash-section">
        <div class="dash-section-title">
          <h2>Tarefas recentes</h2>
          <a class="btn btn-soft btn-sm" href="tarefas.php">Ver tarefas</a>
        </div>
        <?php if (!$recentTasks): ?>
          <?= empty_state('Nenhuma tarefa cadastrada.') ?>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Tarefa</th><th>Caso</th><th>Status</th><th>Ação</th></tr></thead>
              <tbody>
                <?php foreach ($recentTasks as $task): ?>
                  <tr>
                    <td><?= e($task['titulo']) ?></td>
                    <td><?= e($task['caso']) ?></td>
                    <td><?= e($task['status']) ?></td>
                    <td><a href="tarefas.php?case_id=<?= (int) $task['case_id'] ?>">Abrir</a></td>
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
