<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();

$type = current_user_type();
$documentId = (int) ($_GET['id'] ?? 0);

function document_access_sql(string $type): array
{
    if ($type === 'cliente') {
        return ['d.user_id = ?', [current_user_id()]];
    }

    if ($type === 'advogado') {
        return [
            'EXISTS (
                SELECT 1 FROM cases c
                WHERE c.cliente_id = d.user_id
                AND c.advogado_id = ?
            )',
            [current_user_id()],
        ];
    }

    if ($type === 'admin') {
        return ['1 = 1', []];
    }

    return ['0 = 1', []];
}

[$accessSql, $accessParams] = document_access_sql($type);

if ($documentId) {
    $document = fetch_one(
        $pdo,
        "SELECT d.*, u.nome AS cliente, u.email AS cliente_email, ar.resumo, ar.explicacao, ar.confianca
         FROM documents d
         INNER JOIN users u ON u.id = d.user_id
         LEFT JOIN ai_results ar ON ar.document_id = d.id
         WHERE d.id = ? AND $accessSql",
        array_merge([$documentId], $accessParams)
    );
    $documents = [];
} else {
    $document = null;
    $documents = fetch_all(
        $pdo,
        "SELECT d.id, d.nome_arquivo, d.tipo_arquivo, d.created_at, u.nome AS cliente, u.email AS cliente_email, ar.id AS analysis_id
         FROM documents d
         INNER JOIN users u ON u.id = d.user_id
         LEFT JOIN ai_results ar ON ar.document_id = d.id
         WHERE $accessSql
         ORDER BY d.created_at DESC",
        $accessParams
    );
}

