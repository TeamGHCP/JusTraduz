<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_role(['admin']);

$status = trim((string) ($_GET['status'] ?? ''));
$priority = trim((string) ($_GET['prioridade'] ?? ''));
$responsible = trim((string) ($_GET['responsavel'] ?? ''));
$scope = trim((string) ($_GET['scope'] ?? ''));
$q = trim((string) ($_GET['q'] ?? ''));

function admin_cases_has_document_id(PDO $pdo): bool
{
    static $hasColumn = null;
    if ($hasColumn !== null) {
        return $hasColumn;
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM cases WHERE Field = 'document_id'");
    $hasColumn = (bool) $stmt->fetch();
    return $hasColumn;
}

function admin_case_status_badge_class(string $status): string
{
    return match ($status) {
        'finalizado' => 'badge-success',
        'em_andamento' => 'badge-info',
        default => 'badge-warning',
    };
}

function admin_case_priority_badge_class(string $priority): string
{
    return $priority === 'alta' ? 'badge-warning' : 'badge-info';
}

$hasDocumentColumn = admin_cases_has_document_id($pdo);
$documentSelect = $hasDocumentColumn ? ', c.document_id, d.nome_arquivo AS document_name' : ', NULL AS document_id, NULL AS document_name';
$documentJoin = $hasDocumentColumn ? ' LEFT JOIN documents d ON d.id = c.document_id' : '';
$where = [];
$params = [];

if (in_array($status, ['aberto', 'em_andamento', 'finalizado'], true)) {
    $where[] = 'c.status = ?';
    $params[] = $status;
}

if (in_array($priority, ['baixa', 'media', 'alta'], true)) {
    $where[] = 'c.prioridade = ?';
    $params[] = $priority;
}

if ($responsible === 'com') {
    $where[] = 'c.advogado_id IS NOT NULL';
} elseif ($responsible === 'sem') {
    $where[] = 'c.advogado_id IS NULL';
}

if ($scope === 'criticas') {
    $where[] = "(c.status <> 'finalizado' AND (c.prioridade = 'alta' OR c.advogado_id IS NULL OR c.created_at <= DATE_SUB(NOW(), INTERVAL 2 DAY)))";
}

if ($q !== '') {
    $searchColumns = ['c.titulo', 'c.descricao', 'cli.nome', 'adv.nome'];
    if ($hasDocumentColumn) {
        $searchColumns[] = 'd.nome_arquivo';
    }

    $where[] = '(' . implode(' LIKE ? OR ', $searchColumns) . ' LIKE ?)';
    $like = '%' . $q . '%';
    foreach ($searchColumns as $_column) {
        $params[] = $like;
    }
}

$sql = "SELECT c.id, c.titulo, c.descricao, c.status, c.prioridade, c.created_at, c.advogado_id,
               cli.nome AS cliente, adv.nome AS advogado,
               (SELECT COUNT(*) FROM messages m WHERE m.case_id = c.id) AS message_count,
               (SELECT MAX(m.created_at) FROM messages m WHERE m.case_id = c.id) AS last_message_at,
               (SELECT COUNT(*) FROM tasks t WHERE t.case_id = c.id) AS task_count,
               (SELECT COUNT(*) FROM appointments a WHERE a.case_id = c.id AND a.status <> 'cancelado') AS appointment_count
               $documentSelect
        FROM cases c
        INNER JOIN users cli ON cli.id = c.cliente_id
        LEFT JOIN users adv ON adv.id = c.advogado_id
        $documentJoin";

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= " ORDER BY FIELD(c.prioridade, 'alta', 'media', 'baixa'), FIELD(c.status, 'aberto', 'em_andamento', 'finalizado'), c.created_at DESC";

$cases = fetch_all($pdo, $sql, $params);
$lawyers = fetch_all($pdo, "SELECT id, nome FROM users WHERE tipo = 'advogado' AND status = 'ativo' AND oab_verificado = TRUE ORDER BY nome");

$openCount = count_query($pdo, "SELECT COUNT(*) FROM cases WHERE status = 'aberto'");
$progressCount = count_query($pdo, "SELECT COUNT(*) FROM cases WHERE status = 'em_andamento'");
$criticalCount = count_query($pdo, "SELECT COUNT(*) FROM cases WHERE status <> 'finalizado' AND prioridade = 'alta'");
$unassignedCount = count_query($pdo, "SELECT COUNT(*) FROM cases WHERE status <> 'finalizado' AND advogado_id IS NULL");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta name="robots" content="noindex, nofollow">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Solicitações | Admin JusTraduz</title>
  <link rel="icon" href="../assets/img/icon.ico" type="image/x-icon">
  <link rel="apple-touch-icon" href="../assets/img/apple-touch-icon.png">
  <link rel="manifest" href="../site.webmanifest">
  <meta name="theme-color" content="#008f80">
  <meta name="application-name" content="JusTraduz">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="JusTraduz">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="msapplication-TileColor" content="#008f80">
  <link rel="stylesheet" href="../assets/css/style.css?v=global-responsive-20260628-2">
  <script src="../assets/js/pwa.js" defer></script>
</head>
<body>
  <div class="app-shell admin-shell">
    <?php render_sidebar('admin', 'solicitacoes.php', true); ?>

    <main class="app-main">
      <?php render_topbar('Solicitações', 'Fila de ajuda jurídica com prioridade, responsável, documento e chat.', current_user_name()); ?>

      <section class="grid grid-4">
        <?= stat_card('Abertas', $openCount, 'help') ?>
        <?= stat_card('Em andamento', $progressCount, 'case') ?>
        <?= stat_card('Prioridade alta', $criticalCount, 'shield') ?>
        <?= stat_card('Sem responsável', $unassignedCount, 'users') ?>
      </section>

      <form class="card admin-filter admin-filter-requests" method="get">
        <div class="field">
          <label for="q">Busca</label>
          <input class="input" id="q" name="q" value="<?= e($q) ?>" placeholder="Caso, cliente, advogado ou documento">
        </div>
        <div class="field">
          <label for="status">Status</label>
          <select class="select" id="status" name="status">
            <option value="">Todos</option>
            <option value="aberto" <?= $status === 'aberto' ? 'selected' : '' ?>>Aberto</option>
            <option value="em_andamento" <?= $status === 'em_andamento' ? 'selected' : '' ?>>Em andamento</option>
            <option value="finalizado" <?= $status === 'finalizado' ? 'selected' : '' ?>>Finalizado</option>
          </select>
        </div>
        <div class="field">
          <label for="prioridade">Prioridade</label>
          <select class="select" id="prioridade" name="prioridade">
            <option value="">Todas</option>
            <option value="alta" <?= $priority === 'alta' ? 'selected' : '' ?>>Alta</option>
            <option value="media" <?= $priority === 'media' ? 'selected' : '' ?>>Media</option>
            <option value="baixa" <?= $priority === 'baixa' ? 'selected' : '' ?>>Baixa</option>
          </select>
        </div>
        <div class="field">
          <label for="responsavel">Responsável</label>
          <select class="select" id="responsavel" name="responsavel">
            <option value="">Todos</option>
            <option value="sem" <?= $responsible === 'sem' ? 'selected' : '' ?>>Sem responsável</option>
            <option value="com" <?= $responsible === 'com' ? 'selected' : '' ?>>Com responsável</option>
          </select>
        </div>
        <div class="field">
          <label for="scope">Visao</label>
          <select class="select" id="scope" name="scope">
            <option value="">Completa</option>
            <option value="criticas" <?= $scope === 'criticas' ? 'selected' : '' ?>>Criticas</option>
          </select>
        </div>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit">Filtrar</button>
          <a class="btn btn-outline" href="solicitacoes.php">Limpar</a>
        </div>
      </form>

      <?php if ($unassignedCount > 0): ?>
        <div class="alert is-visible alert-info">Há <?= e((string) $unassignedCount) ?> caso(s) sem responsável. Isso é gargalo operacional, não detalhe visual.</div>
      <?php endif; ?>

      <section class="dash-section">
        <div class="dash-section-title">
          <h2>Fila administrativa</h2>
          <span class="badge badge-info"><?= e((string) count($cases)) ?> registros</span>
        </div>

        <?php if (!$cases): ?>
          <?= empty_state('Nenhuma solicitação encontrada para os filtros selecionados.') ?>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table admin-requests-table">
              <thead>
                <tr>
                  <th>Caso</th>
                  <th>Cliente</th>
                  <th>Responsável</th>
                  <th>Documento</th>
                  <th>Status</th>
                  <th>Operação</th>
                  <th>Acao</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($cases as $case): ?>
                  <tr>
                    <td>
                      <strong><?= e($case['titulo']) ?></strong>
                      <span class="table-subtext"><?= e($case['descricao'] ?: 'Sem descricao') ?></span>
                    </td>
                    <td><?= e($case['cliente']) ?></td>
                    <td><?= e($case['advogado'] ?? 'Aguardando responsável') ?></td>
                    <td>
                      <?php if (!empty($case['document_id'])): ?>
                        <a href="../visualizar-documento.php?id=<?= (int) $case['document_id'] ?>"><?= e($case['document_name'] ?? 'Documento') ?></a>
                      <?php else: ?>
                        <span class="text-muted">Sem documento</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <span class="badge <?= e(admin_case_priority_badge_class((string) $case['prioridade'])) ?>"><?= e((string) $case['prioridade']) ?></span>
                      <span class="badge <?= e(admin_case_status_badge_class((string) $case['status'])) ?>"><?= e(status_label($case['status'] ?? '')) ?></span>
                    </td>
                    <td>
                      <strong><?= e((string) (int) $case['message_count']) ?> msg</strong>
                      <span class="table-subtext"><?= e((string) (int) $case['task_count']) ?> tarefas | <?= e((string) (int) $case['appointment_count']) ?> agenda</span>
                    </td>
                    <td>
                      <form class="action-form action-form-stack" action="<?= e(app_url('/backend/public/index.php?rota=/admin/cases/update')) ?>" method="post">
                        <?= csrf_input() ?>
                        <input type="hidden" name="case_id" value="<?= (int) $case['id'] ?>">
                        <select class="select select-sm" name="advogado_id" aria-label="Advogado responsável">
                          <option value="">Sem advogado</option>
                          <?php foreach ($lawyers as $lawyer): ?>
                            <option value="<?= (int) $lawyer['id'] ?>" <?= (int) ($case['advogado_id'] ?? 0) === (int) $lawyer['id'] ? 'selected' : '' ?>><?= e($lawyer['nome']) ?></option>
                          <?php endforeach; ?>
                        </select>
                        <select class="select select-sm" name="prioridade" aria-label="Prioridade">
                          <?php foreach (['baixa' => 'Baixa', 'media' => 'Media', 'alta' => 'Alta'] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $case['prioridade'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                          <?php endforeach; ?>
                        </select>
                        <select class="select select-sm" name="status" aria-label="Status">
                          <?php foreach (['aberto' => 'Aberto', 'em_andamento' => 'Em andamento', 'finalizado' => 'Finalizado'] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $case['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                          <?php endforeach; ?>
                        </select>
                        <div class="review-actions">
                          <button class="btn btn-soft btn-sm" type="submit">Salvar</button>
                          <a class="btn btn-outline btn-sm" href="../chat.php?case_id=<?= (int) $case['id'] ?>">Chat</a>
                        </div>
                      </form>
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
  <?php render_vlibras(); ?>
</body>
</html>
