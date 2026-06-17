<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();

$type = current_user_type();
$userId = current_user_id();
$status = trim((string) ($_GET['status'] ?? ''));
$priority = trim((string) ($_GET['prioridade'] ?? ''));
$scope = trim((string) ($_GET['scope'] ?? ''));
$q = trim((string) ($_GET['q'] ?? ''));

function app_cases_has_document_id(PDO $pdo): bool
{
    static $hasColumn = null;
    if ($hasColumn !== null) {
        return $hasColumn;
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM cases WHERE Field = 'document_id'");
    $hasColumn = (bool) $stmt->fetch();
    return $hasColumn;
}

function case_status_badge_class(string $status): string
{
    return match ($status) {
        'finalizado' => 'badge-success',
        'em_andamento' => 'badge-info',
        default => 'badge-warning',
    };
}

function case_priority_badge_class(string $priority): string
{
    return $priority === 'alta' ? 'badge-warning' : 'badge-info';
}

$hasDocumentColumn = app_cases_has_document_id($pdo);
$documentSelect = $hasDocumentColumn ? ', d.id AS document_id, d.nome_arquivo AS document_name' : ', NULL AS document_id, NULL AS document_name';
$documentJoin = $hasDocumentColumn ? ' LEFT JOIN documents d ON d.id = c.document_id' : '';
$where = [];
$params = [];

if ($type === 'cliente') {
    $where[] = 'c.cliente_id = ?';
    $params[] = $userId;
} elseif ($type === 'advogado') {
    if ($scope === 'abertos') {
        $where[] = 'c.advogado_id IS NULL';
    } elseif ($scope === 'meus') {
        $where[] = 'c.advogado_id = ?';
        $params[] = $userId;
    } else {
        $where[] = '(c.advogado_id = ? OR c.advogado_id IS NULL)';
        $params[] = $userId;
    }
} elseif ($type !== 'admin') {
    $where[] = '0 = 1';
}

if (in_array($status, ['aberto', 'em_andamento', 'finalizado'], true)) {
    $where[] = 'c.status = ?';
    $params[] = $status;
}

if (in_array($priority, ['baixa', 'media', 'alta'], true)) {
    $where[] = 'c.prioridade = ?';
    $params[] = $priority;
}

if ($q !== '') {
    $where[] = '(c.titulo LIKE ? OR c.descricao LIKE ? OR cli.nome LIKE ? OR adv.nome LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}

$sql = "SELECT c.id, c.titulo, c.descricao, c.status, c.prioridade, c.created_at, c.advogado_id,
               cli.nome AS cliente, adv.nome AS advogado,
               COUNT(DISTINCT m.id) AS message_count,
               MAX(m.created_at) AS last_message_at,
               COUNT(DISTINCT t.id) AS task_count,
               COUNT(DISTINCT a.id) AS appointment_count
               $documentSelect
        FROM cases c
        INNER JOIN users cli ON cli.id = c.cliente_id
        LEFT JOIN users adv ON adv.id = c.advogado_id
        $documentJoin
        LEFT JOIN messages m ON m.case_id = c.id
        LEFT JOIN tasks t ON t.case_id = c.id
        LEFT JOIN appointments a ON a.case_id = c.id AND a.status <> 'cancelado'";

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= " GROUP BY c.id, c.titulo, c.descricao, c.status, c.prioridade, c.created_at, c.advogado_id, cli.nome, adv.nome";
if ($hasDocumentColumn) {
    $sql .= ', d.id, d.nome_arquivo';
}
$sql .= " ORDER BY FIELD(c.prioridade, 'alta', 'media', 'baixa'), FIELD(c.status, 'aberto', 'em_andamento', 'finalizado'), c.created_at DESC";

$cases = fetch_all($pdo, $sql, $params);
$total = count($cases);
$openCount = count(array_filter($cases, static fn (array $case): bool => ($case['status'] ?? '') === 'aberto'));
$progressCount = count(array_filter($cases, static fn (array $case): bool => ($case['status'] ?? '') === 'em_andamento'));
$highCount = count(array_filter($cases, static fn (array $case): bool => ($case['prioridade'] ?? '') === 'alta'));
$unassignedCount = count(array_filter($cases, static fn (array $case): bool => empty($case['advogado_id']) && ($case['status'] ?? '') !== 'finalizado'));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Solicitações | JusTraduz</title>
  <link rel="icon" href="assets/img/icon.ico" type="image/x-icon">
  <link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
  <link rel="manifest" href="site.webmanifest">
  <meta name="theme-color" content="#008f80">
  <link rel="stylesheet" href="assets/css/style.css?v=sidebar-open-button-1">
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar($type, 'acompanhar-solicitacoes.php'); ?>

    <main class="app-main">
      <?php render_topbar('Acompanhar solicitações', 'Fila de casos, responsaveis, chat e proximas acoes.', current_user_name()); ?>

      <section class="grid grid-4">
        <?= stat_card('Resultado filtrado', $total, 'case') ?>
        <?= stat_card('Abertas', $openCount, 'help') ?>
        <?= stat_card('Em andamento', $progressCount, 'chat') ?>
        <?= stat_card('Prioridade alta', $highCount, 'shield') ?>
      </section>

      <form class="card admin-filter case-filter" method="get">
        <div class="field">
          <label for="q">Busca</label>
          <input class="input" id="q" name="q" value="<?= e($q) ?>" placeholder="Caso, descricao, cliente ou advogado">
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
        <?php if ($type === 'advogado'): ?>
          <div class="field">
            <label for="scope">Fila</label>
            <select class="select" id="scope" name="scope">
              <option value="">Todos acessíveis</option>
              <option value="meus" <?= $scope === 'meus' ? 'selected' : '' ?>>Meus casos</option>
              <option value="abertos" <?= $scope === 'abertos' ? 'selected' : '' ?>>Abertos</option>
            </select>
          </div>
        <?php endif; ?>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit">Filtrar</button>
          <a class="btn btn-outline" href="acompanhar-solicitacoes.php">Limpar</a>
          <?php if ($type === 'cliente'): ?>
            <a class="btn btn-soft" href="solicitar-ajuda.php"><?= icon_svg('help') ?> Nova solicitacao</a>
          <?php endif; ?>
        </div>
      </form>

      <?php if ($unassignedCount > 0 && in_array($type, ['advogado', 'admin'], true)): ?>
        <div class="alert is-visible alert-info">Existem <?= e((string) $unassignedCount) ?> caso(s) sem responsável. Priorize aceitar ou atribuir antes de abrir novas frentes.</div>
      <?php endif; ?>

      <section class="dash-section">
        <div class="dash-section-title">
          <h2>Casos</h2>
          <span class="badge badge-info"><?= e((string) $total) ?> registros</span>
        </div>

        <?php if (!$cases): ?>
          <?= empty_state('Nenhuma solicitacao encontrada para os filtros atuais.') ?>
        <?php else: ?>
          <div class="case-board">
            <?php foreach ($cases as $case): ?>
              <?php
                $caseId = (int) $case['id'];
                $isOpenForLawyer = $type === 'advogado' && empty($case['advogado_id']) && ($case['status'] ?? '') === 'aberto';
              ?>
              <article class="case-card-rich">
                <div class="case-card-head">
                  <div>
                    <span class="badge <?= e(case_priority_badge_class((string) $case['prioridade'])) ?>"><?= e((string) $case['prioridade']) ?></span>
                    <h3><?= e($case['titulo']) ?></h3>
                  </div>
                  <span class="badge <?= e(case_status_badge_class((string) $case['status'])) ?>"><?= e(status_label($case['status'] ?? '')) ?></span>
                </div>

                <p><?= e(mb_substr((string) ($case['descricao'] ?? ''), 0, 220)) ?><?= mb_strlen((string) ($case['descricao'] ?? '')) > 220 ? '...' : '' ?></p>

                <div class="case-meta-grid">
                  <div><span>Cliente</span><strong><?= e($case['cliente'] ?? '-') ?></strong></div>
                  <div><span>Responsável</span><strong><?= e($case['advogado'] ?? 'Aguardando') ?></strong></div>
                  <div><span>Mensagens</span><strong><?= e((string) (int) $case['message_count']) ?></strong></div>
                  <div><span>Tarefas</span><strong><?= e((string) (int) $case['task_count']) ?></strong></div>
                </div>

                <?php if (!empty($case['document_id'])): ?>
                  <a class="case-linked-doc" href="visualizar-documento.php?id=<?= (int) $case['document_id'] ?>">
                    <?= icon_svg('file') ?>
                    <span><?= e($case['document_name'] ?? 'Documento relacionado') ?></span>
                  </a>
                <?php endif; ?>

                <div class="case-card-foot">
                  <span class="text-muted">
                    Criado em <?= e(date('d/m/Y H:i', strtotime((string) $case['created_at']))) ?>
                    <?php if (!empty($case['last_message_at'])): ?> | Ultima msg <?= e(date('d/m/Y H:i', strtotime((string) $case['last_message_at']))) ?><?php endif; ?>
                  </span>
                  <div class="case-actions">
                    <?php if ($isOpenForLawyer): ?>
                      <form class="inline-form" action="<?= e(app_url('/backend/public/index.php?rota=/cases/accept')) ?>" method="post">
                        <?= csrf_input() ?>
                        <input type="hidden" name="case_id" value="<?= $caseId ?>">
                        <button class="btn btn-primary btn-sm" type="submit">Aceitar</button>
                      </form>
                    <?php else: ?>
                      <a class="btn btn-primary btn-sm" href="chat.php?case_id=<?= $caseId ?>"><?= icon_svg('chat') ?> Chat</a>
                      <a class="btn btn-outline btn-sm" href="tarefas.php?case_id=<?= $caseId ?>"><?= icon_svg('check') ?> Tarefas</a>
                      <a class="btn btn-soft btn-sm" href="agenda.php"><?= icon_svg('calendar') ?> Agenda</a>
                    <?php endif; ?>

                    <?php if (($case['status'] ?? '') !== 'finalizado' && !$isOpenForLawyer): ?>
                      <form class="inline-form" action="<?= e(app_url('/backend/public/index.php?rota=/cases/status')) ?>" method="post">
                        <?= csrf_input() ?>
                        <input type="hidden" name="case_id" value="<?= $caseId ?>">
                        <?php if ($type === 'cliente'): ?>
                          <input type="hidden" name="status" value="finalizado">
                          <button class="btn btn-outline btn-sm" type="submit">Finalizar</button>
                        <?php elseif (in_array($type, ['advogado', 'admin'], true)): ?>
                          <select class="select select-sm" name="status" aria-label="Status do caso">
                            <option value="aberto" <?= ($case['status'] ?? '') === 'aberto' ? 'selected' : '' ?>>Aberto</option>
                            <option value="em_andamento" <?= ($case['status'] ?? '') === 'em_andamento' ? 'selected' : '' ?>>Em andamento</option>
                            <option value="finalizado" <?= ($case['status'] ?? '') === 'finalizado' ? 'selected' : '' ?>>Finalizado</option>
                          </select>
                          <button class="btn btn-outline btn-sm" type="submit">Salvar</button>
                        <?php endif; ?>
                      </form>
                    <?php endif; ?>
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
