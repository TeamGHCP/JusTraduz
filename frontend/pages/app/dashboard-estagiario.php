<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_role(['estagiario']);

$userId = current_user_id();
$futureSlotCount = count_query($pdo, 'SELECT COUNT(*) FROM schedule_slots WHERE professional_id = ? AND starts_at >= NOW()', [$userId]);
$appointmentCount = count_query(
    $pdo,
    'SELECT COUNT(*)
     FROM appointments a
     INNER JOIN schedule_slots s ON s.id = a.slot_id
     WHERE s.professional_id = ?
     AND a.status = "agendado"',
    [$userId]
);
$freeSlotCount = count_query($pdo, 'SELECT COUNT(*) FROM schedule_slots WHERE professional_id = ? AND status = "livre" AND starts_at >= NOW()', [$userId]);
$blockedSlotCount = count_query($pdo, 'SELECT COUNT(*) FROM schedule_slots WHERE professional_id = ? AND status = "bloqueado" AND starts_at >= NOW()', [$userId]);
$appointments = fetch_all(
    $pdo,
    'SELECT a.id, a.assunto, a.status, s.starts_at, s.ends_at, cli.nome AS cliente
     FROM appointments a
     INNER JOIN schedule_slots s ON s.id = a.slot_id
     INNER JOIN users cli ON cli.id = a.client_id
     WHERE s.professional_id = ?
     ORDER BY s.starts_at ASC
     LIMIT 8',
    [$userId]
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard do estagiário | JusTraduz</title>
  <link rel="icon" href="assets/img/icon.ico" type="image/x-icon">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="app-shell">
    <?php render_sidebar('estagiario', 'dashboard-estagiario.php'); ?>

    <main class="app-main">
      <?php render_topbar('Área do estagiário', 'Acesso assistivo limitado à própria agenda até existir atribuição formal de casos.', current_user_name()); ?>

      <section class="grid grid-4">
        <?= stat_card('Horários futuros', $futureSlotCount, 'calendar') ?>
        <?= stat_card('Agendamentos', $appointmentCount, 'case') ?>
        <?= stat_card('Horários livres', $freeSlotCount, 'check') ?>
        <?= stat_card('Bloqueados', $blockedSlotCount, 'shield') ?>
      </section>

      <section class="dash-section">
        <div class="dash-section-title">
          <h2>Meus agendamentos</h2>
          <a class="btn btn-soft btn-sm" href="agenda.php">Ver agenda</a>
        </div>
        <?php if (!$appointments): ?>
          <?= empty_state('Nenhum agendamento encontrado na sua agenda.') ?>
        <?php else: ?>
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Assunto</th><th>Cliente</th><th>Quando</th><th>Status</th><th>Ação</th></tr></thead>
              <tbody>
                <?php foreach ($appointments as $appointment): ?>
                  <tr>
                    <td><?= e($appointment['assunto']) ?></td>
                    <td><?= e($appointment['cliente']) ?></td>
                    <td><?= e(date('d/m/Y H:i', strtotime($appointment['starts_at']))) ?></td>
                    <td><?= e(status_label($appointment['status'] ?? '')) ?></td>
                    <td><a href="agenda.php">Abrir agenda</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>

      <section class="dash-section">
        <div class="dash-section-title">
          <h2>Permissões</h2>
          <a class="btn btn-soft btn-sm" href="agenda.php">Gerenciar horários</a>
        </div>
        <?= empty_state('Por segurança, estagiários não acessam documentos, chats ou casos de clientes sem uma atribuição formal no sistema.') ?>
      </section>
    </main>
  </div>
</body>
</html>
