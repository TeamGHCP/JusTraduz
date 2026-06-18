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

function document_analysis_sections(string $text): array
{
    $sections = [
        'simple' => '',
        'important' => '',
        'risks' => '',
        'steps' => '',
        'notice' => '',
    ];

    $current = 'simple';
    $hasHeadings = false;
    foreach (preg_split('/\R/', $text) ?: [] as $line) {
        $trimmed = trim((string) $line);

        if (str_starts_with($trimmed, '## ')) {
            $hasHeadings = true;
            $heading = mb_strtolower(trim(substr($trimmed, 3)));
            $current = match (true) {
                str_contains($heading, 'pontos importantes') => 'important',
                str_contains($heading, 'riscos') || str_contains($heading, 'atencao') => 'risks',
                str_contains($heading, 'próximos passos') => 'steps',
                str_contains($heading, 'aviso') => 'notice',
                default => 'simple',
            };
            continue;
        }

        $sections[$current] .= ($sections[$current] === '' ? '' : "\n") . $line;
    }

    if (!$hasHeadings) {
        $sections['simple'] = $text;
    }

    return array_map(static fn (string $value): string => trim($value), $sections);
}

function document_analysis_items(string $text): array
{
    $items = [];
    foreach (preg_split('/\R/', $text) ?: [] as $line) {
        $line = trim((string) $line);
        $line = preg_replace('/^[-*]\s*/', '', $line);
        if ($line !== '') {
            $items[] = $line;
        }
    }

    return $items;
}

function render_analysis_text_block(string $text): void
{
    $items = document_analysis_items($text);
    if (count($items) > 1) {
        echo '<ul class="analysis-list">';
        foreach ($items as $item) {
            echo '<li>' . e($item) . '</li>';
        }
        echo '</ul>';
        return;
    }

    echo '<div class="doc-text">' . nl2br(e($text)) . '</div>';
}

[$accessSql, $accessParams] = document_access_sql($type);

