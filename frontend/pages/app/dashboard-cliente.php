<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_role(['cliente']);

$userId = current_user_id();
$documentCount = count_query($pdo, 'SELECT COUNT(*) FROM documents WHERE user_id = ?', [$userId]);
$analysisCount = count_query($pdo, 'SELECT COUNT(*) FROM ai_results ar INNER JOIN documents d ON d.id = ar.document_id WHERE d.user_id = ?', [$userId]);
$pendingAnalysisCount = count_query($pdo, 'SELECT COUNT(*) FROM documents d LEFT JOIN ai_results ar ON ar.document_id = d.id WHERE d.user_id = ? AND ar.id IS NULL', [$userId]);
$caseCount = count_query($pdo, "SELECT COUNT(*) FROM cases WHERE cliente_id = ? AND status <> 'finalizado'", [$userId]);

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
$activeCase = fetch_one(
    $pdo,
    "SELECT c.id, c.titulo, c.status, c.created_at,
            (SELECT COUNT(*) FROM messages m WHERE m.case_id = c.id) AS message_count
     FROM cases c
     WHERE c.cliente_id = ? AND c.status <> 'finalizado'
     ORDER BY COALESCE((SELECT MAX(m2.created_at) FROM messages m2 WHERE m2.case_id = c.id), c.created_at) DESC
     LIMIT 1",
    [$userId]
);

$quickLinks = [
    [
        'title' => 'Enviar documento',
        'description' => 'Envie arquivos e acompanhe a análise.',
        'href' => 'visualizar-documento.php#novo-documento',
        'icon' => 'upload',
        'action' => 'Enviar agora',
        'tone' => 'primary',
    ],
    [
        'title' => 'Histórico',
        'description' => 'Veja envios, status e análises.',
        'href' => 'visualizar-documento.php',
        'icon' => 'folder',
        'action' => 'Ver histórico',
        'tone' => 'documents',
    ],
    [
        'title' => 'Pedir ajuda',
        'description' => 'Abra uma solicitação de orientação.',
        'href' => 'solicitar-ajuda.php',
        'icon' => 'help',
        'action' => 'Solicitar',
        'tone' => 'warning',
    ],
    [
        'title' => 'Conversas',
        'description' => 'Acompanhe conversas dos casos.',
        'href' => 'chat.php',
        'icon' => 'chat',
        'action' => 'Abrir chat',
        'tone' => 'info',
    ],
    [
        'title' => 'Agenda',
        'description' => 'Veja compromissos e atendimentos.',
        'href' => 'agenda.php',
        'icon' => 'calendar',
        'action' => 'Ver agenda',
        'tone' => 'schedule',
    ],
    [
        'title' => 'Perfil',
        'description' => 'Atualize dados e segurança.',
        'href' => 'perfil.php',
        'icon' => 'user',
        'action' => 'Editar perfil',
        'tone' => 'account',
    ],
];

