<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/backend/app/config/database.php';

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function is_logged_in(): bool
{
    return isset($_SESSION['logado']) && $_SESSION['logado'] === true;
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: /justraduz/frontend/login.html?erro=' . urlencode('Faça login para continuar.'));
        exit;
    }
}

function require_role(array $roles): void
{
    require_login();

    if (!in_array($_SESSION['tipo'] ?? '', $roles, true)) {
        header('Location: ' . dashboard_url($_SESSION['tipo'] ?? 'cliente'));
        exit;
    }
}

function current_user_id(): int
{
    return (int) ($_SESSION['id'] ?? 0);
}

function current_user_name(): string
{
    return (string) ($_SESSION['nome'] ?? 'Usuário');
}

function current_user_type(): string
{
    return (string) ($_SESSION['tipo'] ?? 'cliente');
}

function dashboard_url(?string $type = null): string
{
    switch ($type ?? current_user_type()) {
        case 'advogado':
            return '/justraduz/frontend/dashboard-advogado.php';
        case 'estagiario':
            return '/justraduz/frontend/dashboard-estagiario.php';
        case 'admin':
            return '/justraduz/frontend/admin/dashboard-admin.php';
        default:
            return '/justraduz/frontend/dashboard-cliente.php';
    }
}

function count_query(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function fetch_all(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fetch_one(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function empty_state(string $message): string
{
    return '<div class="card empty-state"><p>' . e($message) . '</p></div>';
}

function icon_svg(string $name): string
{
    $icons = [
        'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9 21v-7h6v7"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h6"/>',
        'help' => '<circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 1 1 5.2 2c-.8.7-1.3 1.1-1.3 2.2"/><path d="M12 17h.01"/>',
        'case' => '<path d="M10 6V5a2 2 0 0 1 2-2h0a2 2 0 0 1 2 2v1"/><rect x="3" y="6" width="18" height="14" rx="2"/><path d="M3 12h18"/>',
        'chat' => '<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/>',
        'user' => '<path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/>',
        'logout' => '<path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M21 3v18"/>',
        'users' => '<path d="M17 21a5 5 0 0 0-10 0"/><circle cx="12" cy="8" r="4"/><path d="M22 21a4 4 0 0 0-3-3.87"/><path d="M16 4.13a4 4 0 0 1 0 7.75"/>',
        'chart' => '<path d="M4 19V5"/><path d="M4 19h16"/><path d="M8 16v-5"/><path d="M12 16V8"/><path d="M16 16v-3"/>',
        'lock' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'upload' => '<path d="M12 16V4"/><path d="M7 9l5-5 5 5"/><path d="M20 16v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'folder' => '<path d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
    ];

    $paths = $icons[$name] ?? $icons['file'];
    return '<svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $paths . '</svg>';
}

function dashboard_nav_items(string $type, bool $isAdminPath = false): array
{
    switch ($type) {
        case 'advogado':
            return [
            ['href' => 'dashboard-advogado.php', 'label' => 'Início', 'icon' => 'home'],
            ['href' => 'acompanhar-solicitacoes.php', 'label' => 'Casos', 'icon' => 'case'],
            ['href' => 'visualizar-documento.php', 'label' => 'Documentos', 'icon' => 'file'],
            ['href' => 'chat.php', 'label' => 'Chat', 'icon' => 'chat'],
            ['href' => 'perfil.php', 'label' => 'Perfil', 'icon' => 'user'],
        ];
        case 'estagiario':
            return [
            ['href' => 'dashboard-estagiario.php', 'label' => 'Início', 'icon' => 'home'],
            ['href' => 'acompanhar-solicitacoes.php', 'label' => 'Atendimentos', 'icon' => 'case'],
            ['href' => 'visualizar-documento.php', 'label' => 'Documentos', 'icon' => 'file'],
            ['href' => 'chat.php', 'label' => 'Chat', 'icon' => 'chat'],
            ['href' => 'perfil.php', 'label' => 'Perfil', 'icon' => 'user'],
        ];
        case 'admin':
            return $isAdminPath ? [
                ['href' => 'dashboard-admin.php', 'label' => 'Visão geral', 'icon' => 'chart'],
                ['href' => 'usuarios.php', 'label' => 'Usuários', 'icon' => 'users'],
                ['href' => 'solicitacoes.php', 'label' => 'Solicitações', 'icon' => 'case'],
                ['href' => 'documentos.php', 'label' => 'Documentos', 'icon' => 'folder'],
                ['href' => '../perfil.php', 'label' => 'Meu perfil', 'icon' => 'user'],
            ] : [
                ['href' => 'admin/dashboard-admin.php', 'label' => 'Visão geral', 'icon' => 'chart'],
                ['href' => 'admin/usuarios.php', 'label' => 'Usuários', 'icon' => 'users'],
                ['href' => 'admin/solicitacoes.php', 'label' => 'Solicitações', 'icon' => 'case'],
                ['href' => 'admin/documentos.php', 'label' => 'Documentos', 'icon' => 'folder'],
                ['href' => 'perfil.php', 'label' => 'Meu perfil', 'icon' => 'user'],
            ];
        default:
            return [
            ['href' => 'dashboard-cliente.php', 'label' => 'Início', 'icon' => 'home'],
            ['href' => 'visualizar-documento.php', 'label' => 'Documentos', 'icon' => 'file'],
            ['href' => 'solicitar-ajuda.php', 'label' => 'Solicitar ajuda', 'icon' => 'help'],
            ['href' => 'acompanhar-solicitacoes.php', 'label' => 'Solicitações', 'icon' => 'case'],
            ['href' => 'chat.php', 'label' => 'Chat', 'icon' => 'chat'],
            ['href' => 'perfil.php', 'label' => 'Perfil', 'icon' => 'user'],
        ];
    }
}

function render_sidebar(string $type, string $active, bool $isAdminPath = false): void
{
    $logoPath = $isAdminPath ? '../assets/img/logo.png' : 'assets/img/logo.png';
    $logoutPath = $isAdminPath ? '../../backend/public/index.php?rota=/auth/logout' : '../backend/public/index.php?rota=/auth/logout';
    if ($isAdminPath) {
        $brandHref = 'dashboard-admin.php';
    } else {
        switch ($type) {
            case 'admin':
                $brandHref = 'admin/dashboard-admin.php';
                break;
            case 'advogado':
                $brandHref = 'dashboard-advogado.php';
                break;
            case 'estagiario':
                $brandHref = 'dashboard-estagiario.php';
                break;
            default:
                $brandHref = 'dashboard-cliente.php';
                break;
        }
    }

    switch ($type) {
        case 'advogado':
            $label = 'Advogado';
            break;
        case 'estagiario':
            $label = 'Estagiário';
            break;
        case 'admin':
            $label = 'Administração';
            break;
        default:
            $label = 'Cliente';
            break;
    }
    ?>
    <aside class="sidebar">
      <a class="sidebar-brand" href="<?= e($brandHref) ?>">
        <img src="<?= e($logoPath) ?>" alt="JusTraduz">
      </a>
      <div class="side-label"><?= e($label) ?></div>
      <nav class="side-nav">
        <?php foreach (dashboard_nav_items($type, $isAdminPath) as $item): ?>
          <a class="<?= $active === $item['href'] ? 'active' : '' ?>" href="<?= e($item['href']) ?>">
            <?= icon_svg($item['icon']) ?>
            <span><?= e($item['label']) ?></span>
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
}

function stat_card(string $label, $value, string $icon): string
{
    return '<div class="stat-card">' . icon_svg($icon) . '<span>' . e($label) . '</span><strong>' . e((string) $value) . '</strong></div>';
}
