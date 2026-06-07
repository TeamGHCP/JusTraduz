<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_role(['admin']);

$q = trim((string) ($_GET['q'] ?? ''));
$analysis = $_GET['analysis'] ?? '';
$type = strtolower(trim((string) ($_GET['type'] ?? '')));
$dateStart = trim((string) ($_GET['date_start'] ?? ''));
$dateEnd = trim((string) ($_GET['date_end'] ?? ''));
$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(d.nome_arquivo LIKE ? OR u.nome LIKE ? OR u.email LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}

if (in_array($analysis, ['analisado', 'pendente'], true)) {
    $where[] = $analysis === 'analisado' ? 'ar.id IS NOT NULL' : 'ar.id IS NULL';
}

if (in_array($type, ['pdf', 'png', 'jpg', 'jpeg', 'webp'], true)) {
    $where[] = 'd.tipo_arquivo = ?';
    $params[] = $type;
}

if ($dateStart !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStart)) {
    $where[] = 'DATE(d.created_at) >= ?';
    $params[] = $dateStart;
}

if ($dateEnd !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEnd)) {
    $where[] = 'DATE(d.created_at) <= ?';
    $params[] = $dateEnd;
}

$sql = 'SELECT d.id, d.nome_arquivo, d.tipo_arquivo, d.created_at, u.nome AS usuario, u.email,
               ar.id AS analysis_id, ar.confianca, ar.modelo, ar.prompt_versao, ar.created_at AS analyzed_at
        FROM documents d
        INNER JOIN users u ON u.id = d.user_id
        LEFT JOIN (
            SELECT ar1.*
            FROM ai_results ar1
            INNER JOIN (
                SELECT document_id, MAX(id) AS latest_id
                FROM ai_results
                GROUP BY document_id
            ) latest ON latest.latest_id = ar1.id
        ) ar ON ar.document_id = d.id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY d.created_at DESC';

$documents = fetch_all($pdo, $sql, $params);
$analyzedCount = count(array_filter($documents, static fn ($doc): bool => !empty($doc['analysis_id'])));
$pendingCount = max(0, count($documents) - $analyzedCount);
$pdfCount = count(array_filter($documents, static fn ($doc): bool => ($doc['tipo_arquivo'] ?? '') === 'pdf'));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Documentos | Admin JusTraduz</title>
  <link rel="icon" href="../assets/img/icon.ico" type="image/x-icon">
  <link rel="stylesheet" href="../assets/css/style.css?v=theme-slow-3">
</head>
<body>
  <div class="app-shell admin-shell">
    <?php render_sidebar('admin', 'documentos.php', true); ?>

    <main class="app-main">
      <?php render_topbar('Documentos', 'Audite envios, filtros operacionais e análises por IA.', current_user_name()); ?>

      <section class="grid grid-4">
        <?= stat_card('Resultado filtrado', count($documents), 'folder') ?>
        <?= stat_card('Analisados por IA', $analyzedCount, 'chart') ?>
        <?= stat_card('Pendentes', $pendingCount, 'help') ?>
        <?= stat_card('PDFs', $pdfCount, 'file') ?>
      </section>

      <form class="card admin-filter admin-filter-documents" method="get">
        <div class="field">
          <label for="q">Busca</label>
          <input class="input" id="q" name="q" value="<?= e($q) ?>" placeholder="Arquivo, usuário ou e-mail">
        </div>
        <div class="field">
          <label for="analysis">Análise IA</label>
          <select class="select" id="analysis" name="analysis">
            <option value="">Todas</option>
            <option value="analisado" <?= $analysis === 'analisado' ? 'selected' : '' ?>>Analisado</option>
            <option value="pendente" <?= $analysis === 'pendente' ? 'selected' : '' ?>>Pendente</option>
          </select>
        </div>
        <div class="field">
          <label for="type">Tipo</label>
          <select class="select" id="type" name="type">
            <option value="">Todos</option>
            <?php foreach (['pdf', 'png', 'jpg', 'jpeg', 'webp'] as $option): ?>
              <option value="<?= e($option) ?>" <?= $type === $option ? 'selected' : '' ?>><?= e(strtoupper($option)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="date_start">De</label>
          <input class="input" id="date_start" name="date_start" type="date" value="<?= e($dateStart) ?>">
        </div>
        <div class="field">
          <label for="date_end">Até</label>
          <input class="input" id="date_end" name="date_end" type="date" value="<?= e($dateEnd) ?>">
        </div>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit">Filtrar</button>
          <a class="btn btn-outline" href="documentos.php">Limpar</a>
        </div>
      </form>

      <section class="dash-section">
        <div class="dash-section-title">
          <h2>Envios auditáveis</h2>
          <span class="badge badge-info"><?= e((string) count($documents)) ?> registros</span>
        </div>
        <?php if (!$documents): ?>
          <?= empty_state('Nenhum documento encontrado para os filtros selecionados.') ?>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table admin-documents-table">
              <thead><tr><th>Documento</th><th>Usuário</th><th>Tipo</th><th>Análise</th><th>Confiança</th><th>Enviado em</th><th>Ação</th></tr></thead>
              <tbody>
                <?php foreach ($documents as $document): ?>
                  <?php $hasAnalysis = !empty($document['analysis_id']); ?>
                  <tr>
                    <td><strong><?= e($document['nome_arquivo']) ?></strong><span class="table-subtext">#<?= (int) $document['id'] ?></span></td>
                    <td><?= e($document['usuario']) ?><span class="table-subtext"><?= e($document['email']) ?></span></td>
                    <td><?= e(strtoupper($document['tipo_arquivo'] ?? '')) ?></td>
                    <td>
                      <span class="badge <?= $hasAnalysis ? 'badge-success' : 'badge-warning' ?>"><?= $hasAnalysis ? 'Gerada' : 'Pendente' ?></span>
                      <?php if ($hasAnalysis): ?>
                        <span class="table-subtext"><?= e($document['modelo'] ?: 'Modelo não registrado') ?></span>
                        <?php if (!empty($document['prompt_versao'])): ?><span class="table-subtext"><?= e($document['prompt_versao']) ?></span><?php endif; ?>
                        <span class="table-subtext"><?= e(date('d/m/Y H:i', strtotime($document['analyzed_at']))) ?></span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($hasAnalysis && $document['confianca'] !== null): ?>
                        <div class="mini-confidence"><span style="--bar: <?= max(4, min(100, (int) round((float) $document['confianca']))) ?>%"></span></div>
                        <span class="table-subtext"><?= e(number_format((float) $document['confianca'], 1, ',', '.')) ?>%</span>
                      <?php else: ?>
                        <span class="text-muted">-</span>
                      <?php endif; ?>
                    </td>
                    <td><?= e(date('d/m/Y H:i', strtotime($document['created_at']))) ?></td>
                    <td>
                      <div class="stacked-actions">
                        <a class="btn btn-soft btn-sm" href="../visualizar-documento.php?id=<?= (int) $document['id'] ?>">Abrir</a>
                        <?php if (!$hasAnalysis): ?>
                          <form class="action-form" action="<?= e(app_url('/backend/public/index.php?rota=/documents/analyze')) ?>" method="post">
                            <?= csrf_input() ?>
                            <input type="hidden" name="document_id" value="<?= (int) $document['id'] ?>">
                            <input type="hidden" name="autorizar_ia" value="1">
                            <button class="btn btn-outline btn-sm" type="submit"><?= icon_svg('chart') ?> Gerar IA</button>
                          </form>
                        <?php endif; ?>
                      </div>
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
