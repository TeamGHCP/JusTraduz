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
    $where = ["s.status = 'livre'", 's.starts_at >= NOW()', "u.status = 'ativo'", "u.tipo IN ('advogado', 'estagiario')", "(u.oab_verificado = TRUE OR (u.status_cna = 'pendente' AND COALESCE(u.oab, '') <> '' AND COALESCE(u.oab_uf, '') <> ''))"];
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
    "SELECT id, nome, tipo FROM users WHERE tipo IN ('advogado', 'estagiario') AND status = 'ativo' AND (oab_verificado = TRUE OR (status_cna = 'pendente' AND COALESCE(oab, '') <> '' AND COALESCE(oab_uf, '') <> '')) ORDER BY tipo, nome"
);
$canManageSlots = in_array($type, ['advogado', 'estagiario'], true);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Agenda | JusTraduz</title>
  <link rel="icon" href="assets/img/icon.ico" type="image/x-icon">
  <link rel="stylesheet" href="assets/css/style.css?v=theme-slow-2">
  <link rel="stylesheet" href="assets/css/agenda.css">
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar($type, 'agenda.php'); ?>

    <main class="app-main">
      <?php render_topbar('Agenda', $type === 'cliente' ? 'Veja horários livres de advogados e estagiários.' : 'Gerencie disponibilidade e acompanhe atendimentos.', current_user_name()); ?>

      <?php if ($type === 'cliente'): ?>
        <section class="dash-section">
          <form class="agenda-filter" method="get">
            <div class="field">
              <label for="professional_id">Advogado ou estagiário</label>
              <select class="select" id="professional_id" name="professional_id">
                <option value="">Todos os profissionais</option>
                <?php foreach ($professionals as $professional): ?>
                  <option value="<?= (int) $professional['id'] ?>" <?= $professionalFilter === (int) $professional['id'] ? 'selected' : '' ?>>
                    <?= e($professional['nome']) ?> - <?= e($professional['tipo'] === 'advogado' ? 'Advogado' : 'Estagiário') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-actions">
              <button class="btn btn-success" type="submit">Ver agenda</button>
              <?php if ($professionalFilter > 0): ?>
                <a class="btn btn-outline" href="agenda.php">Ver todos</a>
              <?php endif; ?>
            </div>
          </form>
        </section>
      <?php endif; ?>

      <section class="dash-section">
        <div class="dash-section-title">
          <h2>Calendário</h2>
          <span class="badge badge-success">Bolinha verde indica horários no dia. Clique nela para ver detalhes.</span>
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
            <input type="hidden" name="slot_date" id="slot-date" value="">
            <div id="slot-modal-alert" style="display:none;margin-bottom:8px;" class="modal-alert"></div>
            <div class="field">
              <label for="slot-starts">Hora de início</label>
              <input id="slot-starts" name="starts_time" type="time" required class="input">
            </div>
            <div class="field">
              <label for="slot-ends">Hora de fim</label>
              <input id="slot-ends" name="ends_time" type="time" required class="input">
            </div>
            <div class="field">
              <label for="slot-title">Título</label>
              <input id="slot-title" name="titulo" class="input">
            </div>
            <div class="field">
              <label for="slot-status">Status</label>
              <select id="slot-status" name="status" class="select">
                <option value="livre">Livre (visível para cliente)</option>
                <option value="bloqueado">Ocupado (somente interno)</option>
              </select>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn btn-success">Salvar</button>
              <button type="button" id="slot-modal-cancel" class="btn btn-outline">Cancelar</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Modal para ver horários do dia -->
      <div id="day-slots-modal" class="modal" style="display:none;">
        <div class="modal-backdrop"></div>
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
  <script>
    window.CURRENT_USER_ID = <?= (int) current_user_id() ?>;
    window.CURRENT_USER_TYPE = '<?= e((string) current_user_type()) ?>';
  </script>
  <script src="assets/js/agenda.js"></script>
</body>
</html>
