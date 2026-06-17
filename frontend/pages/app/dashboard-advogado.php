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
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mesa do advogado | JusTraduz</title>
  <link rel="icon" href="assets/img/icon.ico" type="image/x-icon">
  <link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
  <link rel="manifest" href="site.webmanifest">
  <meta name="theme-color" content="#008f80">
  <link rel="stylesheet" href="assets/css/style.css?v=sidebar-open-button-1">
</head>
<body data-tour-page="dashboard_advogado">
  <div class="app-shell">
    <?php render_sidebar('advogado', 'dashboard-advogado.php'); ?>

    <main class="app-main" data-tour-step="1" data-tour-title="Visão geral do advogado" data-tour-description="Esta mesa reúne fila, casos, documentos, tarefas e agenda profissional.">
      <?php render_topbar('Mesa do advogado', 'Fila, prioridades, documentos, tarefas e agenda em uma unica area de trabalho.', current_user_name()); ?>

      <section class="lawyer-command" data-tour-step="2" data-tour-title="Casos atribuídos" data-tour-description="Veja sua carga ativa e mantenha cada atendimento com responsável e próximos passos.">
        <article class="command-card command-card-primary">
          <span class="badge badge-info">Fila juridica</span>
          <h2><?= e((string) $openCount) ?> solicitações aguardando aceite</h2>
          <p><?= e((string) $highPriorityOpenCount) ?> estao em prioridade alta. Aceite o que você consegue conduzir com resposta, tarefa e agenda.</p>
          <div class="form-actions">
            <a class="btn btn-primary" href="acompanhar-solicitacoes.php?scope=abertos"><?= icon_svg('case') ?> Ver fila</a>
            <a class="btn btn-outline" href="tarefas.php"><?= icon_svg('check') ?> Tarefas</a>
            <a class="btn btn-soft" href="agenda.php"><?= icon_svg('calendar') ?> Agenda</a>
          </div>
        </article>
        <article class="command-card">
          <span>Casos ativos</span>
          <strong><?= e((string) $assignedActiveCount) ?></strong>
          <p>Casos sob sua responsabilidade que ainda precisam de acompanhamento.</p>
        </article>
        <article class="command-card">
          <span>Próximos compromissos</span>
          <strong><?= e((string) $appointmentCount) ?></strong>
          <p>Atendimentos agendados para não deixar o caso morrer no chat.</p>
        </article>
      </section>

      <section class="grid grid-4" data-tour-step="6" data-tour-title="Prioridade e status" data-tour-description="Use os indicadores para identificar urgências, pendências e volume de trabalho.">
        <?= stat_card('Fila aberta', $openCount, 'help') ?>
        <?= stat_card('Alta prioridade', $highPriorityOpenCount, 'shield') ?>
        <?= stat_card('Tarefas abertas', $taskCount, 'check') ?>
        <?= stat_card('Documentos vinculados', $documentCount, 'file') ?>
      </section>

      <?php if ($highPriorityOpenCount > 0): ?>
        <div class="professional-alert">
          <div>
            <strong>Prioridade real exige decisao.</strong>
            <span>Ha casos abertos de prioridade alta. O custo de ignorar a fila e simples: o cliente perde confianca e a demo parece parada.</span>
          </div>
          <a class="btn btn-primary btn-sm" href="acompanhar-solicitacoes.php?scope=abertos&prioridade=alta">Resolver fila alta</a>
        </div>
      <?php endif; ?>

      <section class="dash-section" data-tour-step="3" data-tour-title="Fila de solicitações" data-tour-description="Aqui aparecem solicitações abertas, ordenadas por urgência e ainda sem responsável.">
        <div class="dash-section-title">
          <h2>Fila para aceitar <?= help_icon('Fila para aceitar', 'Mostra solicitações sem responsável. Aceite apenas casos que você pode conduzir com segurança e disponibilidade.') ?></h2>
          <span class="badge badge-warning">Ordenada por urgencia</span>
        </div>
        <?php if (!$openCases): ?>
          <?= empty_state('Nenhuma solicitacao aberta no momento.') ?>
        <?php else: ?>
          <div class="case-board">
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
                <div class="case-meta-grid">
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
                    <form class="inline-form" action="<?= e(app_url('/backend/public/index.php?rota=/cases/accept')) ?>" method="post" data-tour-step="4" data-tour-title="Aceitar caso" data-tour-description="Aceite somente casos que você consegue conduzir com atenção, prazo e responsabilidade.">
                      <?= csrf_input() ?>
                      <input type="hidden" name="case_id" value="<?= (int) $case['id'] ?>">
                      <button class="btn btn-primary btn-sm" type="submit">Aceitar caso</button>
                    </form>
                    <a class="btn btn-outline btn-sm" href="acompanhar-solicitacoes.php?scope=abertos">Ver detalhes</a>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <section class="dash-section">
        <div class="dash-section-title">
          <h2>Meus casos ativos <?= help_icon('Casos ativos', 'Acompanhe status, prioridade, tarefas, documentos e mensagens dos casos sob sua responsabilidade.') ?></h2>
          <a class="btn btn-soft btn-sm" href="acompanhar-solicitacoes.php?scope=meus">Ver todos</a>
        </div>
        <?php if (!$assignedCases): ?>
          <?= empty_state('Você ainda não possui casos ativos atribuidos.') ?>
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
                <div class="case-meta-grid">
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
                <div class="case-actions">
                  <a class="btn btn-primary btn-sm" href="chat.php?case_id=<?= (int) $case['id'] ?>"><?= icon_svg('chat') ?> Chat</a>
                  <a class="btn btn-outline btn-sm" href="tarefas.php?case_id=<?= (int) $case['id'] ?>"><?= icon_svg('check') ?> Tarefas</a>
                  <a class="btn btn-soft btn-sm" href="agenda.php"><?= icon_svg('calendar') ?> Agenda</a>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <section class="grid grid-2 professional-work-grid" data-tour-step="7" data-tour-title="Tarefas" data-tour-description="Organize os próximos passos dos casos e acompanhe o que ainda está pendente.">
        <article class="dash-section">
          <div class="dash-section-title">
            <h2>Proximas tarefas <?= help_icon('Tarefas', 'Registre próximos passos objetivos e evite incluir dados sensíveis desnecessários na descrição.') ?></h2>
            <span class="badge badge-info"><?= e((string) $taskCount) ?> abertas</span>
          </div>
          <?php if (!$tasks): ?>
            <?= empty_state('Nenhuma tarefa aberta nos seus casos.') ?>
          <?php else: ?>
            <div class="professional-list">
              <?php foreach ($tasks as $task): ?>
                <article class="professional-list-item">
                  <div>
                    <span class="badge <?= e(lawyer_status_badge($task['status'] ?? '')) ?>"><?= e(status_label($task['status'] ?? '')) ?></span>
                    <strong><?= e($task['titulo']) ?></strong>
                    <small><?= e($task['caso']) ?> | <?= e($task['cliente']) ?> | <?= e(status_label($task['prioridade'] ?? '')) ?></small>
                  </div>
                  <a class="btn btn-outline btn-sm" href="tarefas.php?case_id=<?= (int) $task['case_id'] ?>">Abrir</a>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </article>

        <article class="dash-section">
          <div class="dash-section-title">
            <h2>Agenda proxima</h2>
            <a class="btn btn-soft btn-sm" href="agenda.php">Gerenciar</a>
          </div>
          <?php if (!$appointments): ?>
            <?= empty_state('Nenhum agendamento futuro confirmado.') ?>
          <?php else: ?>
            <div class="professional-list">
              <?php foreach ($appointments as $appointment): ?>
                <article class="professional-list-item">
                  <div>
                    <span class="badge badge-info"><?= e(lawyer_datetime($appointment['starts_at'] ?? '')) ?></span>
                    <strong><?= e($appointment['assunto']) ?></strong>
                    <small><?= e($appointment['cliente']) ?><?= !empty($appointment['caso']) ? ' | ' . e($appointment['caso']) : '' ?></small>
                  </div>
                  <?php if (!empty($appointment['case_id'])): ?>
                    <a class="btn btn-outline btn-sm" href="chat.php?case_id=<?= (int) $appointment['case_id'] ?>">Chat</a>
                  <?php else: ?>
                    <a class="btn btn-outline btn-sm" href="agenda.php">Agenda</a>
                  <?php endif; ?>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </article>
      </section>

      <section class="dash-section" data-tour-step="5" data-tour-title="Documentos vinculados" data-tour-description="Revise apenas documentos associados aos seus casos e preserve o sigilo do cliente.">
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
  <?php render_onboarding_assets('dashboard_advogado', '2026.06.11', 'advogado'); ?>
  <?php render_vlibras(); ?>
</body>
</html>
