<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once PROJECT_ROOT_PATH . '/backend/app/services/GeminiService.php';
require_role(['admin']);

function admin_percent(int $value, int $max): int
{
    if ($max <= 0) {
        return 0;
    }

    return max(4, min(100, (int) round(($value / $max) * 100)));
}

function admin_action_severity(string $action): string
{
    if (str_contains($action, 'failed') || str_contains($action, 'error') || str_contains($action, 'delete')) {
        return 'critical';
    }

    if (str_starts_with($action, 'admin.') || str_starts_with($action, 'case.') || str_starts_with($action, 'schedule.')) {
        return 'warning';
    }

    return 'info';
}

function admin_env_configured(string $key): bool
{
    $value = getenv($key);
    if ($value !== false && trim((string) $value) !== '') {
        return true;
    }

    $env = database_env_values(PROJECT_ROOT_PATH . '/backend/.env');
    return trim((string) ($env[$key] ?? '')) !== '';
}

function admin_risk_level(int $value): string
{
    if ($value <= 0) {
        return 'ok';
    }

    return $value >= 3 ? 'critical' : 'warning';
}

$userCount = count_query($pdo, 'SELECT COUNT(*) FROM users');
$activeUserCount = count_query($pdo, "SELECT COUNT(*) FROM users WHERE status = 'ativo'");
$clientCount = count_query($pdo, "SELECT COUNT(*) FROM users WHERE tipo = 'cliente'");
$activeClientCount = count_query($pdo, "SELECT COUNT(*) FROM users WHERE tipo = 'cliente' AND status = 'ativo'");
$lawyerCount = count_query($pdo, "SELECT COUNT(*) FROM users WHERE tipo = 'advogado'");
$activeLawyerCount = count_query($pdo, "SELECT COUNT(*) FROM users WHERE tipo = 'advogado' AND status = 'ativo'");
$internCount = count_query($pdo, "SELECT COUNT(*) FROM users WHERE tipo = 'estagiario'");
$pendingProfessionalCount = count_query(
    $pdo,
    "SELECT COUNT(*) FROM users
     WHERE tipo IN ('advogado', 'estagiario')
       AND status = 'ativo'
       AND oab_verificado = FALSE
       AND COALESCE(status_cna, 'pendente') = 'pendente'
       AND COALESCE(oab, '') <> ''
       AND COALESCE(oab_uf, '') <> ''"
);

$documentCount = count_query($pdo, 'SELECT COUNT(*) FROM documents');
$analyzedDocumentCount = count_query($pdo, 'SELECT COUNT(DISTINCT document_id) FROM ai_results');
$pendingDocumentCount = max(0, $documentCount - $analyzedDocumentCount);
$aiErrorCount = count_query($pdo, "SELECT COUNT(*) FROM audit_logs WHERE action = 'document.ai_error'");

$caseCount = count_query($pdo, 'SELECT COUNT(*) FROM cases');
$openCaseCount = count_query($pdo, "SELECT COUNT(*) FROM cases WHERE status = 'aberto'");
$inProgressCaseCount = count_query($pdo, "SELECT COUNT(*) FROM cases WHERE status = 'em_andamento'");
$closedCaseCount = count_query($pdo, "SELECT COUNT(*) FROM cases WHERE status = 'finalizado'");
$activeCaseCount = count_query($pdo, "SELECT COUNT(*) FROM cases WHERE status <> 'finalizado'");
$criticalCaseCount = count_query($pdo, "SELECT COUNT(*) FROM cases WHERE status <> 'finalizado' AND prioridade = 'alta'");
$unassignedCaseCount = count_query($pdo, "SELECT COUNT(*) FROM cases WHERE status <> 'finalizado' AND advogado_id IS NULL");

