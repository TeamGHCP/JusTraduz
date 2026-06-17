<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_role(['cliente']);

$lawyers = fetch_all($pdo, "SELECT id, nome, oab, oab_uf FROM users WHERE tipo = 'advogado' AND status = 'ativo' AND oab_verificado = TRUE ORDER BY nome");
$selectedLawyerId = (int) ($_GET['advogado_id'] ?? 0);
$selectedDocumentId = (int) ($_GET['document_id'] ?? 0);
$selectedDocument = null;
$prefillTitle = '';
$prefillDescription = '';

if ($selectedDocumentId > 0) {
    $selectedDocument = fetch_one(
        $pdo,
        'SELECT d.id, d.nome_arquivo, d.tipo_arquivo, d.created_at, ar.resumo, ar.explicacao, ar.confianca
         FROM documents d
         LEFT JOIN ai_results ar ON ar.document_id = d.id
         WHERE d.id = ? AND d.user_id = ?',
        [$selectedDocumentId, current_user_id()]
    );

    if ($selectedDocument) {
        $prefillTitle = 'Ajuda com documento: ' . (string) $selectedDocument['nome_arquivo'];
        $summary = trim((string) ($selectedDocument['resumo'] ?? ''));
        $analysis = trim((string) ($selectedDocument['explicacao'] ?? ''));

        $prefillDescription = "Documento relacionado: " . (string) $selectedDocument['nome_arquivo'] . "\n";
        if ($summary !== '') {
            $prefillDescription .= "\nResumo da análise:\n" . mb_substr($summary, 0, 900) . "\n";
        }
        if ($analysis !== '') {
            $prefillDescription .= "\nPontos da análise para o profissional revisar:\n" . mb_substr(strip_tags($analysis), 0, 1200) . "\n";
        }
        $prefillDescription .= "\nMinha duvida principal:\n";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Solicitar ajuda | JusTraduz</title>
  <link rel="icon" href="assets/img/icon.ico" type="image/x-icon">
  <link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
  <link rel="manifest" href="site.webmanifest">
  <meta name="theme-color" content="#008f80">
  <link rel="stylesheet" href="assets/css/style.css?v=sidebar-open-button-1">
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar('cliente', 'solicitar-ajuda.php'); ?>

    <main class="app-main">
      <?php render_topbar('Solicitar ajuda juridica', 'Transforme a análise do documento em atendimento humano.', current_user_name()); ?>

      <?php if ($selectedDocument): ?>
        <section class="selected-document-context">
          <div>
            <span class="badge badge-info">Documento relacionado</span>
            <h2><?= e($selectedDocument['nome_arquivo']) ?></h2>
            <p>Enviado em <?= e(date('d/m/Y H:i', strtotime((string) $selectedDocument['created_at']))) ?><?= $selectedDocument['confianca'] !== null ? ' | Confianca IA: ' . e(number_format((float) $selectedDocument['confianca'], 1, ',', '.')) . '%' : '' ?></p>
          </div>
          <a class="btn btn-outline btn-sm" href="visualizar-documento.php?id=<?= (int) $selectedDocument['id'] ?>"><?= icon_svg('file') ?> Ver análise</a>
        </section>
      <?php elseif ($selectedDocumentId > 0): ?>
        <div class="alert alert-error is-visible">Documento não encontrado para a sua conta. A solicitacao sera criada sem contexto automatico.</div>
      <?php endif; ?>

      <form class="card auth-form" action="<?= e(app_url('/backend/public/index.php?rota=/cases/create')) ?>" method="post">
        <?= csrf_input() ?>
        <?php if ($selectedDocument): ?>
          <input type="hidden" name="document_id" value="<?= (int) $selectedDocument['id'] ?>">
        <?php endif; ?>
        <div class="form-grid">
          <div class="field">
            <label for="titulo">Titulo da solicitacao</label>
            <input class="input" id="titulo" name="titulo" value="<?= e($prefillTitle) ?>" required>
          </div>
          <div class="field">
            <label for="prioridade">Prioridade</label>
            <select class="select" id="prioridade" name="prioridade">
              <option value="baixa">Baixa</option>
              <option value="media" <?= !$selectedDocument ? 'selected' : '' ?>>Media</option>
              <option value="alta" <?= $selectedDocument ? 'selected' : '' ?>>Alta</option>
            </select>
          </div>
        </div>
        <div class="field">
          <label for="advogado_id">Advogado especifico</label>
          <select class="select" id="advogado_id" name="advogado_id">
            <option value="">Deixar solicitacao aberta</option>
            <?php foreach ($lawyers as $lawyer): ?>
              <option value="<?= (int) $lawyer['id'] ?>" <?= $selectedLawyerId === (int) $lawyer['id'] ? 'selected' : '' ?>>
                <?= e($lawyer['nome']) ?><?= $lawyer['oab'] ? ' - OAB/' . e($lawyer['oab_uf']) . ' ' . e($lawyer['oab']) : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="descricao">Descreva sua duvida</label>
          <textarea class="textarea textarea-tall" id="descricao" name="descricao" required><?= e($prefillDescription) ?></textarea>
        </div>
        <div class="alert alert-info is-visible">A IA organiza o contexto, mas a decisao e a orientação juridica devem vir de um profissional.</div>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit"><?= icon_svg('help') ?> Enviar solicitacao</button>
          <a class="btn btn-outline" href="lista-advogados.php"><?= icon_svg('users') ?> Ver advogados</a>
        </div>
      </form>
    </main>
  </div>
  <?php render_vlibras(); ?>
</body>
</html>
