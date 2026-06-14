<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_role(['estagiario']);

$userId = current_user_id();

function intern_datetime(?string $value): string
{
    if (!$value) {
        return '-';
    }

    return date('d/m/Y H:i', strtotime($value));
}

function intern_slot_badge(?string $status): string
{
    return match ($status) {
        'livre' => 'badge-success',
        'ocupado', 'agendado' => 'badge-info',
        default => 'badge-warning',
    };
}

$futureSlotCount = count_query($pdo, 'SELECT COUNT(*) FROM schedule_slots WHERE professional_id = ? AND starts_at >= NOW()', [$userId]);
$freeSlotCount = count_query($pdo, "SELECT COUNT(*) FROM schedule_slots WHERE professional_id = ? AND status = 'livre' AND starts_at >= NOW()", [$userId]);
$blockedSlotCount = count_query($pdo, "SELECT COUNT(*) FROM schedule_slots WHERE professional_id = ? AND status = 'bloqueado' AND starts_at >= NOW()", [$userId]);
$appointmentCount = count_query(
    $pdo,
    "SELECT COUNT(*)
     FROM appointments a
     INNER JOIN schedule_slots s ON s.id = a.slot_id
     WHERE s.professional_id = ? AND a.status = 'agendado'",
    [$userId]
);

$slots = fetch_all(
    $pdo,
    "SELECT id, titulo, starts_at, ends_at, status
     FROM schedule_slots
     WHERE professional_id = ? AND starts_at >= NOW()
     ORDER BY starts_at ASC
     LIMIT 8",
    [$userId]
);