$upcomingAppointmentCount = count_query(
    $pdo,
    "SELECT COUNT(*)
     FROM appointments a
     INNER JOIN schedule_slots s ON s.id = a.slot_id
     WHERE a.status = 'agendado'
       AND s.starts_at >= NOW()
       AND s.starts_at < DATE_ADD(NOW(), INTERVAL 7 DAY)"
);
$failedLoginCount = count_query(
    $pdo,
    "SELECT COUNT(*) FROM audit_logs
     WHERE action IN ('auth.login_failed', 'auth.admin_login_failed')
       AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"
);
$externalProcessCount = count_query($pdo, 'SELECT COUNT(*) FROM external_processes');
$lastExternalSync = fetch_one($pdo, 'SELECT MAX(last_synced_at) AS synced_at FROM external_processes');
$lastExternalSyncAt = (string) ($lastExternalSync['synced_at'] ?? '');
$datajudConfigured = admin_env_configured('DATAJUD_API_KEY');
$operationalRisks = [
    [
        'label' => 'OAB pendente',
        'value' => $pendingProfessionalCount,
        'detail' => 'Profissional sem validacao nao entra no sistema completo.',
        'href' => 'validar-oab.php',
        'level' => admin_risk_level($pendingProfessionalCount),
    ],
    [
        'label' => 'Casos sem responsavel',
        'value' => $unassignedCaseCount,
        'detail' => 'Fila aberta sem dono vira gargalo de atendimento.',
        'href' => 'solicitacoes.php?responsavel=sem',
        'level' => admin_risk_level($unassignedCaseCount),
    ],
    [
        'label' => 'Documentos sem IA',
        'value' => $pendingDocumentCount,
        'detail' => 'Documento sem analise enfraquece a promessa central.',
        'href' => 'documentos.php?analysis=pendente',
        'level' => admin_risk_level($pendingDocumentCount),
    ],
    [
        'label' => 'Falhas de login 24h',
        'value' => $failedLoginCount,
        'detail' => 'Tentativas falhas precisam aparecer na auditoria.',
        'href' => 'auditoria.php?severity=critical&action=login_failed',
        'level' => admin_risk_level($failedLoginCount),
    ],
];

$documentTrendRows = fetch_all(
    $pdo,
    'SELECT DATE(created_at) AS day, COUNT(*) AS total
     FROM documents
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(created_at)
     ORDER BY day'
);
$documentTrendMap = array_column($documentTrendRows, 'total', 'day');
$documentTrend = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} days"));
    $documentTrend[] = [
        'label' => date('d/m', strtotime($day)),
        'total' => (int) ($documentTrendMap[$day] ?? 0),
    ];
}
$maxDocumentsPerDay = max(1, ...array_column($documentTrend, 'total'));

$aiTrendRows = fetch_all(
    $pdo,
    'SELECT DATE(created_at) AS day, COUNT(*) AS total
     FROM ai_results
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(created_at)
     ORDER BY day'
);
$aiTrendMap = array_column($aiTrendRows, 'total', 'day');
$aiTrend = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} days"));
    $aiTrend[] = [
        'label' => date('d/m', strtotime($day)),
        'total' => (int) ($aiTrendMap[$day] ?? 0),
    ];
}
$maxAiPerDay = max(1, ...array_column($aiTrend, 'total'));

$caseStatus = [
    ['label' => 'Abertas', 'value' => $openCaseCount, 'class' => 'is-open'],
    ['label' => 'Em andamento', 'value' => $inProgressCaseCount, 'class' => 'is-progress'],
    ['label' => 'Finalizadas', 'value' => $closedCaseCount, 'class' => 'is-done'],
];
$maxCasesByStatus = max(1, $openCaseCount, $inProgressCaseCount, $closedCaseCount);

$otherUserCount = max(0, $userCount - $clientCount - $lawyerCount - $internCount);
$clientDegrees = $userCount > 0 ? (int) round(($clientCount / $userCount) * 360) : 0;
$lawyerDegrees = $userCount > 0 ? (int) round((($clientCount + $lawyerCount) / $userCount) * 360) : 0;
$internDegrees = $userCount > 0 ? (int) round((($clientCount + $lawyerCount + $internCount) / $userCount) * 360) : 0;

$topLawyers = fetch_all(
    $pdo,
    "SELECT u.id, u.nome, COUNT(c.id) AS total
     FROM users u
     INNER JOIN cases c ON c.advogado_id = u.id
     WHERE u.tipo = 'advogado'
     GROUP BY u.id, u.nome
     ORDER BY total DESC, u.nome
     LIMIT 5"
);
$maxLawyerCases = max(1, ...array_map(static fn ($row): int => (int) $row['total'], $topLawyers ?: [['total' => 0]]));

