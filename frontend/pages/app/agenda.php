<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();

$type = current_user_type();
$userId = current_user_id();
$professionalFilter = (int) ($_GET['professional_id'] ?? 0);
$roleFilter = trim((string) ($_GET['perfil'] ?? ''));

function agenda_datetime_label(?string $value): string
{
    if (!$value) {
        return '-';
    }

    return date('d/m/Y H:i', strtotime($value));
}

function agenda_time_range(array $row): string
{
    return date('H:i', strtotime((string) $row['starts_at'])) . ' - ' . date('H:i', strtotime((string) $row['ends_at']));
}

function agenda_role_label(?string $role): string
{
    return $role === 'advogado' ? 'Advogado' : 'Profissional';
}

function agenda_status_badge_class(string $status): string
{
    return match ($status) {
        'agendado', 'livre' => 'badge-success',
        'concluido' => 'badge-info',
        'ocupado', 'bloqueado' => 'badge-warning',
        'cancelado' => 'badge-danger',
        default => 'badge-info',
    };
}

function agenda_status_label(string $status): string
{
    return match ($status) {
        'livre' => 'Livre',
        'ocupado' => 'Ocupado',
        'bloqueado' => 'Bloqueado',
        'agendado' => 'Agendado',
        'cancelado' => 'Cancelado',
        'concluido' => 'Concluido',
        default => ucfirst($status),
    };
}

$professionals = fetch_all(
    $pdo,
    "SELECT id, nome, tipo, oab, oab_uf
     FROM users
     WHERE tipo = 'advogado'
       AND status = 'ativo'
       AND oab_verificado = TRUE
     ORDER BY nome"
);

$clientsCases = $type === 'cliente'
    ? fetch_all(
        $pdo,
        "SELECT id, titulo
         FROM cases
         WHERE cliente_id = ? AND status <> 'finalizado'
         ORDER BY FIELD(prioridade, 'alta', 'media', 'baixa'), created_at DESC",
        [$userId]
    )
    : [];

$freeSlots = [];
$slots = [];
$appointments = [];

if ($type === 'cliente') {
    $where = [
        "s.status = 'livre'",
        's.starts_at >= NOW()',
        "u.status = 'ativo'",
        "u.tipo = 'advogado'",
        'u.oab_verificado = TRUE',
    ];
    $params = [];

    if ($professionalFilter > 0) {
        $where[] = 'u.id = ?';
        $params[] = $professionalFilter;
    }

    if ($roleFilter === 'advogado') {
        $where[] = 'u.tipo = ?';
        $params[] = $roleFilter;
    }

    $freeSlots = fetch_all(
        $pdo,
        'SELECT s.id, s.professional_id, s.starts_at, s.ends_at, s.titulo, u.nome AS profissional, u.tipo, u.oab, u.oab_uf
         FROM schedule_slots s
         INNER JOIN users u ON u.id = s.professional_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY s.starts_at ASC
         LIMIT 60',
        $params
    );

    $appointments = fetch_all(
        $pdo,
        'SELECT a.*, s.starts_at, s.ends_at, pro.nome AS profissional, pro.tipo, c.titulo AS caso
         FROM appointments a
         INNER JOIN schedule_slots s ON s.id = a.slot_id
         INNER JOIN users pro ON pro.id = s.professional_id
         LEFT JOIN cases c ON c.id = a.case_id
         WHERE a.client_id = ?
         ORDER BY s.starts_at DESC
         LIMIT 80',
        [$userId]
    );
} elseif ($type === 'advogado') {
    $slots = fetch_all(
        $pdo,
        'SELECT s.id, s.starts_at, s.ends_at, s.status, s.titulo,
                a.id AS appointment_id, a.assunto, a.observacoes, a.status AS appointment_status, a.case_id,
                cli.nome AS cliente, c.titulo AS caso
         FROM schedule_slots s
         LEFT JOIN appointments a ON a.slot_id = s.id AND a.status <> "cancelado"
         LEFT JOIN users cli ON cli.id = a.client_id
         LEFT JOIN cases c ON c.id = a.case_id
         WHERE s.professional_id = ?
         ORDER BY s.starts_at DESC
         LIMIT 120',
        [$userId]
    );

    $appointments = array_values(array_filter($slots, static fn (array $slot): bool => !empty($slot['appointment_id'])));
} else {
    $where = ['1 = 1'];
    $params = [];

    if ($professionalFilter > 0) {
        $where[] = 's.professional_id = ?';
        $params[] = $professionalFilter;
    }

    if ($roleFilter === 'advogado') {
        $where[] = 'pro.tipo = ?';
        $params[] = $roleFilter;
    }

    $slots = fetch_all(
        $pdo,
        'SELECT s.id, s.professional_id, s.starts_at, s.ends_at, s.status, s.titulo,
                pro.nome AS profissional, pro.tipo,
                a.id AS appointment_id, a.assunto, a.status AS appointment_status, a.case_id,
                cli.nome AS cliente, c.titulo AS caso
         FROM schedule_slots s
         INNER JOIN users pro ON pro.id = s.professional_id
         LEFT JOIN appointments a ON a.slot_id = s.id AND a.status <> "cancelado"
         LEFT JOIN users cli ON cli.id = a.client_id
         LEFT JOIN cases c ON c.id = a.case_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY s.starts_at DESC
         LIMIT 160',
        $params
    );

    $appointments = array_values(array_filter($slots, static fn (array $slot): bool => !empty($slot['appointment_id'])));
}

