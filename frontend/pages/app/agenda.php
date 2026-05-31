<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();

$type = current_user_type();
$userId = current_user_id();
$professionalFilter = (int) ($_GET['professional_id'] ?? 0);
$roleFilter = $_GET['perfil'] ?? '';

$clientsCases = $type === 'cliente'
    ? fetch_all($pdo, "SELECT id, titulo FROM cases WHERE cliente_id = ? AND status <> 'finalizado' ORDER BY created_at DESC", [$userId])
    : [];

if ($type === 'cliente') {
    $where = ["s.status = 'livre'", 's.starts_at >= NOW()', "u.status = 'ativo'", "u.tipo IN ('advogado', 'estagiario')", 'u.oab_verificado = TRUE'];
    $params = [];

    if ($professionalFilter > 0) {
        $where[] = 'u.id = ?';
        $params[] = $professionalFilter;
    }

    if (in_array($roleFilter, ['advogado', 'estagiario'], true)) {
        $where[] = 'u.tipo = ?';
        $params[] = $roleFilter;
    }

    $freeSlots = fetch_all(
        $pdo,
        'SELECT s.*, u.nome AS profissional, u.tipo, u.oab, u.oab_uf
         FROM schedule_slots s
         INNER JOIN users u ON u.id = s.professional_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY s.starts_at ASC
         LIMIT 80',
        $params
    );

    $appointments = fetch_all(
        $pdo,
        'SELECT a.*, s.starts_at, s.ends_at, u.nome AS profissional, u.tipo, c.titulo AS caso
         FROM appointments a
         INNER JOIN schedule_slots s ON s.id = a.slot_id
         INNER JOIN users u ON u.id = s.professional_id
         LEFT JOIN cases c ON c.id = a.case_id
         WHERE a.client_id = ?
         ORDER BY s.starts_at DESC
         LIMIT 60',
        [$userId]
    );
} elseif (in_array($type, ['advogado', 'estagiario'], true)) {
    $freeSlots = [];
    $appointments = fetch_all(
        $pdo,
        'SELECT a.*, s.id AS slot_id, s.titulo AS slot_title, s.starts_at, s.ends_at, s.status AS slot_status, cli.nome AS cliente, c.titulo AS caso
         FROM schedule_slots s
         LEFT JOIN appointments a ON a.slot_id = s.id AND a.status <> "cancelado"
         LEFT JOIN users cli ON cli.id = a.client_id
         LEFT JOIN cases c ON c.id = a.case_id
         WHERE s.professional_id = ?
         ORDER BY s.starts_at DESC
         LIMIT 100',
        [$userId]
    );
} else {
    $freeSlots = [];
    $appointments = fetch_all(
        $pdo,
        'SELECT a.*, s.id AS slot_id, s.titulo AS slot_title, s.starts_at, s.ends_at, s.status AS slot_status, pro.nome AS profissional, pro.tipo, cli.nome AS cliente, c.titulo AS caso
         FROM schedule_slots s
         INNER JOIN users pro ON pro.id = s.professional_id
         LEFT JOIN appointments a ON a.slot_id = s.id AND a.status <> "cancelado"
         LEFT JOIN users cli ON cli.id = a.client_id
         LEFT JOIN cases c ON c.id = a.case_id
         ORDER BY s.starts_at DESC
         LIMIT 120'
    );
}