$metricCards = [
    ['label' => 'Documentos', 'value' => $documentCount, 'icon' => 'file', 'href' => 'visualizar-documento.php', 'tone' => 'documents'],
    ['label' => 'Análises', 'value' => $analysisCount, 'icon' => 'chart', 'href' => 'visualizar-documento.php', 'tone' => 'success'],
    ['label' => 'IA pendente', 'value' => $pendingAnalysisCount, 'icon' => 'bell', 'href' => 'visualizar-documento.php', 'tone' => $pendingAnalysisCount > 0 ? 'warning' : 'success'],
    ['label' => 'Casos ativos', 'value' => $caseCount, 'icon' => 'case', 'href' => 'acompanhar-solicitacoes.php', 'tone' => 'info'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta name="robots" content="noindex, nofollow">
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
  <link rel="stylesheet" href="assets/css/style.css?v=2026.07.02-vlibras-panel-1">
  <script src="assets/js/pwa.js" defer></script>
</head>
<body data-tour-page="dashboard_cliente">
  <div class="app-shell">
    <?php render_sidebar('cliente', 'dashboard-cliente.php'); ?>

    <main class="app-main" data-tour-step="1" data-tour-title="Bem-vindo ao JusTraduz" data-tour-description="Esta é sua central para entender documentos e acompanhar ajuda jurídica com clareza.">
      <?php render_topbar('Olá, ' . current_user_name(), 'Documentos, ajuda e atendimento em um só lugar.', current_user_name()); ?>

      <section class="client-command" data-tour-step="2" data-tour-title="Fluxo principal" data-tour-description="Envie documentos, acompanhe análises e peça ajuda jurídica quando precisar.">
        <article class="command-card command-card-primary" data-tour-step="3" data-tour-title="Enviar documento" data-tour-description="Comece por aqui para enviar contrato, notificação, imagem ou outro documento jurídico.">
          <span class="badge badge-info">Fluxo principal</span>
          <h2>Envie um documento e veja próximos passos.</h2>
          <p>Análise, solicitação, chat e agenda ficam organizados em um só fluxo.</p>
          <div class="form-actions">
            <a class="btn btn-primary" href="visualizar-documento.php#novo-documento"><?= icon_svg('upload') ?> Enviar documento</a>
            <a class="btn btn-outline" href="visualizar-documento.php"><?= icon_svg('file') ?> Ver documentos</a>
          </div>
        </article>

        <article class="command-card command-card-secondary">
          <span>Último documento</span>
          <?php if ($lastDocument): ?>
            <span class="badge <?= !empty($lastDocument['analysis_id']) ? 'badge-success' : 'badge-warning' ?>"><?= !empty($lastDocument['analysis_id']) ? 'Análise gerada' : 'Pendente de IA' ?></span>
            <strong><?= e($lastDocument['nome_arquivo']) ?></strong>
            <p><?= !empty($lastDocument['analysis_id']) ? 'Análise disponível para consulta.' : 'Análise ainda pendente.' ?></p>
            <a class="btn btn-soft btn-sm" href="visualizar-documento.php?id=<?= (int) $lastDocument['id'] ?>">Abrir</a>
          <?php else: ?>
            <span class="badge badge-info">Aguardando envio</span>
            <strong>Nenhum envio</strong>
            <p>Comece pelo upload de PDF, DOCX, PNG, JPEG ou WebP.</p>
          <?php endif; ?>
        </article>

        <article class="command-card command-card-secondary">
          <span>Atendimento</span>
          <?php if ($activeCase): ?>
            <span class="badge badge-info"><?= e(status_label($activeCase['status'] ?? '')) ?></span>
            <strong><?= e($activeCase['titulo']) ?></strong>
            <?php $messageCount = (int) ($activeCase['message_count'] ?? 0); ?>
            <p><?= e((string) $messageCount) ?> <?= $messageCount === 1 ? 'mensagem' : 'mensagens' ?> neste atendimento.</p>
            <a class="btn btn-soft btn-sm" href="chat.php?case_id=<?= (int) $activeCase['id'] ?>">Abrir chat</a>
          <?php else: ?>
            <span class="badge badge-warning">Sem caso ativo</span>
            <strong>Sem solicitação</strong>
            <p>Peça ajuda quando quiser orientação profissional.</p>
            <a class="btn btn-soft btn-sm" href="solicitar-ajuda.php">Pedir ajuda</a>
          <?php endif; ?>
        </article>
      </section>

      <section class="grid grid-4 dashboard-metrics" data-tour-step="4" data-tour-title="Análises e pendências" data-tour-description="Estes indicadores mostram o que já foi analisado e o que ainda aguarda processamento.">
        <?php foreach ($metricCards as $metric): ?>
          <a class="stat-card dashboard-metric-link metric-tone-<?= e($metric['tone']) ?>" href="<?= e($metric['href']) ?>">
            <?= icon_svg($metric['icon']) ?>
            <span><?= e($metric['label']) ?></span>
            <strong><?= e((string) $metric['value']) ?></strong>
          </a>
        <?php endforeach; ?>
      </section>

      <section class="dash-section" data-tour-step="5" data-tour-title="Atalhos da rotina" data-tour-description="Use estes cartões para ir direto a documentos, atendimento, conversas, agenda e perfil sem depender do menu.">
        <div class="dash-section-title">
          <div>
            <h2>Atalhos importantes <?= help_icon('Atalhos da dashboard', 'Use estes acessos para chegar rapidamente às principais áreas do JusTraduz.') ?></h2>
            <p class="text-muted">Acesse as principais áreas sem procurar no menu.</p>
          </div>
        </div>
        <div class="grid grid-3 quick-actions-grid">
          <?php foreach ($quickLinks as $link): ?>
            <?php $isPriorityAction = in_array($link['href'], ['visualizar-documento.php#novo-documento', 'solicitar-ajuda.php', 'chat.php'], true); ?>
            <article class="card quick-action-card quick-action-tone-<?= e($link['tone']) ?><?= $isPriorityAction ? ' quick-action-card-priority' : '' ?>">
              <?= icon_svg($link['icon']) ?>
              <h3><?= e($link['title']) ?></h3>
              <p class="text-muted"><?= e($link['description']) ?></p>
              <a class="btn btn-soft btn-sm" href="<?= e($link['href']) ?>"><?= e($link['action']) ?></a>
            </article>
          <?php endforeach; ?>
        </div>
      </section>

    </main>
  </div>
  <?php render_onboarding_assets('dashboard_cliente', '2026.06.28', 'cliente'); ?>
  <?php render_vlibras(); ?>
</body>
</html>