$activeAppointments = count(array_filter($appointments, static function (array $appointment): bool {
    $status = $appointment['appointment_status'] ?? $appointment['status'] ?? '';
    return $status === 'agendado';
}));
$canManageSlots = $type === 'advogado';
$hasAgendaFilters = $professionalFilter > 0 || $roleFilter !== '';
$calendarSubtitle = $type === 'cliente'
    ? 'Encontre horário livre, vincule a um caso e confirme atendimento.'
    : ($type === 'admin' ? 'Visao operacional de disponibilidade e atendimentos.' : 'Crie horários, bloqueie agenda e acompanhe atendimentos.');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta name="robots" content="noindex, nofollow">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Agenda | JusTraduz</title>
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
  <link rel="stylesheet" href="assets/css/style.css?v=global-responsive-20260628">
  <link rel="stylesheet" href="assets/css/agenda.css?v=agenda-book-full-1">
  <script src="assets/js/pwa.js" defer></script>
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar($type, 'agenda.php'); ?>

    <main class="app-main agenda-page">
      <?php render_topbar('Agenda', $calendarSubtitle, current_user_name()); ?>

      <section class="agenda-summary-strip" aria-label="Resumo da agenda">
        <?php if ($type === 'cliente'): ?>
          <article class="agenda-summary-card">
            <?= icon_svg('calendar') ?>
            <span>Horários livres</span>
            <strong><?= e((string) count($freeSlots)) ?></strong>
          </article>
          <article class="agenda-summary-card">
            <?= icon_svg('case') ?>
            <span>Meus atendimentos</span>
            <strong><?= e((string) count($appointments)) ?></strong>
          </article>
          <article class="agenda-summary-card">
            <?= icon_svg('chat') ?>
            <span>Ativos</span>
            <strong><?= e((string) $activeAppointments) ?></strong>
          </article>
          <article class="agenda-summary-card">
            <?= icon_svg('help') ?>
            <span>Casos abertos</span>
            <strong><?= e((string) count($clientsCases)) ?></strong>
          </article>
        <?php else: ?>
          <article class="agenda-summary-card">
            <?= icon_svg('calendar') ?>
            <span>Horários exibidos</span>
            <strong><?= e((string) count($slots)) ?></strong>
          </article>
          <article class="agenda-summary-card">
            <?= icon_svg('case') ?>
            <span>Atendimentos</span>
            <strong><?= e((string) count($appointments)) ?></strong>
          </article>
          <article class="agenda-summary-card">
            <?= icon_svg('chat') ?>
            <span>Ativos</span>
            <strong><?= e((string) $activeAppointments) ?></strong>
          </article>
          <article class="agenda-summary-card">
            <?= icon_svg('users') ?>
            <span>Profissionais</span>
            <strong><?= e((string) count($professionals)) ?></strong>
          </article>
        <?php endif; ?>
      </section>

      <?php if ($type === 'cliente' || $type === 'admin'): ?>
        <details class="agenda-tools"<?= $hasAgendaFilters ? ' open' : '' ?>>
          <summary>Filtros</summary>
          <form class="card agenda-filter" method="get">
            <div class="field">
              <label for="professional_id">Profissional</label>
              <select class="select" id="professional_id" name="professional_id">
                <option value="">Todos</option>
                <?php foreach ($professionals as $professional): ?>
                  <option value="<?= (int) $professional['id'] ?>" <?= $professionalFilter === (int) $professional['id'] ? 'selected' : '' ?>>
                    <?= e($professional['nome']) ?> - <?= e(agenda_role_label($professional['tipo'])) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="perfil">Perfil</label>
              <select class="select" id="perfil" name="perfil">
                <option value="">Todos</option>
                <option value="advogado" <?= $roleFilter === 'advogado' ? 'selected' : '' ?>>Advogado</option>
              </select>
            </div>
            <div class="form-actions">
              <button class="btn btn-primary" type="submit">Filtrar</button>
              <a class="btn btn-outline" href="agenda.php">Limpar</a>
            </div>
          </form>
        </details>
      <?php endif; ?>

      <?php if ($canManageSlots): ?>
        <details class="agenda-tools agenda-create-section">
          <summary>Abrir horário</summary>
          <div class="card agenda-create-card">
            <div>
              <span class="badge badge-info">Disponibilidade</span>
              <h2>Abrir horário de atendimento</h2>
              <p>Crie horários livres para clientes ou bloqueios internos. Conflitos de horário sao recusados pelo backend.</p>
            </div>
            <form class="agenda-inline-form" action="<?= e(app_url('/backend/public/index.php?rota=/schedule/slots/create')) ?>" method="post">
              <?= csrf_input() ?>
              <div class="field">
                <label for="starts_at">Inicio</label>
                <input class="input" id="starts_at" name="starts_at" type="datetime-local" required>
              </div>
              <div class="field">
                <label for="ends_at">Fim</label>
                <input class="input" id="ends_at" name="ends_at" type="datetime-local" required>
              </div>
              <div class="field">
                <label for="slot_title_static">Titulo</label>
                <input class="input" id="slot_title_static" name="titulo" placeholder="Atendimento inicial">
              </div>
              <div class="field">
                <label for="slot_status_static">Status</label>
                <select class="select" id="slot_status_static" name="status">
                  <option value="livre">Livre para cliente</option>
                  <option value="bloqueado">Bloqueio interno</option>
                </select>
              </div>
              <button class="btn btn-primary" type="submit"><?= icon_svg('calendar') ?> Criar horário</button>
            </form>
          </div>
        </details>
      <?php endif; ?>

      <section class="dash-section agenda-calendar-section">
        <div class="dash-section-title">
          <h2>Calendario</h2>
          <span class="badge badge-info">Clique no contador do dia para ver detalhes</span>
        </div>
        <div id="calendar" class="card agenda-calendar-card"></div>
      </section>

      <?php if ($type === 'cliente'): ?>
        <details class="dash-section agenda-list-section" open>
          <summary class="dash-section-title">
            <h2>Horários disponíveis</h2>
            <span class="badge badge-success"><?= e((string) count($freeSlots)) ?> livres</span>
          </summary>

          <?php if (!$freeSlots): ?>
            <?= empty_state('Nenhum horário livre encontrado para os filtros atuais.') ?>
          <?php else: ?>
            <div class="agenda-slot-grid">
              <?php foreach ($freeSlots as $slot): ?>
                <article class="agenda-slot-card" id="slot-<?= (int) $slot['id'] ?>" data-slot-card="<?= (int) $slot['id'] ?>">
                  <div class="agenda-slot-head">
                    <div>
                      <span class="badge badge-success">Livre</span>
                      <h3><?= e($slot['titulo'] ?: 'Atendimento jurídico') ?></h3>
                    </div>
                    <strong><?= e(agenda_time_range($slot)) ?></strong>
                  </div>
                  <div class="agenda-slot-meta">
                    <div><span>Data</span><strong><?= e(date('d/m/Y', strtotime((string) $slot['starts_at']))) ?></strong></div>
                    <div><span>Profissional</span><strong><?= e($slot['profissional']) ?></strong></div>
                    <div><span>Perfil</span><strong><?= e(agenda_role_label($slot['tipo'])) ?></strong></div>
                    <div><span>OAB</span><strong><?= e(trim((string) ($slot['oab'] ?? '') . '/' . (string) ($slot['oab_uf'] ?? ''), '/')) ?></strong></div>
                  </div>
                  <form class="agenda-book-form" action="<?= e(app_url('/backend/public/index.php?rota=/schedule/book')) ?>" method="post">
                    <?= csrf_input() ?>
                    <input type="hidden" name="slot_id" value="<?= (int) $slot['id'] ?>">
                    <div class="field">
                      <label for="case_<?= (int) $slot['id'] ?>">Caso vinculado</label>
                      <select class="select" id="case_<?= (int) $slot['id'] ?>" name="case_id">
                        <option value="">Sem caso especifico</option>
                        <?php foreach ($clientsCases as $case): ?>
                          <option value="<?= (int) $case['id'] ?>"><?= e($case['titulo']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="field">
                      <label for="assunto_<?= (int) $slot['id'] ?>">Assunto</label>
                      <input class="input" id="assunto_<?= (int) $slot['id'] ?>" name="assunto" required placeholder="Ex.: revisar notificacao">
                    </div>
                    <div class="field agenda-field-full">
                      <label for="obs_<?= (int) $slot['id'] ?>">Observacoes</label>
                      <textarea class="textarea textarea-sm" id="obs_<?= (int) $slot['id'] ?>" name="observacoes" placeholder="Resumo objetivo para o profissional"></textarea>
                    </div>
                    <button class="btn btn-primary" type="submit"><?= icon_svg('calendar') ?> Agendar</button>
                  </form>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </details>

        <details class="dash-section agenda-list-section">
          <summary class="dash-section-title">
            <h2>Meus atendimentos</h2>
            <span class="badge badge-info"><?= e((string) count($appointments)) ?> registros</span>
          </summary>

          <?php if (!$appointments): ?>
            <?= empty_state('Você ainda não tem atendimentos agendados.') ?>
          <?php else: ?>
            <div class="agenda-list">
              <?php foreach ($appointments as $appointment): ?>
                <article class="agenda-row">
                  <div>
                    <span class="badge <?= e(agenda_status_badge_class((string) $appointment['status'])) ?>"><?= e(agenda_status_label((string) $appointment['status'])) ?></span>
                    <h3><?= e($appointment['assunto']) ?></h3>
                    <p><?= e(agenda_datetime_label($appointment['starts_at'])) ?> com <?= e($appointment['profissional']) ?><?= !empty($appointment['caso']) ? ' | Caso: ' . e($appointment['caso']) : '' ?></p>
                  </div>
                  <?php if (($appointment['status'] ?? '') === 'agendado'): ?>
                    <form class="inline-form" action="<?= e(app_url('/backend/public/index.php?rota=/schedule/appointments/update')) ?>" method="post">
                      <?= csrf_input() ?>
                      <input type="hidden" name="appointment_id" value="<?= (int) $appointment['id'] ?>">
                      <input type="hidden" name="status" value="cancelado">
                      <button class="btn btn-outline btn-sm" type="submit">Cancelar</button>
                    </form>
                  <?php endif; ?>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </details>
      <?php else: ?>
        <details class="dash-section agenda-list-section" open>
          <summary class="dash-section-title">
            <h2><?= $type === 'admin' ? 'Agenda operacional' : 'Minha disponibilidade' ?></h2>
            <span class="badge badge-info"><?= e((string) count($slots)) ?> slots</span>
          </summary>

          <?php if (!$slots): ?>
            <?= empty_state($type === 'admin' ? 'Nenhum horário encontrado para os filtros atuais.' : 'Você ainda não cadastrou horários.') ?>
          <?php else: ?>
            <div class="agenda-list">
              <?php foreach ($slots as $slot): ?>
                <?php
                  $slotStatus = (string) ($slot['appointment_status'] ?: $slot['status']);
                  $hasAppointment = !empty($slot['appointment_id']);
                  $isFuture = strtotime((string) $slot['starts_at']) > time();
                ?>
                <article class="agenda-row">
                  <div>
                    <span class="badge <?= e(agenda_status_badge_class($slotStatus)) ?>"><?= e(agenda_status_label($slotStatus)) ?></span>
                    <h3><?= e($slot['assunto'] ?: ($slot['titulo'] ?: 'Horário de agenda')) ?></h3>
                    <p>
                      <?= e(agenda_datetime_label($slot['starts_at'])) ?> | <?= e(agenda_time_range($slot)) ?>
                      <?php if ($type === 'admin' && !empty($slot['profissional'])): ?>
                        | <?= e($slot['profissional']) ?> (<?= e(agenda_role_label($slot['tipo'] ?? '')) ?>)
                      <?php endif; ?>
                      <?php if (!empty($slot['cliente'])): ?>
                        | Cliente: <?= e($slot['cliente']) ?>
                      <?php endif; ?>
                      <?php if (!empty($slot['caso'])): ?>
                        | Caso: <?= e($slot['caso']) ?>
                      <?php endif; ?>
                    </p>
                  </div>
                  <div class="agenda-row-actions">
                    <?php if (!empty($slot['case_id'])): ?>
                      <a class="btn btn-outline btn-sm" href="chat.php?case_id=<?= (int) $slot['case_id'] ?>">Chat</a>
                    <?php endif; ?>

                    <?php if ($hasAppointment && ($slot['appointment_status'] ?? '') === 'agendado'): ?>
                      <form class="inline-form" action="<?= e(app_url('/backend/public/index.php?rota=/schedule/appointments/update')) ?>" method="post">
                        <?= csrf_input() ?>
                        <input type="hidden" name="appointment_id" value="<?= (int) $slot['appointment_id'] ?>">
                        <input type="hidden" name="status" value="concluido">
                        <button class="btn btn-soft btn-sm" type="submit">Concluir</button>
                      </form>
                      <form class="inline-form" action="<?= e(app_url('/backend/public/index.php?rota=/schedule/appointments/update')) ?>" method="post">
                        <?= csrf_input() ?>
                        <input type="hidden" name="appointment_id" value="<?= (int) $slot['appointment_id'] ?>">
                        <input type="hidden" name="status" value="cancelado">
                        <button class="btn btn-outline btn-sm" type="submit">Cancelar</button>
                      </form>
                    <?php elseif (!$hasAppointment && $type !== 'admin' && $isFuture): ?>
                      <form class="inline-form" action="<?= e(app_url('/backend/public/index.php?rota=/schedule/slots/update')) ?>" method="post">
                        <?= csrf_input() ?>
                        <input type="hidden" name="slot_id" value="<?= (int) $slot['id'] ?>">
                        <input type="hidden" name="status" value="<?= ($slot['status'] ?? '') === 'livre' ? 'bloqueado' : 'livre' ?>">
                        <button class="btn btn-outline btn-sm" type="submit"><?= ($slot['status'] ?? '') === 'livre' ? 'Bloquear' : 'Liberar' ?></button>
                      </form>
                    <?php endif; ?>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </details>
      <?php endif; ?>

      <div id="slot-modal" class="modal" style="display:none;">
        <div class="modal-backdrop" data-slot-modal-close></div>
        <div class="modal-card">
          <h3 id="slot-modal-title">Novo horário</h3>
          <form id="slot-modal-form">
            <?= csrf_input() ?>
            <input type="hidden" name="slot_id" id="slot-modal-id" value="">
            <input type="hidden" name="slot_date" id="slot-date" value="">
            <div id="slot-modal-alert" style="display:none;margin-bottom:8px;" class="modal-alert"></div>
            <div class="field">
              <label for="slot-starts">Hora de inicio</label>
              <input id="slot-starts" name="starts_time" type="time" required class="input">
            </div>
            <div class="field">
              <label for="slot-ends">Hora de fim</label>
              <input id="slot-ends" name="ends_time" type="time" required class="input">
            </div>
            <div class="field">
              <label for="slot-title">Titulo</label>
              <input id="slot-title" name="titulo" class="input">
            </div>
            <div class="field">
              <label for="slot-status">Status</label>
              <select id="slot-status" name="status" class="select">
                <option value="livre">Livre para cliente</option>
                <option value="bloqueado">Bloqueio interno</option>
              </select>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn btn-primary">Salvar</button>
              <button type="button" id="slot-modal-cancel" class="btn btn-outline">Cancelar</button>
            </div>
          </form>
        </div>
      </div>

      <div id="day-slots-modal" class="modal" style="display:none;">
        <div class="modal-backdrop" data-day-modal-close></div>
        <div class="modal-card day-slots-card">
          <h3 id="day-slots-title">Horários do dia</h3>
          <div id="day-slots-content" class="day-slots-content"></div>
          <div class="form-actions">
            <button type="button" id="day-slots-close" class="btn btn-outline">Fechar</button>
          </div>
        </div>
      </div>
    </main>
  </div>
  <script type="application/json" id="agenda-user-context"><?= json_encode(
      ['id' => (int) current_user_id(), 'type' => (string) current_user_type()],
      JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
  ) ?></script>
  <script src="assets/js/agenda.js?v=module-5"></script>
  <?php render_vlibras(); ?>
</body>
</html>
