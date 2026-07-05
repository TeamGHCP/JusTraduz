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
  <meta name="robots" content="noindex, nofollow">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Solicitações | JusTraduz</title>
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
    <?php render_sidebar($type, 'acompanhar-solicitacoes.php'); ?>

    <main class="app-main cases-page">
      <?php render_topbar('Acompanhar solicitações', 'Veja o que precisa de atenção e abra o atendimento certo.', current_user_name()); ?>

      <section class="case-summary-strip" aria-label="Resumo das solicitações">
        <strong><?= e((string) $total) ?> caso<?= $total === 1 ? '' : 's' ?></strong>
        <span><?= e((string) $openCount) ?> aberto<?= $openCount === 1 ? '' : 's' ?></span>
        <span><?= e((string) $progressCount) ?> em andamento</span>
        <span><?= e((string) $highCount) ?> prioridade alta</span>
      </section>

      <form class="card admin-filter case-filter case-filter-compact" method="get">
        <div class="field">
          <label for="q">Busca</label>
          <input class="input" id="q" name="q" value="<?= e($q) ?>" placeholder="Caso, descrição, cliente ou advogado">
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
            <option value="media" <?= $priority === 'media' ? 'selected' : '' ?>>Média</option>
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
            <a class="btn btn-soft" href="solicitar-ajuda.php"><?= icon_svg('help') ?> Nova solicitação</a>
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
          <?= empty_state('Nenhuma solicitação encontrada para os filtros atuais.') ?>
        <?php else: ?>
          <div class="case-board">
            <?php foreach ($cases as $case): ?>
              <?php
                $caseId = (int) $case['id'];
                $isOpenForLawyer = $type === 'advogado' && empty($case['advogado_id']) && ($case['status'] ?? '') === 'aberto';
                $lastActivity = !empty($case['last_message_at']) ? (string) $case['last_message_at'] : (string) $case['created_at'];
              ?>
              <article class="case-card-rich">
                <div class="case-card-head">
                  <div>
                    <h3><?= e($case['titulo']) ?></h3>
                    <div class="case-card-badges">
                      <span class="badge <?= e(case_status_badge_class((string) $case['status'])) ?>"><?= e(status_label($case['status'] ?? '')) ?></span>
                      <span class="badge <?= e(case_priority_badge_class((string) $case['prioridade'])) ?>"><?= e(status_label($case['prioridade'] ?? '')) ?></span>
                    </div>
                  </div>
                  <?php if ($isOpenForLawyer): ?>
                    <form class="inline-form" action="<?= e(app_url('/backend/public/index.php?rota=/cases/accept')) ?>" method="post">
                      <?= csrf_input() ?>
                      <input type="hidden" name="case_id" value="<?= $caseId ?>">
                      <button class="btn btn-primary btn-sm" type="submit">Aceitar</button>
                    </form>
                  <?php else: ?>
                    <a class="btn btn-primary btn-sm" href="chat.php?case_id=<?= $caseId ?>"><?= icon_svg('chat') ?> Abrir chat</a>
                  <?php endif; ?>
                </div>

                <p><?= e(mb_substr((string) ($case['descricao'] ?? ''), 0, 150)) ?><?= mb_strlen((string) ($case['descricao'] ?? '')) > 150 ? '...' : '' ?></p>

                <div class="case-quick-meta">
                  <span><strong>Cliente:</strong> <?= e($case['cliente'] ?? '-') ?></span>
                  <span><strong>Responsável:</strong> <?= e($case['advogado'] ?? 'Aguardando') ?></span>
                  <span><strong>Última atividade:</strong> <?= e(date('d/m/Y H:i', strtotime($lastActivity))) ?></span>
                </div>

                <div class="case-card-foot">
                  <details class="case-more-actions">
                    <summary>Mais ações</summary>
                    <div class="case-actions">
                      <?php if (!$isOpenForLawyer): ?>
                        <a class="btn btn-outline btn-sm" href="tarefas.php?case_id=<?= $caseId ?>"><?= icon_svg('check') ?> Tarefas (<?= e((string) (int) $case['task_count']) ?>)</a>
                        <a class="btn btn-soft btn-sm" href="agenda.php"><?= icon_svg('calendar') ?> Agenda (<?= e((string) (int) $case['appointment_count']) ?>)</a>
                      <?php endif; ?>

                      <?php if (!empty($case['document_id'])): ?>
                        <a class="btn btn-outline btn-sm" href="visualizar-documento.php?id=<?= (int) $case['document_id'] ?>"><?= icon_svg('file') ?> Documento</a>
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
                            <button class="btn btn-outline btn-sm" type="submit">Salvar status</button>
                          <?php endif; ?>
                        </form>
                      <?php endif; ?>
                    </div>
                  </details>
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
