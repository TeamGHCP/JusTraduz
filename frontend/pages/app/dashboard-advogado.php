<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_role(['advogado']);

$userId = current_user_id();
$assignedCount = count_query($pdo, 'SELECT COUNT(*) FROM cases WHERE advogado_id = ?', [$userId]);
$openCount = count_query($pdo, "SELECT COUNT(*) FROM cases WHERE advogado_id IS NULL AND status = 'aberto'");
$taskCount = count_query($pdo, 'SELECT COUNT(*) FROM tasks t INNER JOIN cases c ON c.id = t.case_id WHERE c.advogado_id = ?', [$userId]);
$messageCount = count_query($pdo, 'SELECT COUNT(*) FROM messages m INNER JOIN cases c ON c.id = m.case_id WHERE c.advogado_id = ?', [$userId]);
$documentCount = count_query($pdo, 'SELECT COUNT(DISTINCT d.id) FROM documents d INNER JOIN cases c ON c.cliente_id = d.user_id WHERE c.advogado_id = ?', [$userId]);

$openCases = fetch_all($pdo, "SELECT c.id, c.titulo, c.prioridade, c.created_at, u.nome AS cliente FROM cases c INNER JOIN users u ON u.id = c.cliente_id WHERE c.advogado_id IS NULL AND c.status = 'aberto' ORDER BY c.created_at ASC LIMIT 8");
$assignedCases = fetch_all($pdo, 'SELECT c.id, c.titulo, c.status, c.created_at, u.nome AS cliente FROM cases c INNER JOIN users u ON u.id = c.cliente_id WHERE c.advogado_id = ? ORDER BY c.created_at DESC LIMIT 8', [$userId]);
$recentDocuments = fetch_all(
    $pdo,
    'SELECT DISTINCT d.id, d.nome_arquivo, d.tipo_arquivo, d.created_at, u.nome AS cliente
     FROM documents d
     INNER JOIN users u ON u.id = d.user_id
     INNER JOIN cases c ON c.cliente_id = d.user_id
     WHERE c.advogado_id = ?
     ORDER BY d.created_at DESC
     LIMIT 8',
    [$userId]
);
$tasks = fetch_all(
    $pdo,
    'SELECT t.titulo, t.status, t.created_at, c.titulo AS caso
     FROM tasks t
     INNER JOIN cases c ON c.id = t.case_id
     WHERE c.advogado_id = ?
     ORDER BY t.created_at DESC
     LIMIT 8',
    [$userId]
);
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
      <?php render_topbar('Área do advogado', 'Gerencie solicitações, documentos, tarefas e conversas com clientes.', current_user_name()); ?>

      <section class="grid grid-4">
        <?= stat_card('Casos atribuídos', $assignedCount, 'case') ?>
        <?= stat_card('Solicitações abertas', $openCount, 'help') ?>
        <?= stat_card('Documentos', $documentCount, 'file') ?>
        <?= stat_card('Mensagens', $messageCount, 'chat') ?>
      </section>

      <section class="dash-section">
        <div class="dash-section-title"><h2>Ações rápidas</h2></div>
        <div class="form-actions">
          <a class="btn btn-primary" href="acompanhar-solicitacoes.php"><?= icon_svg('case') ?> Ver casos</a>
          <a class="btn btn-soft" href="visualizar-documento.php"><?= icon_svg('file') ?> Documentos</a>
          <a class="btn btn-outline" href="chat.php"><?= icon_svg('chat') ?> Abrir chat</a>
        </div>
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
                    <td>
                      <form class="inline-form" action="<?= e(app_url('/backend/public/index.php?rota=/cases/accept')) ?>" method="post">
                        <?= csrf_input() ?>
                        <input type="hidden" name="case_id" value="<?= (int) $case['id'] ?>">
                        <button class="btn btn-primary btn-sm" type="submit">Aceitar</button>
                      </form>
                    </td>
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

      <section class="dash-section">
        <div class="dash-section-title">
          <h2>Documentos dos meus clientes</h2>
          <a class="btn btn-soft btn-sm" href="visualizar-documento.php">Ver todos</a>
        </div>
        <?php if (!$recentDocuments): ?>
          <?= empty_state('Nenhum documento disponível nos seus casos.') ?>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Cliente</th><th>Documento</th><th>Tipo</th><th>Enviado em</th><th>Ação</th></tr></thead>
              <tbody>
                <?php foreach ($recentDocuments as $document): ?>
                  <tr>
                    <td><?= e($document['cliente']) ?></td>
                    <td><?= e($document['nome_arquivo']) ?></td>
                    <td><?= e(strtoupper($document['tipo_arquivo'] ?? '')) ?></td>
                    <td><?= e(date('d/m/Y H:i', strtotime($document['created_at']))) ?></td>
                    <td><a href="visualizar-documento.php?id=<?= (int) $document['id'] ?>">Abrir documento</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>

      <section class="dash-section">
        <div class="dash-section-title">
          <h2>Tarefas</h2>
          <span class="badge badge-info"><?= e((string) $taskCount) ?> tarefas</span>
        </div>
        <?php if (!$tasks): ?>
          <?= empty_state('Nenhuma tarefa cadastrada para seus casos.') ?>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Tarefa</th><th>Caso</th><th>Status</th><th>Criada em</th></tr></thead>
              <tbody>
                <?php foreach ($tasks as $task): ?>
                  <tr>
                    <td><?= e($task['titulo']) ?></td>
                    <td><?= e($task['caso']) ?></td>
                    <td><?= e($task['status']) ?></td>
                    <td><?= e(date('d/m/Y H:i', strtotime($task['created_at']))) ?></td>
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
