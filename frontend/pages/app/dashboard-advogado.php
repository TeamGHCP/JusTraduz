<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_role(['advogado']);

$userId = current_user_id();

function lawyer_cases_has_document_id(PDO $pdo): bool
{
    static $hasColumn = null;
    if ($hasColumn !== null) {
        return $hasColumn;
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM cases WHERE Field = 'document_id'");
    $hasColumn = (bool) $stmt->fetch();
    return $hasColumn;
}

function lawyer_priority_badge(?string $priority): string
{
    return $priority === 'alta' ? 'badge-warning' : 'badge-info';
}

function lawyer_status_badge(?string $status): string
{
    return match ($status) {
        'finalizado', 'concluida', 'concluido' => 'badge-success',
        'em_andamento', 'agendado' => 'badge-info',
        default => 'badge-warning',
    };
}

function lawyer_short_text(?string $text, int $limit = 180): string
{
    $text = trim((string) $text);
    if (mb_strlen($text) <= $limit) {
        return $text;
    }

    return mb_substr($text, 0, $limit - 3) . '...';
}

function lawyer_datetime(?string $value): string
{
    if (!$value) {
        return '-';
    }

    return date('d/m/Y H:i', strtotime($value));
}

$hasDocumentColumn = lawyer_cases_has_document_id($pdo);
$documentSelect = $hasDocumentColumn ? ', d.id AS document_id, d.nome_arquivo AS document_name' : ', NULL AS document_id, NULL AS document_name';
$documentJoin = $hasDocumentColumn ? ' LEFT JOIN documents d ON d.id = c.document_id' : '';

$assignedActiveCount = count_query($pdo, "SELECT COUNT(*) FROM cases WHERE advogado_id = ? AND status <> 'finalizado'", [$userId]);
$openCount = count_query($pdo, "SELECT COUNT(*) FROM cases WHERE advogado_id IS NULL AND status = 'aberto'");
$highPriorityOpenCount = count_query($pdo, "SELECT COUNT(*) FROM cases WHERE advogado_id IS NULL AND status = 'aberto' AND prioridade = 'alta'");
$taskCount = count_query(
    $pdo,
    "SELECT COUNT(*)
     FROM tasks t
     INNER JOIN cases c ON c.id = t.case_id
     WHERE c.advogado_id = ? AND t.status <> 'concluida'",
    [$userId]
);
$appointmentCount = count_query(
    $pdo,
    "SELECT COUNT(*)
     FROM appointments a
     INNER JOIN schedule_slots s ON s.id = a.slot_id
     WHERE s.professional_id = ? AND a.status = 'agendado' AND s.starts_at >= NOW()",
    [$userId]
);
$documentCountSql = $hasDocumentColumn
    ? "SELECT COUNT(DISTINCT c.document_id) FROM cases c WHERE c.advogado_id = ? AND c.document_id IS NOT NULL"
    : "SELECT COUNT(DISTINCT d.id)
       FROM documents d
       INNER JOIN cases c ON c.cliente_id = d.user_id
       WHERE c.advogado_id = ?";
$documentCount = count_query($pdo, $documentCountSql, [$userId]);

$openCases = fetch_all(
    $pdo,
    "SELECT c.id, c.titulo, c.descricao, c.prioridade, c.created_at, cli.nome AS cliente,
            COUNT(DISTINCT m.id) AS message_count,
            COUNT(DISTINCT t.id) AS task_count
            $documentSelect
     FROM cases c
     INNER JOIN users cli ON cli.id = c.cliente_id
     $documentJoin
     LEFT JOIN messages m ON m.case_id = c.id
     LEFT JOIN tasks t ON t.case_id = c.id
     WHERE c.advogado_id IS NULL AND c.status = 'aberto'
     GROUP BY c.id, c.titulo, c.descricao, c.prioridade, c.created_at, cli.nome" . ($hasDocumentColumn ? ', d.id, d.nome_arquivo' : '') . "
     ORDER BY FIELD(c.prioridade, 'alta', 'media', 'baixa'), c.created_at ASC
     LIMIT 6"
);

$assignedCases = fetch_all(
    $pdo,
    "SELECT c.id, c.titulo, c.descricao, c.status, c.prioridade, c.created_at, cli.nome AS cliente,
            COUNT(DISTINCT m.id) AS message_count,
            MAX(m.created_at) AS last_message_at,
            COUNT(DISTINCT t.id) AS task_count,
            COUNT(DISTINCT a.id) AS appointment_count
            $documentSelect
     FROM cases c
     INNER JOIN users cli ON cli.id = c.cliente_id
     $documentJoin
     LEFT JOIN messages m ON m.case_id = c.id
     LEFT JOIN tasks t ON t.case_id = c.id AND t.status <> 'concluida'
     LEFT JOIN appointments a ON a.case_id = c.id AND a.status <> 'cancelado'
     WHERE c.advogado_id = ? AND c.status <> 'finalizado'
     GROUP BY c.id, c.titulo, c.descricao, c.status, c.prioridade, c.created_at, cli.nome" . ($hasDocumentColumn ? ', d.id, d.nome_arquivo' : '') . "
     ORDER BY FIELD(c.prioridade, 'alta', 'media', 'baixa'), c.created_at DESC
     LIMIT 8",
    [$userId]
);

$tasks = fetch_all(
    $pdo,
    "SELECT t.id, t.titulo, t.descricao, t.status, t.created_at,
            c.id AS case_id, c.titulo AS caso, c.prioridade, cli.nome AS cliente
     FROM tasks t
     INNER JOIN cases c ON c.id = t.case_id
     INNER JOIN users cli ON cli.id = c.cliente_id
     WHERE c.advogado_id = ? AND t.status <> 'concluida'
     ORDER BY FIELD(t.status, 'pendente', 'em_andamento', 'concluida'),
              FIELD(c.prioridade, 'alta', 'media', 'baixa'),
              t.created_at ASC
     LIMIT 8",
    [$userId]
);

if ($hasDocumentColumn) {
    $recentDocuments = fetch_all(
        $pdo,
        "SELECT DISTINCT d.id, d.nome_arquivo, d.tipo_arquivo, d.created_at,
                cli.nome AS cliente, c.titulo AS caso, ar.id AS analysis_id, ar.confianca
         FROM cases c
         INNER JOIN users cli ON cli.id = c.cliente_id
         INNER JOIN documents d ON d.id = c.document_id
         LEFT JOIN ai_results ar ON ar.document_id = d.id
         WHERE c.advogado_id = ?
         ORDER BY d.created_at DESC
         LIMIT 6",
        [$userId]
    );
} else {
    $recentDocuments = fetch_all(
        $pdo,
        "SELECT DISTINCT d.id, d.nome_arquivo, d.tipo_arquivo, d.created_at,
                cli.nome AS cliente, c.titulo AS caso, ar.id AS analysis_id, ar.confianca
         FROM documents d
         INNER JOIN users cli ON cli.id = d.user_id
         INNER JOIN cases c ON c.cliente_id = d.user_id
         LEFT JOIN ai_results ar ON ar.document_id = d.id
         WHERE c.advogado_id = ?
         ORDER BY d.created_at DESC
         LIMIT 6",
        [$userId]
    );
}

$appointments = fetch_all(
    $pdo,
    "SELECT a.id, a.assunto, a.status, s.starts_at, s.ends_at, cli.nome AS cliente, c.id AS case_id, c.titulo AS caso
     FROM appointments a
     INNER JOIN schedule_slots s ON s.id = a.slot_id
     INNER JOIN users cli ON cli.id = a.client_id
     LEFT JOIN cases c ON c.id = a.case_id
     WHERE s.professional_id = ? AND a.status = 'agendado' AND s.starts_at >= NOW()
     ORDER BY s.starts_at ASC
     LIMIT 5",
    [$userId]
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta name="robots" content="noindex, nofollow">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mesa do advogado | JusTraduz</title>
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
  <script src="assets/js/pwa.js" defer></script>
</head>
<body data-tour-page="dashboard_advogado">
  <div class="app-shell">
    <?php render_sidebar('advogado', 'dashboard-advogado.php'); ?>

    <main class="app-main lawyer-dashboard" data-tour-step="1" data-tour-title="Visão geral do advogado" data-tour-description="Esta mesa reúne fila, casos, documentos, tarefas e agenda profissional.">
      <?php render_topbar('Mesa do advogado', 'Fila, prioridades, documentos, tarefas e agenda em uma única área.', current_user_name()); ?>

      <section class="lawyer-focus-panel" data-tour-step="2" data-tour-title="Prioridade do dia" data-tour-description="Comece pelos sinais que mais afetam atendimento, prazo e resposta ao cliente.">
        <div>
          <span class="badge <?= $highPriorityOpenCount > 0 ? 'badge-warning' : 'badge-info' ?>">Prioridade agora</span>
          <h2><?= $highPriorityOpenCount > 0 ? e((string) $highPriorityOpenCount) . ' casos de alta prioridade na fila' : 'Fila sob controle' ?></h2>
          <p><?= $openCount > 0 ? e((string) $openCount) . ' solicitações aguardam aceite. Priorize com critério.' : 'Nenhuma solicitação aberta. Use agenda e tarefas para manter casos ativos andando.' ?></p>
        </div>
        <div class="lawyer-focus-actions">
          <a class="btn btn-primary" href="acompanhar-solicitacoes.php?scope=abertos"><?= icon_svg('case') ?> Ver fila</a>
          <a class="btn btn-outline" href="tarefas.php"><?= icon_svg('check') ?> Tarefas</a>
          <a class="btn btn-soft" href="agenda.php"><?= icon_svg('calendar') ?> Agenda</a>
        </div>
      </section>

      <section class="lawyer-summary-grid" data-tour-step="3" data-tour-title="Prioridade e status" data-tour-description="Use os indicadores para identificar urgências, pendências e volume de trabalho.">
        <article class="lawyer-summary-card summary-tone-info">
          <?= icon_svg('case') ?>
          <span>Casos ativos</span>
          <strong><?= e((string) $assignedActiveCount) ?></strong>
        </article>
        <article class="lawyer-summary-card summary-tone-warning">
          <?= icon_svg('help') ?>
          <span>Fila aberta</span>
          <strong><?= e((string) $openCount) ?></strong>
        </article>
        <article class="lawyer-summary-card summary-tone-success">
          <?= icon_svg('check') ?>
          <span>Tarefas abertas</span>
          <strong><?= e((string) $taskCount) ?></strong>
        </article>
        <article class="lawyer-summary-card summary-tone-schedule">
          <?= icon_svg('calendar') ?>
          <span>Compromissos</span>
          <strong><?= e((string) $appointmentCount) ?></strong>
        </article>
      </section>

      <section class="dash-section" data-tour-step="4" data-tour-title="Fila de solicitações" data-tour-description="Aqui aparecem solicitações abertas, ordenadas por urgência e ainda sem responsável.">
        <div class="dash-section-title">
          <h2>Fila para aceitar <?= help_icon('Fila para aceitar', 'Solicitações sem responsável. Aceite apenas o que puder conduzir com segurança.') ?></h2>
          <span class="badge badge-warning">Ordenada por urgência</span>
        </div>
        <?php if (!$openCases): ?>
          <?= empty_state('Nenhuma solicitação aberta no momento.') ?>
        <?php else: ?>
          <div class="case-board lawyer-case-board">
            <?php foreach ($openCases as $case): ?>
              <article class="case-card-rich">
                <div class="case-card-head">
                  <div>
                    <span class="badge <?= e(lawyer_priority_badge($case['prioridade'] ?? '')) ?>"><?= e(status_label($case['prioridade'] ?? '')) ?></span>
                    <h3><?= e($case['titulo']) ?></h3>
                  </div>
                  <span class="badge badge-warning">Aberto</span>
                </div>
                <p><?= e(lawyer_short_text($case['descricao'] ?? '', 210)) ?></p>
                <div class="case-meta-grid lawyer-card-topics">
                  <div><span>Cliente</span><strong><?= e($case['cliente']) ?></strong></div>
                  <div><span>Criado</span><strong><?= e(lawyer_datetime($case['created_at'] ?? '')) ?></strong></div>
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
                  <div class="case-actions">
                    <form class="inline-form" action="<?= e(app_url('/backend/public/index.php?rota=/cases/accept')) ?>" method="post" data-tour-step="5" data-tour-title="Aceitar caso" data-tour-description="Aceite somente casos que você consegue conduzir com atenção, prazo e responsabilidade.">
                      <?= csrf_input() ?>
                      <input type="hidden" name="case_id" value="<?= (int) $case['id'] ?>">
                      <button class="btn btn-primary btn-sm" type="submit">Aceitar caso</button>
                    </form>
                    <a class="btn btn-outline btn-sm" href="acompanhar-solicitacoes.php?scope=abertos">Detalhes</a>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <section class="dash-section" data-tour-step="6" data-tour-title="Meus casos ativos" data-tour-description="Depois de aceitar um caso, acompanhe chat, tarefas, agenda e documentos ligados ao cliente.">
        <div class="dash-section-title">
          <h2>Meus casos ativos <?= help_icon('Casos ativos', 'Status, prioridade, tarefas, documentos e mensagens dos seus casos.') ?></h2>
          <a class="btn btn-soft btn-sm" href="acompanhar-solicitacoes.php?scope=meus">Ver todos</a>
        </div>
        <?php if (!$assignedCases): ?>
          <?= empty_state('Você ainda não possui casos ativos atribuídos.') ?>
        <?php else: ?>
          <div class="professional-card-grid">
            <?php foreach ($assignedCases as $case): ?>
              <article class="professional-case-card">
                <div class="case-card-head">
                  <div>
                    <span class="badge <?= e(lawyer_priority_badge($case['prioridade'] ?? '')) ?>"><?= e(status_label($case['prioridade'] ?? '')) ?></span>
                    <h3><?= e($case['titulo']) ?></h3>
                  </div>
                  <span class="badge <?= e(lawyer_status_badge($case['status'] ?? '')) ?>"><?= e(status_label($case['status'] ?? '')) ?></span>
                </div>
                <p><?= e(lawyer_short_text($case['descricao'] ?? '', 150)) ?></p>
                <div class="case-meta-grid lawyer-card-topics">
                  <div><span>Cliente</span><strong><?= e($case['cliente']) ?></strong></div>
                  <div><span>Mensagens</span><strong><?= e((string) (int) $case['message_count']) ?></strong></div>
                  <div><span>Tarefas</span><strong><?= e((string) (int) $case['task_count']) ?></strong></div>
                  <div><span>Agenda</span><strong><?= e((string) (int) $case['appointment_count']) ?></strong></div>
                </div>
                <?php if (!empty($case['document_id'])): ?>
                  <a class="case-linked-doc" href="visualizar-documento.php?id=<?= (int) $case['document_id'] ?>">
                    <?= icon_svg('file') ?>
                    <span><?= e($case['document_name'] ?? 'Documento relacionado') ?></span>
                  </a>
                <?php endif; ?>
                <div class="case-actions lawyer-primary-actions">
                  <a class="btn btn-primary btn-sm" href="chat.php?case_id=<?= (int) $case['id'] ?>"><?= icon_svg('chat') ?> Chat</a>
                  <details class="case-more-actions">
                    <summary>Mais ações</summary>
                    <div>
                      <a class="btn btn-outline btn-sm" href="tarefas.php?case_id=<?= (int) $case['id'] ?>"><?= icon_svg('check') ?> Tarefas</a>
                      <a class="btn btn-soft btn-sm" href="agenda.php"><?= icon_svg('calendar') ?> Agenda</a>
                    </div>
                  </details>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <section class="grid grid-2 professional-work-grid" data-tour-step="7" data-tour-title="Tarefas e agenda" data-tour-description="Organize os próximos passos dos casos e acompanhe compromissos futuros sem perder prazo.">
        <article class="dash-section">
          <div class="dash-section-title">
            <h2>Próximas tarefas <?= help_icon('Tarefas', 'Registre próximos passos objetivos e evite incluir dados sensíveis desnecessários na descrição.') ?></h2>
            <span class="badge badge-info"><?= e((string) $taskCount) ?> abertas</span>
          </div>
          <?php if (!$tasks): ?>
            <?= empty_state('Nenhuma tarefa aberta nos seus casos.') ?>
          <?php else: ?>
            <div class="professional-list">
              <?php foreach ($tasks as $task): ?>
                <details class="professional-list-item lawyer-work-item">
                  <summary>
                    <span class="lawyer-work-main">
                      <span class="badge <?= e(lawyer_status_badge($task['status'] ?? '')) ?>"><?= e(status_label($task['status'] ?? '')) ?></span>
                      <strong><?= e($task['titulo']) ?></strong>
                    </span>
                    <span class="lawyer-work-preview"><?= e($task['cliente']) ?></span>
                    <span class="lawyer-work-toggle">Detalhes</span>
                  </summary>
                  <div class="lawyer-work-details">
                    <div class="lawyer-work-meta">
                      <span><strong>Caso</strong><?= e($task['caso']) ?></span>
                      <span><strong>Cliente</strong><?= e($task['cliente']) ?></span>
                      <span><strong>Prioridade</strong><?= e(status_label($task['prioridade'] ?? '')) ?></span>
                    </div>
                    <a class="btn btn-outline btn-sm" href="tarefas.php?case_id=<?= (int) $task['case_id'] ?>">Abrir tarefa</a>
                  </div>
                </details>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </article>

        <article class="dash-section">
          <div class="dash-section-title">
            <h2>Agenda próxima</h2>
            <a class="btn btn-soft btn-sm" href="agenda.php">Gerenciar</a>
          </div>
          <?php if (!$appointments): ?>
            <?= empty_state('Nenhum agendamento futuro confirmado.') ?>
          <?php else: ?>
            <div class="professional-list">
              <?php foreach ($appointments as $appointment): ?>
                <details class="professional-list-item lawyer-work-item">
                  <summary>
                    <span class="lawyer-work-main">
                      <span class="badge badge-info"><?= e(lawyer_datetime($appointment['starts_at'] ?? '')) ?></span>
                      <strong><?= e($appointment['assunto']) ?></strong>
                    </span>
                    <span class="lawyer-work-preview"><?= e($appointment['cliente']) ?></span>
                    <span class="lawyer-work-toggle">Detalhes</span>
                  </summary>
                  <div class="lawyer-work-details">
                    <div class="lawyer-work-meta">
                      <span><strong>Cliente</strong><?= e($appointment['cliente']) ?></span>
                      <?php if (!empty($appointment['caso'])): ?>
                        <span><strong>Caso</strong><?= e($appointment['caso']) ?></span>
                      <?php endif; ?>
                    </div>
                    <?php if (!empty($appointment['case_id'])): ?>
                      <a class="btn btn-outline btn-sm" href="chat.php?case_id=<?= (int) $appointment['case_id'] ?>">Abrir chat</a>
                    <?php else: ?>
                      <a class="btn btn-outline btn-sm" href="agenda.php">Abrir agenda</a>
                    <?php endif; ?>
                  </div>
                </details>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </article>
      </section>

      <section class="dash-section" data-tour-step="8" data-tour-title="Documentos vinculados" data-tour-description="Revise apenas documentos associados aos seus casos e preserve o sigilo do cliente.">
        <div class="dash-section-title">
          <h2>Documentos para revisar <?= help_icon('Documentos vinculados', 'Acesse somente documentos dos seus casos. O conteúdo é sensível e deve permanecer protegido.') ?></h2>
          <a class="btn btn-soft btn-sm" href="visualizar-documento.php">Ver documentos</a>
        </div>
        <?php if (!$recentDocuments): ?>
          <?= empty_state('Nenhum documento vinculado aos seus casos.') ?>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table compact-table">
              <thead><tr><th>Documento</th><th>Cliente</th><th>Caso</th><th>Análise</th><th>Acao</th></tr></thead>
              <tbody>
                <?php foreach ($recentDocuments as $document): ?>
                  <tr>
                    <td><strong><?= e($document['nome_arquivo']) ?></strong><span><?= e(strtoupper((string) ($document['tipo_arquivo'] ?? ''))) ?> | <?= e(lawyer_datetime($document['created_at'] ?? '')) ?></span></td>
                    <td><?= e($document['cliente']) ?></td>
                    <td><?= e($document['caso']) ?></td>
                    <td><span class="badge <?= !empty($document['analysis_id']) ? 'badge-success' : 'badge-warning' ?>"><?= !empty($document['analysis_id']) ? 'Gerada' : 'Pendente' ?></span></td>
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
  <?php render_onboarding_assets('dashboard_advogado', '2026.06.28', 'advogado'); ?>
  <?php render_vlibras(); ?>
</body>
</html>
