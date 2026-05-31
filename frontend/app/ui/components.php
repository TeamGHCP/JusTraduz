<?php

function empty_state(string $message): string
{
    return '<div class="card empty-state"><p>' . e($message) . '</p></div>';
}

function render_query_alert(): void
{
    $message = query_message();
    if ($message === '') {
        return;
    }

    echo '<div class="alert is-visible ' . query_message_kind() . ' mt-16">' . e($message) . '</div>';
}

function render_sidebar(string $type, string $active, bool $isAdminPath = false): void
{
    $logoPath = $isAdminPath ? '../assets/img/logo.png' : 'assets/img/logo.png';
    $logoutPath = app_url('/backend/public/index.php?rota=/auth/logout');
    $brandHref = sidebar_brand_href($type, $isAdminPath);
    $label = sidebar_profile_label($type);
    ?>
    <aside class="sidebar">
      <a class="sidebar-brand" href="<?= e($brandHref) ?>">
        <img src="<?= e($logoPath) ?>" alt="JusTraduz">
      </a>
      <div class="side-label"><?= e($label) ?></div>
      <nav class="side-nav">
        <?php foreach (dashboard_nav_items($type, $isAdminPath) as $item): ?>
          <?php
            $activeMatch = basename($item['href']) === $active || $item['href'] === $active;
            $unread = basename($item['href']) === 'notificacoes.php' ? unread_notifications_count() : 0;
          ?>
          <a class="<?= $activeMatch ? 'active' : '' ?>" href="<?= e($item['href']) ?>">
            <?= icon_svg($item['icon']) ?>
            <span><?= e($item['label']) ?></span>
            <?php if ($unread > 0): ?><span class="side-badge"><?= e((string) min($unread, 99)) ?></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </nav>
      <div class="side-label">Conta</div>
      <nav class="side-nav">
        <form action="<?= e($logoutPath) ?>" method="post">
          <?= csrf_input() ?>
          <button type="submit"><?= icon_svg('logout') ?><span>Sair</span></button>
        </form>
      </nav>
    </aside>
    <?php
}

function render_topbar(string $title, string $subtitle, string $roleLabel): void
{
    $initial = strtoupper(substr(current_user_name(), 0, 1));
    $photoUrl = current_user_photo_url();
    ?>
    <header class="topbar">
      <div>
        <h1><?= e($title) ?></h1>
        <p><?= e($subtitle) ?></p>
      </div>
      <div class="topbar-actions">
        <?= render_theme_toggle() ?>
        <div class="user-chip">
          <span class="avatar">
            <?php if ($photoUrl): ?>
              <img src="<?= e($photoUrl) ?>" alt="<?= e(current_user_name()) ?>">
            <?php else: ?>
              <?= e($initial) ?>
            <?php endif; ?>
          </span>
          <span><?= e($roleLabel) ?></span>
        </div>
      </div>
    </header>
    <script src="<?= e(theme_asset_path()) ?>" defer></script>
    <?php
    render_query_alert();
}

function current_user_photo_url(): string
{
    static $photoPath = null;

    if ($photoPath === null) {
        $photoPath = '';

        if (is_logged_in()) {
            global $pdo;
            $stmt = $pdo->prepare('SELECT foto_perfil FROM users WHERE id = ?');
            $stmt->execute([current_user_id()]);
            $photoPath = (string) ($stmt->fetchColumn() ?: '');
        }
    }

    if ($photoPath === '') {
        return '';
    }

    $prefix = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/frontend/admin/') ? '../../' : '../';
    return $prefix . ltrim($photoPath, '/');
}

function render_theme_toggle(): string
{
    return '<button type="button" class="theme-toggle" data-theme-toggle-button aria-label="Alternar tema" aria-pressed="false">'
        . '<span class="theme-toggle-track" aria-hidden="true">'
        . '<span class="theme-toggle-thumb">'
        . '<span class="theme-toggle-icon theme-toggle-icon-light">' . icon_svg('sun') . '</span>'
        . '<span class="theme-toggle-icon theme-toggle-icon-dark">' . icon_svg('moon') . '</span>'
        . '</span>'
        . '</span>'
        . '</button>';
}

function theme_asset_path(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    return str_contains($script, '/frontend/admin/') ? '../assets/js/theme.js' : 'assets/js/theme.js';
}

function stat_card(string $label, $value, string $icon): string
{
    return '<div class="stat-card">' . icon_svg($icon) . '<span>' . e($label) . '</span><strong>' . e((string) $value) . '</strong></div>';
}

function status_label(?string $status): string
{
    return str_replace('_', ' ', (string) $status);
}

function sidebar_brand_href(string $type, bool $isAdminPath): string
{
    if ($isAdminPath) {
        return 'dashboard-admin.php';
    }

    return match ($type) {
        'admin' => 'admin/dashboard-admin.php',
        'advogado' => 'dashboard-advogado.php',
        'estagiario' => 'dashboard-estagiario.php',
        default => 'dashboard-cliente.php',
    };
}

function sidebar_profile_label(string $type): string
{
    return match ($type) {
        'advogado' => 'Advogado',
        'estagiario' => 'Estagiário',
        'admin' => 'Administração',
        default => 'Cliente',
    };
}