$pendingProfessionals = fetch_all(
    $pdo,
    "SELECT id, nome, email, tipo, oab, oab_uf, oab_status, status_cna, created_at
     FROM users
     WHERE tipo IN ('advogado', 'estagiario')
       AND status = 'ativo'
       AND oab_verificado = FALSE
       AND COALESCE(status_cna, 'pendente') = 'pendente'
       AND COALESCE(oab, '') <> ''
       AND COALESCE(oab_uf, '') <> ''
     ORDER BY created_at ASC
     LIMIT 6"
);

$criticalCases = fetch_all(
    $pdo,
    "SELECT c.id, c.titulo, c.status, c.prioridade, c.created_at, cli.nome AS cliente, adv.nome AS advogado
     FROM cases c
     INNER JOIN users cli ON cli.id = c.cliente_id
     LEFT JOIN users adv ON adv.id = c.advogado_id
     WHERE c.status <> 'finalizado'
       AND (c.prioridade = 'alta' OR c.advogado_id IS NULL OR c.created_at <= DATE_SUB(NOW(), INTERVAL 2 DAY))
     ORDER BY FIELD(c.prioridade, 'alta', 'media', 'baixa'), c.advogado_id IS NULL DESC, c.created_at ASC
     LIMIT 6"
);

$pendingDocuments = fetch_all(
    $pdo,
    "SELECT d.id, d.nome_arquivo, d.tipo_arquivo, d.created_at, u.nome AS usuario
     FROM documents d
     INNER JOIN users u ON u.id = d.user_id
     WHERE NOT EXISTS (SELECT 1 FROM ai_results ar WHERE ar.document_id = d.id)
     ORDER BY d.created_at ASC
     LIMIT 6"
);

$recentAudit = fetch_all(
    $pdo,
    'SELECT a.action, a.entity_type, a.entity_id, a.created_at, a.ip_address, u.nome
     FROM audit_logs a
     LEFT JOIN users u ON u.id = a.user_id
     ORDER BY a.created_at DESC
     LIMIT 6'
);

