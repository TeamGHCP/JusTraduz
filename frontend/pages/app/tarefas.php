<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();

$type = current_user_type();
$userId = current_user_id();
$status = trim((string) ($_GET['status'] ?? ''));
$caseFilter = (int) ($_GET['case_id'] ?? 0);
$q = trim((string) ($_GET['q'] ?? ''));

function task_status_badge(?string $status): string
{
    return match ($status) {
        'concluida' => 'badge-success',
        'em_andamento' => 'badge-info',
        default => 'badge-warning',
    };
}

function task_priority_badge(?string $priority): string
{
    return $priority === 'alta' ? 'badge-warning' : 'badge-info';
}

function task_datetime(?string $value): string
{
    if (!$value) {
        return '-';
    }

    return date('d/m/Y H:i', strtotime($value));
}

function task_short(?string $text, int $limit = 130): string
{
    $text = trim((string) $text);
    if ($text === '') {
        return 'Sem descricao';
    }

    return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit - 3) . '...' : $text;
}

$where = [];
$params = [];

if ($type === 'cliente') {
    $where[] = 'c.cliente_id = ?';
    $params[] = $userId;
} elseif ($type === 'advogado') {
    $where[] = 'c.advogado_id = ?';
    $params[] = $userId;
} elseif ($type !== 'admin') {
    $where[] = '0 = 1';
}

if (in_array($status, ['pendente', 'em_andamento', 'concluida'], true)) {
    $where[] = 't.status = ?';
    $params[] = $status;
}

if ($caseFilter > 0) {
    $where[] = 'c.id = ?';
    $params[] = $caseFilter;
}