$appointments = fetch_all(
    $pdo,
    "SELECT a.id, a.assunto, a.status, s.starts_at, s.ends_at, cli.nome AS cliente, c.titulo AS caso
     FROM appointments a
     INNER JOIN schedule_slots s ON s.id = a.slot_id
     INNER JOIN users cli ON cli.id = a.client_id
     LEFT JOIN cases c ON c.id = a.case_id
     WHERE s.professional_id = ?
     ORDER BY s.starts_at ASC
     LIMIT 8",
    [$userId]
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mesa do estagiário | JusTraduz</title>
  <link rel="icon" href="assets/img/icon.ico" type="image/x-icon">
  <link rel="stylesheet" href="assets/css/style.css?v=sidebar-open-button-1">
</head>
<body data-tour-page="dashboard_estagiario">
  <div class="app-shell">
    <?php render_sidebar('estagiario', 'dashboard-estagiario.php'); ?>

    <main class="app-main" data-tour-step="1" data-tour-title="Visão geral do estagiário" data-tour-description="Esta área é assistiva e possui permissões menores que as de um advogado.">
      <?php render_topbar('Mesa do estagiário', 'Acesso assistivo restrito à agenda própria e aos dados do próprio perfil.', current_user_name()); ?>

      <section class="professional-alert professional-alert-locked" data-tour-step="5" data-tour-title="Limites de atuação" data-tour-description="Sem atribuição formal, você não acessa casos, documentos ou chats de clientes.">
        <div>
          <strong>Permissao limitada de proposito.</strong>
          <span>Sem atribuição formal, estagiario não acessa casos, documentos, tarefas ou chat de clientes. Isso protege o cliente e evita papel de admin disfarcido.</span>
        </div>
        <a class="btn btn-primary btn-sm" href="agenda.php"><?= icon_svg('calendar') ?> Minha agenda</a>
      </section>

      <section class="grid grid-4" data-tour-step="4" data-tour-title="Permissões do perfil" data-tour-description="Os indicadores refletem apenas recursos autorizados para sua conta.">
        <?= stat_card('Horários futuros', $futureSlotCount, 'calendar') ?>
        <?= stat_card('Livres', $freeSlotCount, 'check') ?>
        <?= stat_card('Agendamentos', $appointmentCount, 'case') ?>
        <?= stat_card('Bloqueados', $blockedSlotCount, 'shield') ?>
      </section>

      <section class="grid grid-2 professional-work-grid">
        <article class="dash-section" data-tour-step="2" data-tour-title="Agenda própria" data-tour-description="Cadastre e acompanhe somente os horários vinculados ao seu perfil.">
          <div class="dash-section-title">
            <h2>Minha agenda <?= help_icon('Agenda própria', 'Gerencie somente seus horários autorizados. A agenda não amplia seu acesso a casos ou documentos.') ?></h2>
            <a class="btn btn-soft btn-sm" href="agenda.php">Gerenciar horários</a>
          </div>
          <?php if (!$slots): ?>
            <?= empty_state('Nenhum horário futuro cadastrado na sua agenda.') ?>
          <?php else: ?>
            <div class="professional-list">
              <?php foreach ($slots as $slot): ?>
                <article class="professional-list-item">
                  <div>
                    <span class="badge <?= e(intern_slot_badge($slot['status'] ?? '')) ?>"><?= e(status_label($slot['status'] ?? '')) ?></span>
                    <strong><?= e($slot['titulo'] ?: 'Horário de atendimento') ?></strong>
                    <small><?= e(intern_datetime($slot['starts_at'] ?? '')) ?> até <?= e(date('H:i', strtotime((string) $slot['ends_at']))) ?></small>
                  </div>
                  <a class="btn btn-outline btn-sm" href="agenda.php">Abrir</a>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </article>

        <article class="dash-section" data-tour-step="3" data-tour-title="Agendamentos" data-tour-description="Veja compromissos da sua agenda sem assumir atribuições exclusivas de advogado.">
          <div class="dash-section-title">
            <h2>Agendamentos <?= help_icon('Agendamentos', 'Consulte compromissos da sua agenda e respeite os limites definidos pela supervisão.') ?></h2>
            <span class="badge badge-info"><?= e((string) $appointmentCount) ?> ativos</span>
          </div>
          <?php if (!$appointments): ?>
            <?= empty_state('Nenhum agendamento encontrado para sua agenda.') ?>
          <?php else: ?>
            <div class="professional-list">
              <?php foreach ($appointments as $appointment): ?>
                <article class="professional-list-item">
                  <div>
                    <span class="badge <?= e(intern_slot_badge($appointment['status'] ?? '')) ?>"><?= e(status_label($appointment['status'] ?? '')) ?></span>
                    <strong><?= e($appointment['assunto']) ?></strong>
                    <small><?= e($appointment['cliente']) ?> | <?= e(intern_datetime($appointment['starts_at'] ?? '')) ?><?= !empty($appointment['caso']) ? ' | ' . e($appointment['caso']) : '' ?></small>
                  </div>
                  <a class="btn btn-outline btn-sm" href="agenda.php">Agenda</a>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </article>
      </section>

      <section class="dash-section">
        <div class="dash-section-title">
          <h2>Permissoes do perfil <?= help_icon('Limites de acesso', 'O perfil de estagiário é assistivo e não possui os mesmos poderes de um advogado ou administrador.') ?></h2>
          <span class="badge badge-warning">Acesso assistivo</span>
        </div>
        <div class="permission-grid">
          <article class="permission-card is-allowed">
            <?= icon_svg('calendar') ?>
            <strong>Agenda própria</strong>
            <span>Criar, bloquear e acompanhar horários do próprio usuário.</span>
          </article>
          <article class="permission-card is-allowed">
            <?= icon_svg('user') ?>
            <strong>Perfil</strong>
            <span>Manter dados profissionais e OAB atualizados.</span>
          </article>
          <article class="permission-card is-denied">
            <?= icon_svg('case') ?>
            <strong>Casos de clientes</strong>
            <span>Bloqueado até existir atribuição formal no sistema.</span>
          </article>
          <article class="permission-card is-denied">
            <?= icon_svg('file') ?>
            <strong>Documentos e chat</strong>
            <span>Bloqueado para preservar sigilo e responsabilidade juridica.</span>
          </article>
        </div>
      </section>
    </main>
  </div>
  <?php render_onboarding_assets('dashboard_estagiario', '2026.06.11', 'estagiario'); ?>
  <?php render_vlibras(); ?>
</body>
</html>
