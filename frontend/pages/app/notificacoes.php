<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();

$notifications = fetch_all(
    $pdo,
    'SELECT id, mensagem, lida, created_at
     FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 100',
    [current_user_id()]
);
$unreadCount = count_query($pdo, 'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND lida = FALSE', [current_user_id()]);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta name="robots" content="noindex, nofollow">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Notificações | JusTraduz</title>
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
<body>
  <div class="app-shell">
    <?php render_sidebar(current_user_type(), 'notificacoes.php'); ?>

    <main class="app-main">
      <?php render_topbar('Notificações', 'Acompanhe atualizações de casos, mensagens e documentos.', current_user_name()); ?>

      <section class="dash-section">
        <div class="dash-section-title">
          <h2>Central de avisos</h2>
          <span class="badge <?= $unreadCount > 0 ? 'badge-warning' : 'badge-success' ?>"><?= e((string) $unreadCount) ?> não lidas</span>
        </div>

        <?php if ($unreadCount > 0): ?>
          <form class="form-actions mt-12" action="<?= e(app_url('/backend/public/index.php?rota=/notifications/read')) ?>" method="post">
            <?= csrf_input() ?>
            <input type="hidden" name="all" value="1">
            <button class="btn btn-primary btn-sm" type="submit"><?= icon_svg('check') ?> Marcar todas como lidas</button>
          </form>
        <?php endif; ?>

        <?php if (!$notifications): ?>
          <?= empty_state('Nenhuma notificação por enquanto.') ?>
        <?php else: ?>
          <div class="notification-list mt-16">
            <?php foreach ($notifications as $notification): ?>
              <article class="card notification-item <?= $notification['lida'] ? '' : 'is-unread' ?>">
                <div>
                  <p><?= e($notification['mensagem']) ?></p>
                  <span><?= e(date('d/m/Y H:i', strtotime($notification['created_at']))) ?></span>
                </div>
                <?php if (!$notification['lida']): ?>
                  <form action="<?= e(app_url('/backend/public/index.php?rota=/notifications/read')) ?>" method="post">
                    <?= csrf_input() ?>
                    <input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>">
                    <button class="btn btn-soft btn-sm" type="submit">Marcar como lida</button>
                  </form>
                <?php else: ?>
                  <span class="badge badge-success">Lida</span>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </main>
  </div>
  <?php render_vlibras(); ?>
</body>
</html>