if ($q !== '') {
    $where[] = '(t.titulo LIKE ? OR t.descricao LIKE ? OR c.titulo LIKE ? OR cli.nome LIKE ? OR adv.nome LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}

$sql = "SELECT t.*, c.titulo AS caso, c.status AS case_status, c.prioridade,
               cli.nome AS cliente, adv.nome AS advogado
        FROM tasks t
        INNER JOIN cases c ON c.id = t.case_id
        INNER JOIN users cli ON cli.id = c.cliente_id
        LEFT JOIN users adv ON adv.id = c.advogado_id";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= " ORDER BY FIELD(t.status, 'pendente', 'em_andamento', 'concluida'),
                 FIELD(c.prioridade, 'alta', 'media', 'baixa'),
                 t.created_at DESC";

$tasks = fetch_all($pdo, $sql, $params);
$totalTasks = count($tasks);
$pendingCount = count(array_filter($tasks, static fn (array $task): bool => ($task['status'] ?? '') === 'pendente'));
$progressCount = count(array_filter($tasks, static fn (array $task): bool => ($task['status'] ?? '') === 'em_andamento'));
$doneCount = count(array_filter($tasks, static fn (array $task): bool => ($task['status'] ?? '') === 'concluida'));

if ($type === 'advogado') {
    $cases = fetch_all(
        $pdo,
        "SELECT c.id, c.titulo, cli.nome AS cliente
         FROM cases c
         INNER JOIN users cli ON cli.id = c.cliente_id
         WHERE c.advogado_id = ? AND c.status <> 'finalizado'
         ORDER BY FIELD(c.prioridade, 'alta', 'media', 'baixa'), c.created_at DESC",
        [$userId]
    );
} elseif ($type === 'admin') {
    $cases = fetch_all(
        $pdo,
        "SELECT c.id, c.titulo, cli.nome AS cliente
         FROM cases c
         INNER JOIN users cli ON cli.id = c.cliente_id
         WHERE c.status <> 'finalizado'
         ORDER BY FIELD(c.prioridade, 'alta', 'media', 'baixa'), c.created_at DESC"
    );
} elseif ($type === 'cliente') {
    $cases = fetch_all(
        $pdo,
        "SELECT c.id, c.titulo, cli.nome AS cliente
         FROM cases c
         INNER JOIN users cli ON cli.id = c.cliente_id
         WHERE c.cliente_id = ?
         ORDER BY c.created_at DESC",
        [$userId]
    );
} else {
    $cases = [];
}

$canManageTasks = in_array($type, ['advogado', 'admin'], true);
$hasTaskFilters = $q !== '' || $status !== '' || $caseFilter > 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta name="robots" content="noindex, nofollow">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tarefas | JusTraduz</title>
  <link rel="icon" href="assets/img/icon.ico" type="image/x-icon">
  <link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
  <link rel="manifest" href="site.webmanifest">
  <meta name="theme-color" content="#008f80">
  <meta name="application-name" content="JusTraduz">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="JusTraduz">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="msapplication-TileColor" content="#008f80">
  <link rel="stylesheet" href="assets/css/style.css?v=2026.07.05-style-cache-1">
  <script src="assets/js/pwa.js?v=2026.07.05-assets-v1" defer></script>
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar($type, 'tarefas.php'); ?>

    <main class="app-main tasks-page">
      <?php render_topbar('Tarefas', 'Acompanhe próximos passos por caso e prioridade.', current_user_name()); ?>

      <section class="task-summary-grid" aria-label="Resumo das tarefas">
        <article class="task-summary-card">
          <?= icon_svg('check') ?>
          <span>Resultado</span>
          <strong><?= e((string) $totalTasks) ?></strong>
        </article>
        <article class="task-summary-card">
          <?= icon_svg('help') ?>
          <span>Pendentes</span>
          <strong><?= e((string) $pendingCount) ?></strong>
        </article>
        <article class="task-summary-card">
          <?= icon_svg('case') ?>
          <span>Em andamento</span>
          <strong><?= e((string) $progressCount) ?></strong>
        </article>
        <article class="task-summary-card">
          <?= icon_svg('shield') ?>
          <span>Concluídas</span>
          <strong><?= e((string) $doneCount) ?></strong>
        </article>
      </section>

      <?php if ($canManageTasks): ?>
        <details class="task-tools task-create-panel">
          <summary>Nova tarefa <span><?= e((string) count($cases)) ?> casos ativos</span></summary>
          <form class="card auth-form task-create-card" action="<?= e(app_url('/backend/public/index.php?rota=/tasks/create')) ?>" method="post">
            <?= csrf_input() ?>
            <?php if (!$cases): ?>
              <p class="text-muted">Nenhum caso ativo disponível para criar tarefas.</p>
            <?php else: ?>
              <div class="form-grid">
                <div class="field">
                  <label for="case_id">Caso</label>
                  <select class="select" id="case_id" name="case_id" required>
                    <?php foreach ($cases as $case): ?>
                      <option value="<?= (int) $case['id'] ?>" <?= $caseFilter === (int) $case['id'] ? 'selected' : '' ?>>
                        <?= e('#' . $case['id'] . ' - ' . $case['titulo'] . ' (' . $case['cliente'] . ')') ?>
                      </option>
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
                <textarea class="textarea" id="descricao" name="descricao" placeholder="O que precisa acontecer, por que importa e qual é a próxima evidência esperada."></textarea>
              </div>
              <button class="btn btn-primary" type="submit"><?= icon_svg('check') ?> Criar tarefa</button>
            <?php endif; ?>
          </form>
        </details>
      <?php endif; ?>

      <details class="task-tools"<?= $hasTaskFilters ? ' open' : '' ?>>
        <summary>Filtros</summary>
        <form class="card admin-filter task-filter-grid" method="get">
          <div class="field">
            <label for="q">Busca</label>
            <input class="input" id="q" name="q" value="<?= e($q) ?>" placeholder="Tarefa, caso, cliente ou responsável">
          </div>
          <div class="field">
            <label for="status">Status</label>
            <select class="select" id="status" name="status">
              <option value="">Todos</option>
              <option value="pendente" <?= $status === 'pendente' ? 'selected' : '' ?>>Pendente</option>
              <option value="em_andamento" <?= $status === 'em_andamento' ? 'selected' : '' ?>>Em andamento</option>
              <option value="concluida" <?= $status === 'concluida' ? 'selected' : '' ?>>Concluída</option>
            </select>
          </div>
          <div class="field">
            <label for="case_filter">Caso</label>
            <select class="select" id="case_filter" name="case_id">
              <option value="0">Todos</option>
              <?php foreach ($cases as $case): ?>
                <option value="<?= (int) $case['id'] ?>" <?= $caseFilter === (int) $case['id'] ? 'selected' : '' ?>>
                  <?= e('#' . $case['id'] . ' - ' . $case['titulo']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-actions">
            <button class="btn btn-primary" type="submit">Filtrar</button>
            <a class="btn btn-outline" href="tarefas.php">Limpar</a>
          </div>
        </form>
      </details>

      <section class="dash-section task-list-section">
        <div class="dash-section-title task-list-title">
          <h2>Lista de tarefas</h2>
          <span class="badge badge-info"><?= e((string) $totalTasks) ?> registros</span>
        </div>

        <?php if (!$tasks): ?>
          <?= empty_state('Nenhuma tarefa encontrada para os filtros atuais.') ?>
        <?php else: ?>
          <div class="task-list">
            <?php foreach ($tasks as $task): ?>
              <article class="task-row">
                <div class="task-row-main">
                  <div class="task-row-head">
                    <h3><?= e($task['titulo']) ?></h3>
                    <div class="task-row-badges">
                      <span class="badge <?= e(task_status_badge($task['status'] ?? '')) ?>"><?= e(status_label($task['status'] ?? '')) ?></span>
                      <span class="badge <?= e(task_priority_badge($task['prioridade'] ?? '')) ?>"><?= e(status_label($task['prioridade'] ?? '')) ?></span>
                    </div>
                  </div>
                  <p><?= e(task_short($task['descricao'] ?? '')) ?></p>
                </div>
                <div class="task-row-footer">
                  <div class="task-row-meta">
                    <span><strong>Caso</strong> <?= e($task['caso']) ?></span>
                    <span><strong>Cliente</strong> <?= e($task['cliente']) ?></span>
                    <span><strong>Responsável</strong> <?= e($task['advogado'] ?? 'Sem advogado') ?></span>
                    <span><strong>Criada</strong> <?= e(task_datetime($task['created_at'] ?? '')) ?></span>
                  </div>
                  <div class="task-row-actions">
                    <?php if ($canManageTasks): ?>
                      <form class="action-form" action="<?= e(app_url('/backend/public/index.php?rota=/tasks/update')) ?>" method="post">
                        <?= csrf_input() ?>
                        <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
                        <select class="select select-sm" name="status" aria-label="Status da tarefa">
                          <option value="pendente" <?= ($task['status'] ?? '') === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                          <option value="em_andamento" <?= ($task['status'] ?? '') === 'em_andamento' ? 'selected' : '' ?>>Em andamento</option>
                          <option value="concluida" <?= ($task['status'] ?? '') === 'concluida' ? 'selected' : '' ?>>Concluída</option>
                        </select>
                        <button class="btn btn-soft btn-sm" type="submit">Salvar</button>
                      </form>
                    <?php endif; ?>
                    <a class="btn btn-outline btn-sm" href="chat.php?case_id=<?= (int) $task['case_id'] ?>"><?= icon_svg('chat') ?> Chat</a>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </main>
  </div>
  <?php render_vlibras(); ?>
</body>
</html>
