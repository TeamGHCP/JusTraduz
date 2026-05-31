<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();

$type = current_user_type();
$userId = current_user_id();
$status = $_GET['status'] ?? '';
$caseFilter = (int) ($_GET['case_id'] ?? 0);
$where = [];
$params = [];

if ($type === 'cliente') {
    $where[] = 'c.cliente_id = ?';
    $params[] = $userId;
} elseif ($type === 'advogado') {
    $where[] = 'c.advogado_id = ?';
    $params[] = $userId;
}

if (in_array($status, ['pendente', 'em_andamento', 'concluida'], true)) {
    $where[] = 't.status = ?';
    $params[] = $status;
}

if ($caseFilter > 0) {
    $where[] = 'c.id = ?';
    $params[] = $caseFilter;
}

$sql = 'SELECT t.*, c.titulo AS caso, c.status AS case_status, cli.nome AS cliente, adv.nome AS advogado
        FROM tasks t
        INNER JOIN cases c ON c.id = t.case_id
        INNER JOIN users cli ON cli.id = c.cliente_id
        LEFT JOIN users adv ON adv.id = c.advogado_id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY t.created_at DESC';

$tasks = fetch_all($pdo, $sql, $params);

if ($type === 'advogado') {
    $cases = fetch_all(
        $pdo,
        "SELECT c.id, c.titulo, cli.nome AS cliente
         FROM cases c
         INNER JOIN users cli ON cli.id = c.cliente_id
         WHERE c.advogado_id = ? AND c.status <> 'finalizado'
         ORDER BY c.created_at DESC",
        [$userId]
    );
} elseif (in_array($type, ['admin', 'estagiario'], true)) {
    $cases = fetch_all(
        $pdo,
        "SELECT c.id, c.titulo, cli.nome AS cliente
         FROM cases c
         INNER JOIN users cli ON cli.id = c.cliente_id
         WHERE c.status <> 'finalizado'
         ORDER BY c.created_at DESC"
    );
} else {
    $cases = [];
}

$canManageTasks = $type !== 'cliente';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tarefas | JusTraduz</title>
  <link rel="icon" href="assets/img/logo.png">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar($type, 'tarefas.php'); ?>

    <main class="app-main">
      <?php render_topbar('Tarefas', 'Organize próximos passos dos casos e acompanhe o andamento.', current_user_name()); ?>

      <?php if ($canManageTasks): ?>
        <form class="card auth-form" action="<?= e(app_url('/backend/public/index.php?rota=/tasks/create')) ?>" method="post">
          <?= csrf_input() ?>
          <div class="dash-section-title">
            <h2>Nova tarefa</h2>
            <span class="badge badge-info"><?= e((string) count($cases)) ?> casos ativos</span>
          </div>
          <?php if (!$cases): ?>
            <p class="text-muted">Nenhum caso ativo disponível para criar tarefas.</p>
          <?php else: ?>
            <div class="form-grid">
              <div class="field">
                <label for="case_id">Caso</label>
                <select class="select" id="case_id" name="case_id" required>
                  <?php foreach ($cases as $case): ?>
                    <option value="<?= (int) $case['id'] ?>" <?= $caseFilter === (int) $case['id'] ? 'selected' : '' ?>><?= e('#' . $case['id'] . ' - ' . $case['titulo'] . ' (' . $case['cliente'] . ')') ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="field">
                <label for="titulo">Título</label>
                <input class="input" id="titulo" name="titulo" required>
              </div>
            </div>
            <div class="field">
              <label for="descricao">Descrição</label>
              <textarea class="textarea" id="descricao" name="descricao"></textarea>
            </div>
            <button class="btn btn-primary" type="submit"><?= icon_svg('check') ?> Criar tarefa</button>
          <?php endif; ?>
        </form>
      <?php endif; ?>

      <section class="dash-section">
        <form class="card admin-filter" method="get">
          <?php if ($caseFilter > 0): ?><input type="hidden" name="case_id" value="<?= (int) $caseFilter ?>"><?php endif; ?>
          <div class="field">
            <label for="status">Status</label>
            <select class="select" id="status" name="status">
              <option value="">Todos</option>
              <option value="pendente" <?= $status === 'pendente' ? 'selected' : '' ?>>Pendente</option>
              <option value="em_andamento" <?= $status === 'em_andamento' ? 'selected' : '' ?>>Em andamento</option>
              <option value="concluida" <?= $status === 'concluida' ? 'selected' : '' ?>>Concluída</option>
            </select>
          </div>
          <div class="form-actions">
            <button class="btn btn-primary" type="submit">Filtrar</button>
            <a class="btn btn-outline" href="<?= $caseFilter > 0 ? 'tarefas.php?case_id=' . (int) $caseFilter : 'tarefas.php' ?>">Limpar</a>
          </div>
        </form>
      </section>

      <section class="dash-section">
        <div class="dash-section-title">
          <h2>Lista de tarefas</h2>
          <span class="badge badge-info"><?= e((string) count($tasks)) ?> registros</span>
        </div>

        <?php if (!$tasks): ?>
          <?= empty_state('Nenhuma tarefa encontrada.') ?>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Tarefa</th><th>Caso</th><th>Cliente</th><th>Responsável</th><th>Status</th><th>Ação</th></tr></thead>
              <tbody>
                <?php foreach ($tasks as $task): ?>
                  <tr>
                    <td><strong><?= e($task['titulo']) ?></strong><span class="table-subtext"><?= e($task['descricao'] ?: 'Sem descrição') ?></span></td>
                    <td><?= e($task['caso']) ?><span class="table-subtext"><?= e($task['case_status']) ?></span></td>
                    <td><?= e($task['cliente']) ?></td>
                    <td><?= e($task['advogado'] ?? 'Sem advogado') ?></td>
                    <td><span class="badge <?= $task['status'] === 'concluida' ? 'badge-success' : 'badge-warning' ?>"><?= e($task['status']) ?></span></td>
                    <td>
                      <?php if ($canManageTasks): ?>
                        <form class="action-form" action="<?= e(app_url('/backend/public/index.php?rota=/tasks/update')) ?>" method="post">
                          <?= csrf_input() ?>
                          <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
                          <select class="select select-sm" name="status">
                            <option value="pendente" <?= $task['status'] === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                            <option value="em_andamento" <?= $task['status'] === 'em_andamento' ? 'selected' : '' ?>>Em andamento</option>
                            <option value="concluida" <?= $task['status'] === 'concluida' ? 'selected' : '' ?>>Concluída</option>
                          </select>
                          <button class="btn btn-soft btn-sm" type="submit">Salvar</button>
                          <a class="btn btn-outline btn-sm" href="chat.php?case_id=<?= (int) $task['case_id'] ?>">Chat</a>
                        </form>
                      <?php else: ?>
                        <a class="btn btn-outline btn-sm" href="chat.php?case_id=<?= (int) $task['case_id'] ?>">Chat</a>
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
