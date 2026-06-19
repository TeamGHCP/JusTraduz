<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_role(['cliente']);

$userId = current_user_id();
$documentCount = count_query($pdo, 'SELECT COUNT(*) FROM documents WHERE user_id = ?', [$userId]);
$analysisCount = count_query($pdo, 'SELECT COUNT(*) FROM ai_results ar INNER JOIN documents d ON d.id = ar.document_id WHERE d.user_id = ?', [$userId]);
$pendingAnalysisCount = count_query($pdo, 'SELECT COUNT(*) FROM documents d LEFT JOIN ai_results ar ON ar.document_id = d.id WHERE d.user_id = ? AND ar.id IS NULL', [$userId]);
$caseCount = count_query($pdo, "SELECT COUNT(*) FROM cases WHERE cliente_id = ? AND status <> 'finalizado'", [$userId]);
$messageCount = count_query($pdo, 'SELECT COUNT(*) FROM messages m INNER JOIN cases c ON c.id = m.case_id WHERE c.cliente_id = ?', [$userId]);
$documents = fetch_all(
    $pdo,
    'SELECT d.id, d.nome_arquivo, d.tipo_arquivo, d.created_at, ar.id AS analysis_id
     FROM documents d
     LEFT JOIN ai_results ar ON ar.document_id = d.id
     WHERE d.user_id = ?
     ORDER BY d.created_at DESC
     LIMIT 8',
    [$userId]
);
$lastDocument = $documents[0] ?? null;
$lastCase = fetch_one($pdo, 'SELECT id, titulo, status, created_at FROM cases WHERE cliente_id = ? ORDER BY created_at DESC LIMIT 1', [$userId]);
$quickLinks = [
    [
        'title' => 'Enviar documento',
        'description' => 'Faça upload e acompanhe a análise na página de documentos.',
        'href' => 'visualizar-documento.php#novo-documento',
        'icon' => 'upload',
        'action' => 'Enviar agora',
    ],
    [
        'title' => 'Histórico',
        'description' => 'Consulte seus envios, status e análises disponíveis.',
        'href' => 'visualizar-documento.php',
        'icon' => 'folder',
        'action' => 'Ver histórico',
    ],
    [
        'title' => 'Pedir ajuda',
        'description' => 'Abra uma solicitação quando precisar de orientação.',
        'href' => 'solicitar-ajuda.php',
        'icon' => 'help',
        'action' => 'Solicitar',
    ],
    [
        'title' => 'Conversas',
        'description' => 'Acompanhe o chat dos seus casos em andamento.',
        'href' => 'chat.php',
        'icon' => 'chat',
        'action' => 'Abrir chat',
    ],
    [
        'title' => 'Agenda',
        'description' => 'Veja compromissos e próximos atendimentos.',
        'href' => 'agenda.php',
        'icon' => 'calendar',
        'action' => 'Ver agenda',
    ],
    [
        'title' => 'Perfil',
        'description' => 'Atualize seus dados e revise a segurança da conta.',
        'href' => 'perfil.php',
        'icon' => 'user',
        'action' => 'Editar perfil',
    ],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard do cliente | JusTraduz</title>
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
<body data-tour-page="dashboard_cliente">
  <div class="app-shell">
    <?php render_sidebar('cliente', 'dashboard-cliente.php'); ?>

    <main class="app-main" data-tour-step="1" data-tour-title="Bem-vindo ao JusTraduz" data-tour-description="Esta é sua central para entender documentos e acompanhar ajuda jurídica com clareza.">
      <?php render_topbar('Olá, ' . current_user_name(), 'Entenda documentos, peça ajuda e acompanhe seu atendimento.', current_user_name()); ?>

      <section class="client-command" data-tour-step="2" data-tour-title="Fluxo principal" data-tour-description="Envie documentos, acompanhe análises e peça ajuda jurídica quando precisar.">
        <article class="command-card command-card-primary" data-tour-step="3" data-tour-title="Enviar documento" data-tour-description="Comece por aqui para enviar contrato, notificação, imagem ou outro documento jurídico.">
          <span class="badge badge-info">Fluxo principal</span>
          <h2>Envie um documento e transforme juridiquês em próximos passos.</h2>
          <p>O JusTraduz organiza análise, solicitação, chat e agenda para você sair da dúvida com segurança.</p>
          <div class="form-actions">
            <a class="btn btn-primary" href="visualizar-documento.php#novo-documento"><?= icon_svg('upload') ?> Enviar documento</a>
            <a class="btn btn-outline" href="visualizar-documento.php"><?= icon_svg('file') ?> Ver documentos</a>
          </div>
        </article>

        <article class="command-card">
          <span>Último documento</span>
          <?php if ($lastDocument): ?>
            <strong><?= e($lastDocument['nome_arquivo']) ?></strong>
            <p><?= !empty($lastDocument['analysis_id']) ? 'Análise disponível para consulta.' : 'Análise ainda pendente.' ?></p>
            <a class="btn btn-soft btn-sm" href="visualizar-documento.php?id=<?= (int) $lastDocument['id'] ?>">Abrir</a>
          <?php else: ?>
            <strong>Nenhum envio</strong>
            <p>Comece pelo upload de PDF, PNG, JPEG ou WebP.</p>
          <?php endif; ?>
        </article>

        <article class="command-card">
          <span>Atendimento</span>
          <?php if ($lastCase): ?>
            <strong><?= e($lastCase['titulo']) ?></strong>
            <p>Status: <?= e(status_label($lastCase['status'] ?? '')) ?></p>
            <a class="btn btn-soft btn-sm" href="chat.php?case_id=<?= (int) $lastCase['id'] ?>">Abrir chat</a>
          <?php else: ?>
            <strong>Sem solicitação</strong>
            <p>Peça ajuda quando quiser orientação profissional.</p>
            <a class="btn btn-soft btn-sm" href="solicitar-ajuda.php">Pedir ajuda</a>
          <?php endif; ?>
        </article>
      </section>

      <section class="grid grid-4" data-tour-step="7" data-tour-title="Análises e pendências" data-tour-description="Estes indicadores mostram o que já foi analisado e o que ainda aguarda processamento.">
        <?= stat_card('Documentos', $documentCount, 'file') ?>
        <?= stat_card('Análises feitas', $analysisCount, 'chart') ?>
        <?= stat_card('Pendentes de IA', $pendingAnalysisCount, 'help') ?>
        <?= stat_card('Casos ativos', $caseCount, 'case') ?>
      </section>

      <section class="dash-section">
        <div class="dash-section-title">
          <div>
            <h2>Atalhos importantes <?= help_icon('Atalhos da dashboard', 'Use estes acessos para chegar rapidamente às principais áreas do JusTraduz.') ?></h2>
            <p class="text-muted">Acesse documentos, atendimento, conversas e conta sem procurar no menu.</p>
          </div>
        </div>
        <div class="grid grid-3">
          <?php foreach ($quickLinks as $link): ?>
            <article class="card">
              <?= icon_svg($link['icon']) ?>
              <h3><?= e($link['title']) ?></h3>
              <p class="text-muted"><?= e($link['description']) ?></p>
              <a class="btn btn-soft btn-sm" href="<?= e($link['href']) ?>"><?= e($link['action']) ?></a>
            </article>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="dash-section" id="novo-documento">
        <form class="card upload-card" action="<?= e(app_url('/backend/public/index.php?rota=/documents/upload')) ?>" method="post" enctype="multipart/form-data" data-upload-form data-tour-step="4" data-tour-title="Área de envio" data-tour-description="Selecione PDF ou imagem. A IA ajuda a explicar o texto, mas não substitui um advogado.">
          <?= csrf_input() ?>
          <div class="dash-section-title">
            <div>
              <h2>Novo documento <?= help_icon('Enviar documento', 'Use para enviar PDF ou imagem. Confira o arquivo e autorize IA somente quando concordar com o processamento.') ?></h2>
              <p class="text-muted">Envie o arquivo, autorize IA se fizer sentido e acompanhe a análise depois.</p>
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
          <label class="checkline mt-14" data-tour-step="5" data-tour-title="Autorização de IA" data-tour-description="Ative apenas quando concordar com o processamento automático do documento enviado.">
            <input type="checkbox" name="autorizar_ia" value="1">
            <span>Autorizo enviar este documento para análise automática por IA.</span>
          </label>
          <p class="mt-14 text-muted">A análise automática é informativa e não substitui orientação jurídica profissional.</p>
          <button class="btn btn-primary mt-16" type="submit" data-upload-submit>Enviar documento</button>
        </form>
      </section>

      <section class="dash-section" data-tour-step="6" data-tour-title="Histórico de documentos" data-tour-description="Consulte envios anteriores e abra as análises disponíveis.">
        <div class="dash-section-title">
          <h2>Histórico de documentos <?= help_icon('Histórico e análise', 'Abra seus envios anteriores para consultar o status e a explicação gerada. A análise não substitui orientação jurídica.') ?></h2>
          <a class="btn btn-soft btn-sm" href="solicitar-ajuda.php"><?= icon_svg('help') ?> Pedir ajuda</a>
        </div>
        <?php if (!$documents): ?>
          <?= empty_state('Nenhum documento enviado ainda.') ?>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table">
              <caption class="sr-only">Histórico dos documentos enviados</caption>
              <thead><tr><th>Documento</th><th>Tipo</th><th>Análise</th><th>Data</th><th>Ação</th></tr></thead>
              <tbody>
                <?php foreach ($documents as $document): ?>
                  <tr>
                    <td><strong><?= e($document['nome_arquivo']) ?></strong></td>
                    <td><?= e(strtoupper($document['tipo_arquivo'] ?? '')) ?></td>
                    <td><span class="badge <?= !empty($document['analysis_id']) ? 'badge-success' : 'badge-warning' ?>"><?= !empty($document['analysis_id']) ? 'Gerada' : 'Pendente' ?></span></td>
                    <td><?= e(date('d/m/Y H:i', strtotime($document['created_at']))) ?></td>
                    <td><a href="visualizar-documento.php?id=<?= (int) $document['id'] ?>">Abrir</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    </main>
  </div>
  <?php render_onboarding_assets('dashboard_cliente', '2026.06.11', 'cliente'); ?>
  <script src="assets/js/upload.js"></script>
  <?php render_vlibras(); ?>
</body>
</html>
