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
        <a href="<?= e($logoutPath) ?>"><?= icon_svg('logout') ?><span>Sair</span></a>
      </nav>
    </aside>
    <?php
}

function render_topbar(string $title, string $subtitle, string $roleLabel): void
{
    $initial = strtoupper(substr(current_user_name(), 0, 1));
    ?>
    <header class="topbar">
      <div>
        <h1><?= e($title) ?></h1>
        <p><?= e($subtitle) ?></p>
      </div>
      <div class="user-chip"><span class="avatar"><?= e($initial) ?></span><span><?= e($roleLabel) ?></span></div>
    </header>
    <?php
    render_query_alert();
}

function stat_card(string $label, $value, string $icon): string
{
    return '<div class="stat-card">' . icon_svg($icon) . '<span>' . e($label) . '</span><strong>' . e((string) $value) . '</strong></div>';
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