$geminiService = new GeminiService();
$healthChecks = [
    ['label' => 'DataJud/CNJ', 'status' => $datajudConfigured ? 'ok' : 'warning', 'detail' => $datajudConfigured ? 'Chave configurada' : 'Chave opcional/ausente'],
    ['label' => 'Processos externos', 'status' => $externalProcessCount > 0 ? 'ok' : 'warning', 'detail' => $externalProcessCount > 0 ? $externalProcessCount . ' importado(s)' : 'Sem dados importados'],
    ['label' => 'Banco de dados', 'status' => 'ok', 'detail' => 'Conexão ativa'],
    ['label' => 'Gemini', 'status' => $geminiService->isConfigured() ? 'ok' : 'warning', 'detail' => $geminiService->isConfigured() ? 'Chave configurada' : 'Chave ausente'],
    ['label' => 'Auditoria', 'status' => 'ok', 'detail' => $recentAudit ? 'Eventos registrados' : 'Sem eventos recentes'],
    ['label' => 'OAB', 'status' => $pendingProfessionalCount > 0 ? 'warning' : 'ok', 'detail' => $pendingProfessionalCount > 0 ? $pendingProfessionalCount . ' pendência(s)' : 'Sem fila pendente'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Administração | JusTraduz</title>
  <link rel="icon" href="../assets/img/icon.ico" type="image/x-icon">
  <link rel="stylesheet" href="../assets/css/style.css?v=sidebar-open-button-1">
</head>
<body>
  <div class="app-shell admin-shell">
    <?php render_sidebar('admin', 'dashboard-admin.php', true); ?>

    <main class="app-main">
      <?php render_topbar('Administração', 'Operação, risco e crescimento em tempo quase real.', current_user_name()); ?>

      <section class="admin-hero admin-hero-ops">
        <div>
          <span class="badge badge-info">Central SaaS</span>
          <h2>Comando da plataforma JusTraduz</h2>
          <p>Monitore validações OAB, documentos com IA, solicitações críticas, agenda e auditoria em uma única tela operacional.</p>
        </div>
        <div class="admin-hero-actions">
          <a class="btn btn-primary" href="validar-oab.php"><?= icon_svg('shield') ?> Revisar OAB</a>
          <a class="btn btn-outline" href="solicitacoes.php?prioridade=alta"><?= icon_svg('case') ?> Casos críticos</a>
          <a class="btn btn-soft" href="documentos.php?analysis=pendente"><?= icon_svg('folder') ?> IA pendente</a>
        </div>
      </section>

      <section class="grid grid-4">
        <?= stat_card('Clientes ativos', $activeClientCount . '/' . $clientCount, 'users') ?>
        <?= stat_card('Advogados ativos', $activeLawyerCount . '/' . $lawyerCount, 'shield') ?>
        <?= stat_card('OAB pendentes', $pendingProfessionalCount, 'help') ?>
        <?= stat_card('Docs analisados', $analyzedDocumentCount . '/' . $documentCount, 'chart') ?>
      </section>

      <section class="admin-alert-strip">
        <a class="admin-alert-tile" href="solicitacoes.php?prioridade=alta">
          <span>Solicitações críticas</span>
          <strong><?= e((string) $criticalCaseCount) ?></strong>
        </a>
        <a class="admin-alert-tile" href="solicitacoes.php?status=aberto">
          <span>Sem responsável</span>
          <strong><?= e((string) $unassignedCaseCount) ?></strong>
        </a>
        <a class="admin-alert-tile" href="../agenda.php">
          <span>Agenda 7 dias</span>
          <strong><?= e((string) $upcomingAppointmentCount) ?></strong>
        </a>
        <a class="admin-alert-tile" href="auditoria.php?action=failed">
          <span>Falhas login 24h</span>
          <strong><?= e((string) $failedLoginCount) ?></strong>
        </a>
      </section>

      <section class="admin-risk-board">
        <?php foreach ($operationalRisks as $risk): ?>
          <a class="admin-risk-card is-<?= e($risk['level']) ?>" href="<?= e($risk['href']) ?>">
            <div>
              <span><?= e($risk['label']) ?></span>
              <strong><?= e((string) $risk['value']) ?></strong>
            </div>
            <p><?= e($risk['detail']) ?></p>
          </a>
        <?php endforeach; ?>
      </section>

      <section class="admin-dashboard-grid">
        <article class="card admin-chart-card admin-chart-card-wide">
          <div class="dash-section-title">
            <h2>Documentos enviados por dia</h2>
            <a class="btn btn-soft btn-sm" href="documentos.php">Abrir</a>
          </div>
          <div class="bar-chart" aria-label="Documentos enviados nos últimos 7 dias">
            <?php foreach ($documentTrend as $point): ?>
              <div class="bar-column">
                <span class="bar-fill" style="--bar: <?= admin_percent($point['total'], $maxDocumentsPerDay) ?>%"></span>
                <strong><?= e((string) $point['total']) ?></strong>
                <small><?= e($point['label']) ?></small>
              </div>
            <?php endforeach; ?>
          </div>
        </article>

        <article class="card admin-chart-card">
          <div class="dash-section-title">
            <h2>Usuários por perfil</h2>
          </div>
          <div class="donut-wrap">
            <div class="donut-chart" style="--client-end: <?= $clientDegrees ?>deg; --lawyer-end: <?= $lawyerDegrees ?>deg; --intern-end: <?= $internDegrees ?>deg">
              <span><?= e((string) $userCount) ?></span>
            </div>
            <div class="chart-legend">
              <span><i class="legend-client"></i>Clientes: <?= e((string) $clientCount) ?></span>
              <span><i class="legend-lawyer"></i>Advogados: <?= e((string) $lawyerCount) ?></span>
              <span><i class="legend-intern"></i>Estagiários: <?= e((string) $internCount) ?></span>
              <span><i class="legend-other"></i>Outros: <?= e((string) $otherUserCount) ?></span>
            </div>
          </div>
        </article>

        <article class="card admin-chart-card">
          <div class="dash-section-title">
            <h2>Solicitações por status</h2>
            <span class="badge badge-info"><?= e((string) $caseCount) ?> total</span>
          </div>
          <div class="horizontal-bars">
            <?php foreach ($caseStatus as $item): ?>
              <div class="hbar-row <?= e($item['class']) ?>">
                <div><span><?= e($item['label']) ?></span><strong><?= e((string) $item['value']) ?></strong></div>
                <span class="hbar-track"><i style="--bar: <?= admin_percent((int) $item['value'], $maxCasesByStatus) ?>%"></i></span>
              </div>
            <?php endforeach; ?>
          </div>
        </article>

        <article class="card admin-chart-card">
          <div class="dash-section-title">
            <h2>Uso de IA por dia</h2>
            <span class="badge <?= $aiErrorCount > 0 ? 'badge-warning' : 'badge-success' ?>"><?= e((string) $aiErrorCount) ?> erro(s)</span>
          </div>
          <div class="bar-chart bar-chart-compact" aria-label="Análises por IA nos últimos 7 dias">
            <?php foreach ($aiTrend as $point): ?>
              <div class="bar-column">
                <span class="bar-fill" style="--bar: <?= admin_percent($point['total'], $maxAiPerDay) ?>%"></span>
                <strong><?= e((string) $point['total']) ?></strong>
                <small><?= e($point['label']) ?></small>
              </div>
            <?php endforeach; ?>
          </div>
        </article>

        <article class="card admin-chart-card">
          <div class="dash-section-title">
            <h2>Advogados com mais casos</h2>
            <a class="btn btn-soft btn-sm" href="usuarios.php?tipo=advogado">Ver</a>
          </div>
          <?php if (!$topLawyers): ?>
            <div class="inline-empty"><p>Nenhum caso atribuído ainda.</p></div>
          <?php else: ?>
            <div class="horizontal-bars">
              <?php foreach ($topLawyers as $lawyer): ?>
                <div class="hbar-row">
                  <div><span><?= e($lawyer['nome']) ?></span><strong><?= e((string) $lawyer['total']) ?></strong></div>
                  <span class="hbar-track"><i style="--bar: <?= admin_percent((int) $lawyer['total'], $maxLawyerCases) ?>%"></i></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </article>
      </section>

      <section class="grid grid-3 admin-work-grid">
        <article class="dash-section">
          <div class="dash-section-title">
            <h2>Fila OAB</h2>
            <a class="btn btn-soft btn-sm" href="validar-oab.php">Abrir</a>
          </div>
          <?php if (!$pendingProfessionals): ?>
            <?= empty_state('Nenhum profissional pendente.') ?>
          <?php else: ?>
            <div class="admin-review-list">
              <?php foreach ($pendingProfessionals as $professional): ?>
                <div class="review-item">
                  <div>
                    <strong><?= e($professional['nome']) ?></strong>
                    <span><?= e(ucfirst($professional['tipo'])) ?> · OAB/<?= e($professional['oab_uf']) ?> <?= e($professional['oab']) ?></span>
                    <small><?= e($professional['oab_status'] ?: 'Aguardando revisão') ?></small>
                  </div>
                  <div class="review-actions">
                    <form action="<?= e(app_url('/backend/public/index.php?rota=/admin/professionals/oab')) ?>" method="post">
                      <?= csrf_input() ?>
                      <input type="hidden" name="user_id" value="<?= (int) $professional['id'] ?>">
                      <input type="hidden" name="action" value="approve">
                      <input type="hidden" name="justificativa" value="Aprovado pela central admin apos validacao manual.">
                      <button class="btn btn-success btn-sm" type="submit"><?= icon_svg('check') ?> Aprovar</button>
                    </form>
                    <form action="<?= e(app_url('/backend/public/index.php?rota=/admin/professionals/oab')) ?>" method="post" onsubmit="return confirm('Reprovar esta OAB vai excluir a conta do profissional. Continuar?');">
                      <?= csrf_input() ?>
                      <input type="hidden" name="user_id" value="<?= (int) $professional['id'] ?>">
                      <input type="hidden" name="action" value="reject">
                      <input type="hidden" name="justificativa" value="OAB reprovada na revisão manual administrativa.">
                      <button class="btn btn-outline btn-sm" type="submit">Excluir</button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </article>

        <article class="dash-section">
          <div class="dash-section-title">
            <h2>Solicitações críticas</h2>
            <a class="btn btn-soft btn-sm" href="solicitacoes.php?prioridade=alta">Abrir</a>
          </div>
          <?php if (!$criticalCases): ?>
            <?= empty_state('Nenhuma solicitação crítica agora.') ?>
          <?php else: ?>
            <div class="admin-review-list">
              <?php foreach ($criticalCases as $case): ?>
                <a class="review-item review-item-link" href="../chat.php?case_id=<?= (int) $case['id'] ?>">
                  <div>
                    <strong><?= e($case['titulo']) ?></strong>
                    <span><?= e($case['cliente']) ?> · <?= e($case['advogado'] ?? 'Sem responsável') ?></span>
                    <small><?= e(status_label($case['status'])) ?> · prioridade <?= e($case['prioridade']) ?></small>
                  </div>
                  <span class="badge <?= $case['prioridade'] === 'alta' ? 'badge-warning' : 'badge-info' ?>"><?= e(date('d/m', strtotime($case['created_at']))) ?></span>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </article>

        <article class="dash-section">
          <div class="dash-section-title">
            <h2>Documentos sem IA</h2>
            <a class="btn btn-soft btn-sm" href="documentos.php?analysis=pendente">Abrir</a>
          </div>
          <?php if (!$pendingDocuments): ?>
            <?= empty_state('Todos os documentos têm análise.') ?>
          <?php else: ?>
            <div class="admin-review-list">
              <?php foreach ($pendingDocuments as $document): ?>
                <a class="review-item review-item-link" href="../visualizar-documento.php?id=<?= (int) $document['id'] ?>">
                  <div>
                    <strong><?= e($document['nome_arquivo']) ?></strong>
                    <span><?= e($document['usuario']) ?> · <?= e(strtoupper($document['tipo_arquivo'] ?? '')) ?></span>
                    <small>Enviado em <?= e(date('d/m/Y H:i', strtotime($document['created_at']))) ?></small>
                  </div>
                  <span class="badge badge-warning">Pendente</span>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </article>
      </section>

      <section class="grid grid-2 admin-panels">
        <article class="dash-section">
          <div class="dash-section-title">
            <h2>Auditoria recente</h2>
            <a class="btn btn-soft btn-sm" href="auditoria.php">Ver logs</a>
          </div>
          <?php if (!$recentAudit): ?>
            <?= empty_state('Nenhum evento de auditoria registrado.') ?>
          <?php else: ?>
            <div class="admin-audit-feed">
              <?php foreach ($recentAudit as $log): ?>
                <?php $severity = admin_action_severity((string) $log['action']); ?>
                <a href="auditoria.php?action=<?= urlencode((string) $log['action']) ?>" class="audit-feed-item is-<?= e($severity) ?>">
                  <span class="audit-dot"></span>
                  <div>
                    <strong><?= e($log['action']) ?></strong>
                    <small><?= e($log['nome'] ?: 'Sistema') ?> · <?= e($log['entity_type'] ?: '-') ?><?= $log['entity_id'] ? ' #' . (int) $log['entity_id'] : '' ?></small>
                  </div>
                  <time><?= e(date('d/m H:i', strtotime($log['created_at']))) ?></time>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </article>

        <article class="dash-section">
          <div class="dash-section-title">
            <h2>Saúde das integrações</h2>
            <span class="badge <?= ($pendingProfessionalCount > 0 || $failedLoginCount > 0) ? 'badge-warning' : 'badge-success' ?>">Monitorado</span>
          </div>
          <div class="health-grid">
            <?php foreach ($healthChecks as $check): ?>
              <div class="health-item is-<?= e($check['status']) ?>">
                <span></span>
                <div>
                  <strong><?= e($check['label']) ?></strong>
                  <small><?= e($check['detail']) ?></small>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </article>
      </section>
    </main>
  </div>
</body>
</html>
