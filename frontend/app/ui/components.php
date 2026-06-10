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
    $label = sidebar_profile_label($type);
    $navigationModules = sidebar_navigation_modules($type, $isAdminPath);
    ?>
    <aside class="sidebar" id="dashboard-sidebar" data-sidebar aria-label="Navegação principal">
      <div class="sidebar-head">
        <button type="button" class="sidebar-brand" data-sidebar-brand-toggle aria-controls="dashboard-sidebar" aria-expanded="true" aria-label="Logo JusTraduz">
          <span class="sidebar-brand-logo">
            <img src="<?= e($logoPath) ?>" alt="JusTraduz">
          </span>
        </button>
        <button type="button" class="sidebar-toggle" data-sidebar-toggle aria-controls="dashboard-sidebar" aria-expanded="true" aria-label="Recolher menu lateral" title="Recolher menu lateral">
          <?= icon_svg('sidebar') ?>
        </button>
      </div>
      <div class="side-label"><?= e($label) ?></div>
      <nav class="sidebar-navigation" aria-label="Módulos da aplicação">
        <?php foreach ($navigationModules as $module): ?>
          <?php
            $moduleId = 'sidebar-module-' . preg_replace('/[^a-z0-9-]+/', '-', strtolower($type . '-' . $module['id']));
            $moduleActive = false;

            foreach ($module['items'] as $moduleItem) {
                if (sidebar_item_is_active($moduleItem['href'], $active)) {
                    $moduleActive = true;
                    break;
                }
            }
          ?>
          <section class="sidebar-module<?= $moduleActive ? ' is-open has-active-item' : '' ?>" data-sidebar-module data-module-key="<?= e($type . ':' . $module['id']) ?>">
            <button
              type="button"
              class="sidebar-module-toggle"
              data-sidebar-module-toggle
              aria-expanded="<?= $moduleActive ? 'true' : 'false' ?>"
              aria-controls="<?= e($moduleId) ?>"
              title="<?= e($module['label']) ?>"
            >
              <?= icon_svg($module['icon']) ?>
              <span class="sidebar-module-label"><?= e($module['label']) ?></span>
              <span class="sidebar-module-chevron" aria-hidden="true"><?= icon_svg('chevron-down') ?></span>
            </button>
            <div class="sidebar-submenu" id="<?= e($moduleId) ?>">
              <?php foreach ($module['items'] as $item): ?>
                <?php
                  $activeMatch = sidebar_item_is_active($item['href'], $active);
                  $unread = basename((string) parse_url($item['href'], PHP_URL_PATH)) === 'notificacoes.php'
                      ? unread_notifications_count()
                      : 0;
                ?>
                <a class="sidebar-submenu-link<?= $activeMatch ? ' active' : '' ?>" href="<?= e($item['href']) ?>" title="<?= e($item['label']) ?>"<?= $activeMatch ? ' aria-current="page"' : '' ?>>
                  <?= icon_svg($item['icon']) ?>
                  <span class="sidebar-link-text"><?= e($item['label']) ?></span>
                  <?php if ($unread > 0): ?><span class="side-badge"><?= e((string) min($unread, 99)) ?></span><?php endif; ?>
                </a>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endforeach; ?>
      </nav>
      <div class="sidebar-footer">
        <form action="<?= e($logoutPath) ?>" method="post">
          <?= csrf_input() ?>
          <button type="submit" class="sidebar-logout" title="Sair"><?= icon_svg('logout') ?><span class="sidebar-link-text">Sair</span></button>
        </form>
      </div>
    </aside>
    <button type="button" class="sidebar-mobile-toggle sidebar-mobile-brand" data-sidebar-mobile-toggle aria-controls="dashboard-sidebar" aria-expanded="false" aria-label="Abrir menu lateral" title="Abrir menu lateral">
      <span class="sidebar-brand-logo">
        <img src="<?= e($logoPath) ?>" alt="" aria-hidden="true">
      </span>
    </button>
    <button type="button" class="sidebar-backdrop" data-sidebar-backdrop aria-label="Fechar menu lateral" tabindex="-1"></button>
    <script src="<?= e(sidebar_asset_path($isAdminPath)) ?>"></script>
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
    $path = str_contains($script, '/frontend/admin/') ? '../assets/js/theme.js' : 'assets/js/theme.js';
    return $path . '?v=theme-slow-3';
}

function sidebar_asset_path(bool $isAdminPath): string
{
    $path = $isAdminPath ? '../assets/js/sidebar.js' : 'assets/js/sidebar.js';
    return $path . '?v=sidebar-open-button-1';
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