$fileUrl = $document ? app_url('/backend/public/index.php?rota=/documents/download&id=' . (int) $document['id']) : '';
$fileType = strtolower((string) ($document['tipo_arquivo'] ?? ''));
$isPdf = $fileType === 'pdf';
$isImage = in_array($fileType, ['png', 'jpg', 'jpeg', 'webp'], true);
$hasAnalysis = $document && ((string) ($document['resumo'] ?? '') !== '' || (string) ($document['explicacao'] ?? '') !== '');
$confidence = $document && $document['confianca'] !== null ? max(0, min(100, (float) $document['confianca'])) : null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Documentos | JusTraduz</title>
  <link rel="icon" href="assets/img/icon.ico" type="image/x-icon">
  <link rel="stylesheet" href="assets/css/style.css?v=theme-slow-2">
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar($type, 'visualizar-documento.php'); ?>

    <main class="app-main">
      <?php render_topbar(
          $document ? 'Análise do documento' : 'Documentos',
          $document ? 'Arquivo original, resumo e explicação em linguagem simples.' : 'Consulte os documentos disponíveis para seu perfil.',
          current_user_name()
      ); ?>

      <?php if (!$documentId): ?>
        <section class="dash-section">
          <div class="dash-section-title">
            <h2>Lista de documentos</h2>
            <span class="badge badge-success"><?= e((string) count($documents)) ?> registros</span>
          </div>
          <?php if (!$documents): ?>
            <?= empty_state('Nenhum documento disponível no momento.') ?>
          <?php else: ?>
            <div class="table-wrap">
              <table class="table">
                <thead><tr><th>Cliente</th><th>Documento</th><th>Tipo</th><th>Análise</th><th>Enviado em</th><th>Ação</th></tr></thead>
                <tbody>
                  <?php foreach ($documents as $item): ?>
                    <tr>
                      <td><?= e($item['cliente']) ?><span class="table-subtext"><?= e($item['cliente_email']) ?></span></td>
                      <td><strong><?= e($item['nome_arquivo']) ?></strong></td>
                      <td><?= e(strtoupper($item['tipo_arquivo'] ?? '')) ?></td>
                      <td><span class="badge <?= !empty($item['analysis_id']) ? 'badge-success' : 'badge-warning' ?>"><?= !empty($item['analysis_id']) ? 'Gerada' : 'Pendente' ?></span></td>
                      <td><?= e(date('d/m/Y H:i', strtotime($item['created_at']))) ?></td>
                      <td><a href="visualizar-documento.php?id=<?= (int) $item['id'] ?>">Abrir</a></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </section>
      <?php elseif (!$document): ?>
        <?= empty_state('Documento não encontrado ou indisponível para seu perfil.') ?>
      <?php else: ?>
        <section class="analysis-hero">
          <div>
            <span class="badge <?= $hasAnalysis ? 'badge-success' : 'badge-warning' ?>"><?= $hasAnalysis ? 'Análise disponível' : 'Análise pendente' ?></span>
            <h2><?= e($document['nome_arquivo']) ?></h2>
            <p>Cliente: <?= e($document['cliente']) ?> · Enviado em <?= e(date('d/m/Y H:i', strtotime($document['created_at']))) ?></p>
          </div>
          <div class="analysis-hero-actions">
            <?php if ($confidence !== null): ?>
              <div class="confidence-meter">
                <strong><?= e((string) $confidence) ?>%</strong>
                <span>confiança</span>
              </div>
            <?php endif; ?>
            <a class="btn btn-outline btn-sm" href="visualizar-documento.php"><?= icon_svg('file') ?> Voltar</a>
            <a class="btn btn-primary btn-sm" href="<?= e($fileUrl) ?>" target="_blank" rel="noopener"><?= icon_svg('folder') ?> Abrir arquivo</a>
          </div>
        </section>

        <section class="doc-toolbar">
          <?php if (in_array($type, ['cliente', 'admin'], true)): ?>
            <form class="inline-form" action="<?= e(app_url('/backend/public/index.php?rota=/documents/delete')) ?>" method="post" data-confirm-delete="Excluir este documento? Esta ação não pode ser desfeita.">
              <?= csrf_input() ?>
              <input type="hidden" name="document_id" value="<?= (int) $document['id'] ?>">
              <button class="btn btn-outline btn-sm" type="submit"><?= icon_svg('trash') ?> Excluir</button>
            </form>
          <?php endif; ?>
          <?php if ($document['texto_extraido'] || $isPdf || $isImage): ?>
            <form class="inline-form analysis-form" action="<?= e(app_url('/backend/public/index.php?rota=/documents/analyze')) ?>" method="post">
              <?= csrf_input() ?>
              <input type="hidden" name="document_id" value="<?= (int) $document['id'] ?>">
              <label class="checkline checkline-inline">
                <input type="checkbox" name="autorizar_ia" value="1" required>
                <span>Autorizo análise por IA</span>
              </label>
              <button class="btn btn-soft btn-sm" type="submit"><?= icon_svg('chart') ?> <?= $hasAnalysis ? 'Regerar análise' : 'Gerar análise' ?></button>
            </form>
          <?php endif; ?>
          <?php if ($type === 'cliente'): ?>
            <a class="btn btn-primary btn-sm" href="solicitar-ajuda.php"><?= icon_svg('help') ?> Pedir ajuda</a>
          <?php endif; ?>
        </section>

        <section class="doc-view doc-view-wide">
          <article class="card doc-pane doc-file-pane">
            <div class="dash-section-title">
              <h2>Arquivo original</h2>
              <span class="badge badge-info"><?= e(strtoupper($fileType)) ?></span>
            </div>

            <?php if ($isPdf): ?>
              <object class="doc-frame" data="<?= e($fileUrl) ?>" type="application/pdf">
                <iframe class="doc-frame" src="<?= e($fileUrl) ?>"></iframe>
              </object>
            <?php elseif ($isImage): ?>
              <div class="doc-image-wrap">
                <img src="<?= e($fileUrl) ?>" alt="<?= e($document['nome_arquivo']) ?>">
              </div>
            <?php else: ?>
              <?= empty_state('Pré-visualização não disponível para este formato.') ?>
            <?php endif; ?>
          </article>

          <article class="card doc-pane doc-analysis-pane analysis-pane">
            <div class="analysis-block analysis-block-primary">
              <div class="dash-section-title">
                <h2>Linguagem simples</h2>
                <?php if ($hasAnalysis): ?><button class="btn btn-outline btn-sm" type="button" data-copy-text="#analysis-simple">Copiar</button><?php endif; ?>
              </div>
              <div class="doc-text" id="analysis-simple"><?= nl2br(e($document['explicacao'] ?: 'Análise por IA ainda não disponível. Autorize o processamento para gerar uma explicação em linguagem simples.')) ?></div>
            </div>
            <div class="doc-divider"></div>
            <div class="analysis-block">
              <div class="dash-section-title">
                <h2>Resumo objetivo</h2>
                <?php if ($hasAnalysis): ?><button class="btn btn-outline btn-sm" type="button" data-copy-text="#analysis-summary">Copiar</button><?php endif; ?>
              </div>
              <div class="doc-text" id="analysis-summary"><?= nl2br(e($document['resumo'] ?: 'Resumo ainda não disponível.')) ?></div>
            </div>
            <div class="analysis-disclaimer">
              <?= icon_svg('shield') ?>
              <span>A análise automática é informativa e não substitui orientação jurídica profissional.</span>
            </div>
          </article>
        </section>
      <?php endif; ?>
    </main>
  </div>
  <script src="assets/js/documento.js"></script>
</body>
</html>