$professionals = fetch_all(
    $pdo,
    "SELECT id, nome, tipo FROM users WHERE tipo IN ('advogado', 'estagiario') AND status = 'ativo' AND oab_verificado = TRUE ORDER BY tipo, nome"
);
$canManageSlots = in_array($type, ['advogado', 'estagiario'], true);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Agenda | JusTraduz</title>
  <link rel="icon" href="assets/img/logo.png">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/agenda.css">
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar($type, 'agenda.php'); ?>

    <main class="app-main">
      <?php render_topbar('Agenda', $type === 'cliente' ? 'Veja horários livres de advogados e estagiários.' : 'Gerencie disponibilidade e acompanhe atendimentos.', current_user_name()); ?>

      <?php if ($canManageSlots): ?>
        <form class="card auth-form" action="<?= e(app_url('/backend/public/index.php?rota=/schedule/slots/create')) ?>" method="post">
          <?= csrf_input() ?>
          <div class="dash-section-title">
            <h2>Novo horário livre</h2>
            <span class="badge badge-info">Agenda profissional</span>
          </div>
          <div class="form-grid">
            <div class="field">
              <label for="starts_at">Início</label>
              <input class="input" id="starts_at" name="starts_at" type="datetime-local" required>
            </div>
            <div class="field">
              <label for="ends_at">Fim</label>
              <input class="input" id="ends_at" name="ends_at" type="datetime-local" required>
            </div>
          </div>
          <div class="field">
            <label for="titulo">Título interno</label>
            <input class="input" id="titulo" name="titulo" placeholder="Atendimento inicial, plantão, revisão de contrato">
          </div>
          <button class="btn btn-primary" type="submit"><?= icon_svg('calendar') ?> Adicionar horário</button>
        </form>
      <?php endif; ?>

      <section class="dash-section">
        <div class="dash-section-title">
          <h2>Calendário</h2>
          <span class="badge badge-info">Visualize seu mês e clique para criar horários</span>
        </div>
        <div id="calendar" class="card p-16"></div>
      </section>

      <!-- Modal para criar/editar horário -->
      <div id="slot-modal" class="modal" style="display:none;">
        <div class="modal-backdrop"></div>
        <div class="modal-card">
          <h3 id="slot-modal-title">Novo horário</h3>
          <form id="slot-modal-form">
            <?= csrf_input() ?>
            <input type="hidden" name="slot_id" id="slot-modal-id" value="">
            <div id="slot-modal-alert" style="display:none;margin-bottom:8px;" class="modal-alert"></div>
            <div class="field">
              <label for="slot-starts">Início</label>
              <input id="slot-starts" name="starts_at" type="datetime-local" required class="input">
            </div>
            <div class="field">
              <label for="slot-ends">Fim</label>
              <input id="slot-ends" name="ends_at" type="datetime-local" required class="input">
            </div>
            <div class="field">
              <label for="slot-title">Título</label>
              <input id="slot-title" name="titulo" class="input">
            </div>
            <div class="form-actions">
              <button type="submit" class="btn btn-primary">Salvar</button>
              <button type="button" id="slot-modal-cancel" class="btn btn-outline">Cancelar</button>
            </div>
          </form>
        </div>
      </div>

      <?php if ($type === 'cliente'): ?>
        <section class="dash-section">
          <form class="card admin-filter" method="get">
            <div class="field">
              <label for="perfil">Perfil</label>
              <select class="select" id="perfil" name="perfil">
                <option value="">Todos</option>
                <option value="advogado" <?= $roleFilter === 'advogado' ? 'selected' : '' ?>>Advogado</option>
                <option value="estagiario" <?= $roleFilter === 'estagiario' ? 'selected' : '' ?>>Estagiário</option>
              </select>
            </div>
            <div class="field">
              <label for="professional_id">Profissional</label>
              <select class="select" id="professional_id" name="professional_id">
                <option value="">Todos</option>
                <?php foreach ($professionals as $professional): ?>
                  <option value="<?= (int) $professional['id'] ?>" <?= $professionalFilter === (int) $professional['id'] ? 'selected' : '' ?>>
                    <?= e($professional['nome'] . ' - ' . $professional['tipo']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-actions">
              <button class="btn btn-primary" type="submit">Filtrar</button>
              <a class="btn btn-outline" href="agenda.php">Limpar</a>
            </div>
          </form>
        </section>

        <section class="dash-section">
          <div class="dash-section-title">
            <h2>Horários livres</h2>
            <span class="badge badge-info"><?= e((string) count($freeSlots)) ?> disponíveis</span>
          </div>
          <?php if (!$freeSlots): ?>
            <?= empty_state('Nenhum horário livre encontrado para os filtros selecionados.') ?>
          <?php else: ?>
            <div class="grid grid-2">
              <?php foreach ($freeSlots as $slot): ?>
                <article class="card schedule-card">
                  <div class="dash-section-title">
                    <h2><?= e($slot['profissional']) ?></h2>
                    <span class="badge badge-success"><?= e($slot['tipo']) ?></span>
                  </div>
                  <p><strong><?= e(date('d/m/Y H:i', strtotime($slot['starts_at']))) ?></strong> até <?= e(date('H:i', strtotime($slot['ends_at']))) ?></p>
                  <p class="mt-8 text-muted"><?= $slot['oab'] ? e('OAB/' . $slot['oab_uf'] . ' ' . $slot['oab']) : 'Cadastro ativo' ?></p>
                  <form class="auth-form mt-16" action="<?= e(app_url('/backend/public/index.php?rota=/schedule/book')) ?>" method="post">
                    <?= csrf_input() ?>
                    <input type="hidden" name="slot_id" value="<?= (int) $slot['id'] ?>">
                    <div class="field">
                      <label for="assunto_<?= (int) $slot['id'] ?>">Assunto</label>
                      <input class="input" id="assunto_<?= (int) $slot['id'] ?>" name="assunto" required>
                    </div>
                    <div class="field">
                      <label for="case_id_<?= (int) $slot['id'] ?>">Vincular caso</label>
                      <select class="select" id="case_id_<?= (int) $slot['id'] ?>" name="case_id">
                        <option value="">Sem caso vinculado</option>
                        <?php foreach ($clientsCases as $case): ?>
                          <option value="<?= (int) $case['id'] ?>"><?= e($case['titulo']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="field">
                      <label for="obs_<?= (int) $slot['id'] ?>">Observações</label>
                      <textarea class="textarea textarea-sm" id="obs_<?= (int) $slot['id'] ?>" name="observacoes"></textarea>
                    </div>
                    <button class="btn btn-primary btn-sm" type="submit"><?= icon_svg('calendar') ?> Agendar</button>
                  </form>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <section class="dash-section">
        <div class="dash-section-title">
          <h2><?= $type === 'cliente' ? 'Meus agendamentos' : 'Agenda e agendamentos' ?></h2>
          <span class="badge badge-info"><?= e((string) count($appointments)) ?> registros</span>
        </div>

        <?php if (!$appointments): ?>
          <?= empty_state($type === 'cliente' ? 'Você ainda não possui agendamentos.' : 'Nenhum horário ou agendamento cadastrado.') ?>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th>Data</th>
                  <th><?= $type === 'cliente' ? 'Profissional' : 'Pessoa' ?></th>
                  <th>Assunto</th>
                  <th>Caso</th>
                  <th>Status</th>
                  <th>Ação</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($appointments as $appointment): ?>
                  <?php $hasAppointment = !empty($appointment['id']); ?>
                  <tr>
                    <td>
                      <strong><?= e(date('d/m/Y H:i', strtotime($appointment['starts_at']))) ?></strong>
                      <span class="table-subtext">até <?= e(date('H:i', strtotime($appointment['ends_at']))) ?></span>
                    </td>
                    <td><?= e($type === 'cliente' ? ($appointment['profissional'] ?? '') : ($appointment['cliente'] ?? ($appointment['profissional'] ?? 'Horário livre'))) ?></td>
                    <td><?= e($hasAppointment ? $appointment['assunto'] : ($appointment['slot_title'] ?? 'Horário disponível')) ?></td>
                    <td><?= e($appointment['caso'] ?? 'Sem caso') ?></td>
                    <td>
                      <span class="badge <?= ($appointment['status'] ?? $appointment['slot_status'] ?? '') === 'agendado' ? 'badge-warning' : 'badge-info' ?>">
                        <?= e($appointment['status'] ?? $appointment['slot_status'] ?? 'livre') ?>
                      </span>
                    </td>
                    <td>
                      <?php if ($hasAppointment && ($appointment['status'] ?? '') === 'agendado'): ?>
                        <form class="action-form" action="<?= e(app_url('/backend/public/index.php?rota=/schedule/appointments/update')) ?>" method="post">
                          <?= csrf_input() ?>
                          <input type="hidden" name="appointment_id" value="<?= (int) $appointment['id'] ?>">
                          <?php if ($type === 'cliente'): ?>
                            <input type="hidden" name="status" value="cancelado">
                            <button class="btn btn-outline btn-sm" type="submit">Cancelar</button>
                          <?php else: ?>
                            <select class="select select-sm" name="status">
                              <option value="concluido">Concluir</option>
                              <option value="cancelado">Cancelar</option>
                            </select>
                            <button class="btn btn-soft btn-sm" type="submit">Salvar</button>
                          <?php endif; ?>
                        </form>
                      <?php elseif (!$hasAppointment && $canManageSlots): ?>
                        <form class="action-form" action="<?= e(app_url('/backend/public/index.php?rota=/schedule/slots/update')) ?>" method="post">
                          <?= csrf_input() ?>
                          <input type="hidden" name="slot_id" value="<?= (int) $appointment['slot_id'] ?>">
                          <select class="select select-sm" name="status">
                            <option value="livre" <?= ($appointment['slot_status'] ?? '') === 'livre' ? 'selected' : '' ?>>Livre</option>
                            <option value="bloqueado" <?= ($appointment['slot_status'] ?? '') === 'bloqueado' ? 'selected' : '' ?>>Bloqueado</option>
                          </select>
                          <button class="btn btn-soft btn-sm" type="submit">Salvar</button>
                        </form>
                      <?php else: ?>
                        <span class="text-muted">Sem ação</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    </main>
  </div>
  <script>window.CURRENT_USER_ID = <?= (int) current_user_id() ?>;</script>
  <script src="assets/js/agenda.js"></script>
</body>
</html>