if ($documentId) {
    $document = fetch_one(
        $pdo,
        "SELECT d.*, u.nome AS cliente, u.email AS cliente_email,
                ar.resumo, ar.explicacao, ar.confianca, ar.modelo, ar.prompt_versao, ar.created_at AS analyzed_at
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
        "SELECT d.id, d.nome_arquivo, d.tipo_arquivo, d.created_at,
                u.nome AS cliente, u.email AS cliente_email,
                ar.id AS analysis_id, ar.confianca
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
$analysisSections = $hasAnalysis ? document_analysis_sections((string) ($document['explicacao'] ?? '')) : [];
$helpUrl = $document ? 'solicitar-ajuda.php?document_id=' . (int) $document['id'] : 'solicitar-ajuda.php';
$successMessage = trim((string) ($_GET['sucesso'] ?? ''));
$errorMessage = trim((string) ($_GET['erro'] ?? ''));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Documentos | JusTraduz</title>
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
  <link rel="stylesheet" href="assets/css/style.css?v=sidebar-open-button-1">
  <script src="assets/js/pwa.js" defer></script>
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar($type, 'visualizar-documento.php'); ?>

    <main class="app-main">
      <?php render_topbar(
          $document ? 'Análise do documento' : 'Documentos',
          $document ? 'Resumo, explicacao simples, riscos e próximos passos.' : 'Envie documentos e consulte seu histórico em um só lugar.',
          current_user_name()
      ); ?>

      <?php if ($successMessage !== ''): ?>
        <div class="alert is-visible alert-success"><?= e($successMessage) ?></div>
      <?php endif; ?>
      <?php if ($errorMessage !== ''): ?>
        <div class="alert is-visible alert-error"><?= e($errorMessage) ?></div>
      <?php endif; ?>

      <?php if (!$documentId): ?>
        <?php if ($type === 'cliente'): ?>
          <section class="dash-section" id="novo-documento">
            <form class="card upload-card" action="<?= e(app_url('/backend/public/index.php?rota=/documents/upload')) ?>" method="post" enctype="multipart/form-data" data-upload-form>
              <?= csrf_input() ?>
              <input type="hidden" name="redirect_to" value="documents">
              <div class="dash-section-title">
                <div>
                  <h2>Novo documento <?= help_icon('Enviar documento', 'Use para enviar PDF ou imagem. Confira o arquivo e autorize IA somente quando concordar com o processamento.') ?></h2>
                  <p class="text-muted">Envie o arquivo por aqui ou pela dashboard. Depois, acompanhe tudo no histórico abaixo.</p>
                </div>
                <span class="badge badge-success">Máx. 50 MB</span>
              </div>
              <label class="upload-box upload-box-featured" data-upload-box tabindex="0">
                <input class="sr-only" type="file" name="documento" accept=".pdf,.png,.jpg,.jpeg,.webp" data-upload-input required>
                <?= icon_svg('upload') ?>
                <strong>Arraste seu arquivo ou clique para selecionar</strong>
                <p data-file-name>PDF, PNG, JPEG ou WebP</p>
                <span class="btn btn-primary">Selecionar arquivo</span>
              </label>
              <label class="checkline mt-14">
                <input type="checkbox" name="autorizar_ia" value="1">
                <span>Autorizo enviar este documento para análise automática por IA.</span>
              </label>
              <p class="mt-14 text-muted">A análise automática é informativa e não substitui orientação jurídica profissional.</p>
              <button class="btn btn-primary mt-16" type="submit" data-upload-submit>Enviar documento</button>
            </form>
          </section>
        <?php endif; ?>

        <section class="dash-section">
          <div class="dash-section-title">
            <h2>Histórico de documentos <?= help_icon('Histórico e análise', 'Abra seus envios anteriores para consultar o status e a explicação gerada. A análise não substitui orientação jurídica.') ?></h2>
            <span class="badge badge-success"><?= e((string) count($documents)) ?> registros</span>
          </div>
          <?php if (!$documents): ?>
            <?= empty_state('Nenhum documento disponível no momento.') ?>
          <?php else: ?>
            <div class="table-wrap">
              <table class="table">
                <thead><tr><th>Cliente</th><th>Documento</th><th>Tipo</th><th>Análise</th><th>Confianca</th><th>Enviado em</th><th>Acao</th></tr></thead>
                <tbody>
                  <?php foreach ($documents as $item): ?>
                    <?php $itemConfidence = $item['confianca'] !== null ? max(0, min(100, (float) $item['confianca'])) : null; ?>
                    <tr>
                      <td><?= e($item['cliente']) ?><span class="table-subtext"><?= e($item['cliente_email']) ?></span></td>
                      <td><strong><?= e($item['nome_arquivo']) ?></strong></td>
                      <td><?= e(strtoupper($item['tipo_arquivo'] ?? '')) ?></td>
                      <td><span class="badge <?= !empty($item['analysis_id']) ? 'badge-success' : 'badge-warning' ?>"><?= !empty($item['analysis_id']) ? 'Gerada' : 'Pendente' ?></span></td>
                      <td><?= $itemConfidence !== null ? e(number_format($itemConfidence, 1, ',', '.')) . '%' : '<span class="text-muted">-</span>' ?></td>
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
            <p>Cliente: <?= e($document['cliente']) ?> | Enviado em <?= e(date('d/m/Y H:i', strtotime($document['created_at']))) ?></p>
            <?php if ($hasAnalysis): ?>
              <div class="analysis-meta-row">
                <?php if (!empty($document['modelo'])): ?><span><?= e($document['modelo']) ?></span><?php endif; ?>
                <?php if (!empty($document['prompt_versao'])): ?><span><?= e($document['prompt_versao']) ?></span><?php endif; ?>
                <?php if (!empty($document['analyzed_at'])): ?><span><?= e(date('d/m/Y H:i', strtotime((string) $document['analyzed_at']))) ?></span><?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
          <div class="analysis-hero-actions">
            <?php if ($confidence !== null): ?>
              <div class="confidence-meter">
                <strong><?= e(number_format($confidence, 1, ',', '.')) ?>%</strong>
                <span>confianca</span>
              </div>
            <?php endif; ?>
            <a class="btn btn-outline btn-sm" href="visualizar-documento.php"><?= icon_svg('file') ?> Voltar</a>
            <a class="btn btn-primary btn-sm" href="<?= e($fileUrl) ?>" target="_blank" rel="noopener"><?= icon_svg('folder') ?> Abrir arquivo</a>
          </div>
        </section>

        <section class="doc-toolbar">
          <?php if (in_array($type, ['cliente', 'admin'], true)): ?>
            <form class="inline-form" action="<?= e(app_url('/backend/public/index.php?rota=/documents/delete')) ?>" method="post" data-confirm-delete="Excluir este documento? Esta acao não pode ser desfeita.">
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
            <a class="btn btn-primary btn-sm" href="<?= e($helpUrl) ?>"><?= icon_svg('help') ?> Pedir ajuda com este documento</a>
          <?php endif; ?>
        </section>

        <section class="doc-view doc-view-wide doc-view-analysis-first">
          <article class="card doc-pane doc-analysis-pane analysis-pane">
            <?php if (!$hasAnalysis): ?>
              <div class="analysis-empty">
                <?= icon_svg('chart') ?>
                <h2>Análise ainda não gerada</h2>
                <p>Autorize a IA acima para transformar o documento em resumo, linguagem simples, riscos e próximos passos.</p>
              </div>
            <?php else: ?>
              <div class="analysis-summary-card">
                <div>
                  <span class="badge badge-info">Resumo objetivo</span>
                  <h2>O que este documento diz</h2>
                </div>
                <?php if ($hasAnalysis): ?><button class="btn btn-outline btn-sm" type="button" data-copy-text="#analysis-summary">Copiar</button><?php endif; ?>
                <p id="analysis-summary"><?= nl2br(e((string) ($document['resumo'] ?? ''))) ?></p>
              </div>

              <div class="analysis-section-grid">
                <section class="analysis-section analysis-section-wide analysis-section-primary">
                  <div class="analysis-section-head">
                    <?= icon_svg('file') ?>
                    <h3>Explicacao em linguagem simples</h3>
                  </div>
                  <div id="analysis-simple"><?php render_analysis_text_block($analysisSections['simple'] ?? ''); ?></div>
                </section>

                <section class="analysis-section">
                  <div class="analysis-section-head">
                    <?= icon_svg('check') ?>
                    <h3>Pontos importantes</h3>
                  </div>
                  <?php render_analysis_text_block($analysisSections['important'] ?: 'Nenhum ponto separado pela IA. Leia o resumo e confira o documento original.'); ?>
                </section>

                <section class="analysis-section">
                  <div class="analysis-section-head">
                    <?= icon_svg('shield') ?>
                    <h3>Riscos e atencao</h3>
                  </div>
                  <?php render_analysis_text_block($analysisSections['risks'] ?: 'Sem riscos destacados automaticamente. Isso não elimina a necessidade de revisão profissional.'); ?>
                </section>

                <section class="analysis-section">
                  <div class="analysis-section-head">
                    <?= icon_svg('help') ?>
                    <h3>Próximos passos</h3>
                  </div>
                  <?php render_analysis_text_block($analysisSections['steps'] ?: 'Separe documentos relacionados e converse com um profissional antes de decidir.'); ?>
                </section>

                <section class="analysis-section">
                  <div class="analysis-section-head">
                    <?= icon_svg('shield') ?>
                    <h3>Limite da análise</h3>
                  </div>
                  <?php render_analysis_text_block($analysisSections['notice'] ?: 'Esta análise é informativa e não substitui orientação juridica profissional.'); ?>
                </section>
              </div>
            <?php endif; ?>

            <div class="analysis-disclaimer">
              <?= icon_svg('shield') ?>
              <span>Uso informativo: a IA ajuda a entender o texto, mas não decide seu caso nem substitui advogado.</span>
            </div>
          </article>

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
              <div class="analysis-empty">
                <?= icon_svg('file') ?>
                <h2>Preview indisponível</h2>
                <p>Abra o arquivo original para consultar este formato.</p>
              </div>
            <?php endif; ?>
          </article>
        </section>
      <?php endif; ?>
    </main>
  </div>
  <script src="assets/js/upload.js"></script>
  <script src="assets/js/documento.js"></script>
  <?php render_vlibras(); ?>
</body>
</html>
