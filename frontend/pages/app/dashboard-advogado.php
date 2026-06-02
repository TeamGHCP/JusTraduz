<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_role(['advogado']);

$userId = current_user_id();
$assignedCount = count_query($pdo, 'SELECT COUNT(*) FROM cases WHERE advogado_id = ?', [$userId]);
$openCount = count_query($pdo, "SELECT COUNT(*) FROM cases WHERE advogado_id IS NULL AND status = 'aberto'");
$highPriorityOpenCount = count_query($pdo, "SELECT COUNT(*) FROM cases WHERE advogado_id IS NULL AND status = 'aberto' AND prioridade = 'alta'");
$taskCount = count_query($pdo, 'SELECT COUNT(*) FROM tasks t INNER JOIN cases c ON c.id = t.case_id WHERE c.advogado_id = ? AND t.status <> "concluida"', [$userId]);
$documentCount = count_query($pdo, 'SELECT COUNT(DISTINCT d.id) FROM documents d INNER JOIN cases c ON c.cliente_id = d.user_id WHERE c.advogado_id = ?', [$userId]);

$openCases = fetch_all(
    $pdo,
    "SELECT c.id, c.titulo, c.prioridade, c.created_at, u.nome AS cliente
     FROM cases c
     INNER JOIN users u ON u.id = c.cliente_id
     WHERE c.advogado_id IS NULL AND c.status = 'aberto'
     ORDER BY FIELD(c.prioridade, 'alta', 'media', 'baixa'), c.created_at ASC
     LIMIT 8"
);
$assignedCases = fetch_all(
    $pdo,
    'SELECT c.id, c.titulo, c.status, c.prioridade, c.created_at, u.nome AS cliente
     FROM cases c
     INNER JOIN users u ON u.id = c.cliente_id
     WHERE c.advogado_id = ?
     ORDER BY c.created_at DESC
     LIMIT 8',
    [$userId]
);
$recentDocuments = fetch_all(
    $pdo,
    'SELECT DISTINCT d.id, d.nome_arquivo, d.tipo_arquivo, d.created_at, u.nome AS cliente, ar.id AS analysis_id
     FROM documents d
     INNER JOIN users u ON u.id = d.user_id
     INNER JOIN cases c ON c.cliente_id = d.user_id
     LEFT JOIN ai_results ar ON ar.document_id = d.id
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
     ORDER BY FIELD(t.status, "pendente", "em_andamento", "concluida"), t.created_at DESC
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
  <link rel="icon" href="assets/img/icon.ico" type="image/x-icon">
  <link rel="stylesheet" href="assets/css/style.css?v=theme-slow-3">
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar('advogado', 'dashboard-advogado.php'); ?>

    <main class="app-main">
      <?php render_topbar('Mesa do advogado', 'Priorize solicitações, revise documentos e avance atendimentos.', current_user_name()); ?>

      <section class="lawyer-command">
        <article class="command-card command-card-primary">
          <span class="badge badge-info">Fila de trabalho</span>
          <h2><?= e((string) $openCount) ?> solicitações aguardando responsável</h2>
          <p><?= e((string) $highPriorityOpenCount) ?> em prioridade alta. Aceite apenas casos que você consegue acompanhar.</p>
          <div class="form-actions">
            <a class="btn btn-primary" href="acompanhar-solicitacoes.php"><?= icon_svg('case') ?> Ver fila</a>
            <a class="btn btn-outline" href="agenda.php"><?= icon_svg('calendar') ?> Agenda</a>
          </div>
        </article>
        <article class="command-card">
          <span>Casos ativos</span>
          <strong><?= e((string) $assignedCount) ?></strong>
          <p>Atendimentos sob sua responsabilidade.</p>
        </article>
        <article class="command-card">
          <span>Tarefas abertas</span>
          <strong><?= e((string) $taskCount) ?></strong>
          <p>Próximos passos pendentes nos casos.</p>
        </article>
      </section>

      <section class="grid grid-4">
        <?= stat_card('Casos atribuídos', $assignedCount, 'case') ?>
        <?= stat_card('Solicitações abertas', $openCount, 'help') ?>
        <?= stat_card('Prioridade alta', $highPriorityOpenCount, 'shield') ?>
        <?= stat_card('Documentos', $documentCount, 'file') ?>
      </section>

      <section class="dash-section">
        <div class="dash-section-title">
          <h2>Solicitações abertas</h2>
          <span class="badge badge-warning">Ordenadas por prioridade</span>
        </div>
        <?php if (!$openCases): ?>
          <?= empty_state('Nenhuma solicitação aberta no momento.') ?>
        <?php else: ?>
          <div class="case-queue">
            <?php foreach ($openCases as $case): ?>
              <article class="case-card">
                <div>
                  <span class="badge <?= $case['prioridade'] === 'alta' ? 'badge-warning' : 'badge-info' ?>"><?= e(status_label($case['prioridade'])) ?></span>
                  <h3><?= e($case['titulo']) ?></h3>
                  <p><?= e($case['cliente']) ?> · <?= e(date('d/m/Y H:i', strtotime($case['created_at']))) ?></p>
                </div>
                <form class="inline-form" action="<?= e(app_url('/backend/public/index.php?rota=/cases/accept')) ?>" method="post">
                  <?= csrf_input() ?>
                  <input type="hidden" name="case_id" value="<?= (int) $case['id'] ?>">
                  <button class="btn btn-primary btn-sm" type="submit">Aceitar</button>
                </form>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <section class="grid grid-2 admin-panels">
        <article class="dash-section">
          <div class="dash-section-title"><h2>Meus casos</h2><a class="btn btn-soft btn-sm" href="acompanhar-solicitacoes.php">Ver todos</a></div>
          <?php if (!$assignedCases): ?>
            <?= empty_state('Você ainda não possui casos atribuídos.') ?>
          <?php else: ?>
            <div class="table-wrap">
              <table class="table compact-table">
                <thead><tr><th>Caso</th><th>Cliente</th><th>Status</th><th>Ação</th></tr></thead>
                <tbody>
                  <?php foreach ($assignedCases as $case): ?>
                    <tr>
                      <td><strong><?= e($case['titulo']) ?></strong><span><?= e(status_label($case['prioridade'])) ?></span></td>
                      <td><?= e($case['cliente']) ?></td>
                      <td><span class="badge badge-info"><?= e(status_label($case['status'] ?? '')) ?></span></td>
                      <td><a href="chat.php?case_id=<?= (int) $case['id'] ?>">Chat</a></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </article>

        <article class="dash-section">
          <div class="dash-section-title"><h2>Documentos recentes</h2><a class="btn btn-soft btn-sm" href="visualizar-documento.php">Ver todos</a></div>
          <?php if (!$recentDocuments): ?>
            <?= empty_state('Nenhum documento disponível nos seus casos.') ?>
          <?php else: ?>
            <div class="table-wrap">
              <table class="table compact-table">
                <thead><tr><th>Documento</th><th>Cliente</th><th>Análise</th><th>Ação</th></tr></thead>
                <tbody>
                  <?php foreach ($recentDocuments as $document): ?>
                    <tr>
                      <td><strong><?= e($document['nome_arquivo']) ?></strong><span><?= e(strtoupper($document['tipo_arquivo'] ?? '')) ?></span></td>
                      <td><?= e($document['cliente']) ?></td>
                      <td><span class="badge <?= !empty($document['analysis_id']) ? 'badge-success' : 'badge-warning' ?>"><?= !empty($document['analysis_id']) ? 'Gerada' : 'Pendente' ?></span></td>
                      <td><a href="visualizar-documento.php?id=<?= (int) $document['id'] ?>">Abrir</a></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </article>
      </section>

      <section class="dash-section">
        <div class="dash-section-title">
          <h2>Tarefas</h2>
          <span class="badge badge-info"><?= e((string) $taskCount) ?> abertas</span>
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
                    <td><span class="badge <?= $task['status'] === 'concluida' ? 'badge-success' : 'badge-warning' ?>"><?= e(status_label($task['status'] ?? '')) ?></span></td>
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
