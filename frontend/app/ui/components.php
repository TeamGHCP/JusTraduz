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

    $kind = query_message_kind();
    $isError = str_contains($kind, 'error');
    echo '<div class="alert alert-query is-visible ' . $kind . ' mt-16" role="' . ($isError ? 'alert' : 'status') . '" aria-live="' . ($isError ? 'assertive' : 'polite') . '" data-alert-auto-dismiss="10000">' . e($message) . '</div>';
}

function render_sidebar(string $type, string $active, bool $isAdminPath = false): void
{
    render_cookie_consent_assets($isAdminPath);

    $logoPath = $isAdminPath ? '../assets/img/logo.png' : 'assets/img/logo.png';
    $logoutPath = app_url('/backend/public/index.php?rota=/auth/logout');
    $label = sidebar_profile_label($type);
    $navigationModules = sidebar_navigation_modules($type, $isAdminPath);
    $tourDashboard = sidebar_brand_href($type, $isAdminPath);
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
                  $tourMeta = sidebar_tour_meta($type, $item['label']);
                  $unread = basename((string) parse_url($item['href'], PHP_URL_PATH)) === 'notificacoes.php'
                      ? unread_notifications_count()
                      : 0;
                ?>
                <a class="sidebar-submenu-link<?= $activeMatch ? ' active' : '' ?>" href="<?= e($item['href']) ?>" title="<?= e($item['label']) ?>"<?= $activeMatch ? ' aria-current="page"' : '' ?><?= $tourMeta ? ' data-tour-step="' . (int) $tourMeta[0] . '" data-tour-title="' . e($tourMeta[1]) . '" data-tour-description="' . e($tourMeta[2]) . '"' : '' ?>>
                  <?= icon_svg($item['icon']) ?>
                  <span class="sidebar-link-text"><?= e($item['label']) ?></span>
                  <?php if ($unread > 0): ?><span class="side-badge"><?= e((string) min($unread, 99)) ?></span><?php endif; ?>
                </a>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endforeach; ?>
        <?php $helpModuleId = 'sidebar-module-' . preg_replace('/[^a-z0-9-]+/', '-', strtolower($type . '-help')); ?>
        <section class="sidebar-module" data-sidebar-module data-module-key="<?= e($type . ':help') ?>">
          <button
            type="button"
            class="sidebar-module-toggle"
            data-sidebar-module-toggle
            aria-expanded="false"
            aria-controls="<?= e($helpModuleId) ?>"
            title="Ajuda"
          >
            <?= icon_svg('guide') ?>
            <span class="sidebar-module-label">Ajuda</span>
            <span class="sidebar-module-chevron" aria-hidden="true"><?= icon_svg('chevron-down') ?></span>
          </button>
          <div class="sidebar-submenu" id="<?= e($helpModuleId) ?>">
            <a class="sidebar-submenu-link" href="<?= e($tourDashboard) ?>?tour=replay" data-tour-replay title="Ver tour novamente">
              <?= icon_svg('play') ?>
              <span class="sidebar-link-text">Ver tour novamente</span>
            </a>
          </div>
        </section>
      </nav>
      <div class="sidebar-footer">
        <div class="sidebar-theme-control">
          <span class="sidebar-link-text">Tema</span>
          <?= render_theme_toggle() ?>
        </div>
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
    <script src="<?= e(app_interactions_asset_path($isAdminPath)) ?>" defer></script>
    <script src="<?= e(theme_asset_path()) ?>" defer></script>
    <script src="<?= e(accessibility_asset_path($isAdminPath)) ?>" defer></script>
    <link rel="stylesheet" href="<?= e(context_help_asset_path($isAdminPath, 'css')) ?>">
    <script src="<?= e(context_help_asset_path($isAdminPath, 'js')) ?>" defer></script>
    <?php
}

function render_vlibras(): void
{
    static $rendered = false;

    if ($rendered) {
        return;
    }

    $rendered = true;
    $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
    $isAdminPath = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/frontend/admin/') || str_starts_with($requestPath, '/admin/');
    $assetPrefix = $isAdminPath ? '../' : '';
    render_cookie_consent_assets($isAdminPath);
    ?>
    <div vw class="enabled">
      <div vw-access-button class="active" role="button" tabindex="0" aria-label="Abrir tradutor VLibras" title="Abrir tradutor VLibras"></div>
      <div vw-plugin-wrapper>
        <div class="vw-plugin-top-wrapper"></div>
      </div>
    </div>
    <script src="<?= e($assetPrefix) ?>assets/js/vlibras-init.js?v=2026.07.05-a11y-global-1" defer></script>
    <?php
}

function render_cookie_consent_assets(bool $isAdminPath = false): void
{
    static $rendered = [];
    $key = $isAdminPath ? 'admin' : 'app';

    if (!empty($rendered[$key])) {
        return;
    }

    $rendered[$key] = true;
    $assetPrefix = $isAdminPath ? '../' : '';
    ?>
    <script src="<?= e($assetPrefix) ?>assets/js/cookie-consent.js?v=2026.07.02-vlibras-1"></script>
    <?php
}

function help_icon(string $title, string $description): string
{
    return '<button type="button" class="help-dot"'
        . ' data-help-title="' . e($title) . '"'
        . ' data-help-description="' . e($description) . '"'
        . ' aria-label="Ajuda: ' . e($title) . '">'
        . icon_svg('help-bubble')
        . '</button>';
}

function context_help_description(string $label, string $profile = ''): string
{
    $normalized = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label) ?: $label);
    $descriptions = [
        'dashboard' => 'Mostra um resumo das informações mais importantes do seu perfil. Use para decidir o próximo passo e confira os detalhes antes de agir.',
        'notific' => 'Reúne avisos sobre atividades do sistema. Use para acompanhar novidades e evite compartilhar dados sensíveis fora da plataforma.',
        'document' => 'Permite consultar documentos autorizados para seu perfil. Use somente quando necessário e preserve o sigilo do conteúdo.',
        'process' => 'Organiza consultas e informações processuais. Confira número, tribunal e atualização antes de tomar decisões.',
        'solicita' => 'Acompanha pedidos de ajuda e seus responsáveis. Use os status para entender o andamento e mantenha os dados no caso correto.',
        'caso' => 'Centraliza o atendimento jurídico e seus próximos passos. Verifique prioridade, responsável e permissões antes de alterar.',
        'chat' => 'Mantém a conversa vinculada ao atendimento. Use para assuntos do caso e não envie informações desnecessárias.',
        'advogado' => 'Apresenta profissionais disponíveis ou relacionados ao atendimento. Confira a validação profissional antes do contato.',
        'tarefa' => 'Organiza ações pendentes. Registre instruções objetivas e evite inserir dados pessoais sem necessidade.',
        'agenda' => 'Gerencia horários e atendimentos. Confirme data, responsável e disponibilidade antes de salvar.',
        'perfil' => 'Permite revisar dados da conta e segurança. Mantenha as informações atualizadas e proteja sua senha.',
        'usuario' => 'Gerencia contas e permissões. Use acesso mínimo, registre a finalidade e confira o perfil antes de alterar.',
        'oab' => 'Apoia a validação profissional. Confira os dados oficiais e registre decisões administrativas com justificativa.',
        'auditoria' => 'Exibe eventos importantes para segurança e governança. Use para investigar sem copiar dados sensíveis.',
        'seguranca' => 'Reúne controles de proteção e rastreabilidade. Revise alertas com cuidado e mantenha acesso mínimo.',
        'operacao' => 'Agrupa funções operacionais da plataforma. Use os indicadores para priorizar pendências e riscos.',
        'gestao' => 'Agrupa controles administrativos. Faça alterações somente quando necessárias e verificáveis.',
        'organizacao' => 'Reúne agenda e tarefas para organizar o trabalho. Confira prazos e responsáveis.',
        'atendimento' => 'Agrupa recursos de ajuda jurídica. Mantenha documentos, mensagens e decisões vinculados ao caso correto.',
        'visao geral' => 'Apresenta os principais indicadores e avisos do perfil. Use como ponto de partida.',
        'conta' => 'Reúne dados pessoais, profissionais e segurança. Revise informações antes de salvar alterações.',
    ];

    foreach ($descriptions as $needle => $description) {
        if (str_contains($normalized, $needle)) {
            return $description;
        }
    }

    return 'Use esta página para consultar e executar as funções disponíveis. Confira as informações antes de alterar e não exponha dados pessoais ou jurídicos.';
}

function sidebar_tour_meta(string $type, string $label): ?array
{
    $items = [
        'cliente' => [
            'Solicitar ajuda' => [8, 'Pedir ajuda', 'Quando a análise não for suficiente, solicite orientação de um profissional.'],
            'Chat com advogado' => [9, 'Chat com profissional', 'Converse dentro do caso para manter o histórico e o contexto organizados.'],
            'Agenda' => [10, 'Agenda', 'Consulte e organize seus próximos atendimentos.'],
            'Notificações' => [11, 'Notificações', 'Acompanhe novidades sobre documentos, casos e mensagens.'],
            'Perfil' => [12, 'Perfil e segurança', 'Atualize seus dados, proteja sua senha e redefina o tour quando precisar.'],
        ],
        'advogado' => [
            'Chat por caso' => [9, 'Chat por caso', 'Centralize a comunicação no caso e preserve o sigilo dos dados do cliente.'],
            'Agenda' => [10, 'Agenda', 'Organize atendimentos e compromissos vinculados ao trabalho jurídico.'],
            'Perfil profissional' => [11, 'Perfil profissional', 'Mantenha seus dados profissionais e sua situação cadastral atualizados.'],
            'Notificações' => [12, 'Notificações', 'Acompanhe novos casos, mensagens e atualizações importantes.'],
            'Documentos' => [13, 'Sigilo e LGPD', 'Documentos de clientes são sensíveis. Acesse somente o necessário e não os exponha.'],
        ],
        'admin' => [
            'Auditoria' => [9, 'Segurança e auditoria', 'Investigue eventos críticos e tentativas de acesso sem expor dados sensíveis.'],
            'Tarefas' => [10, 'Operação', 'Acompanhe ações necessárias para manter a plataforma funcionando.'],
            'Notificações' => [11, 'Notificações administrativas', 'Veja alertas relevantes para a operação e a segurança.'],
            'Meu perfil' => [12, 'LGPD e governança', 'Use privilégios administrativos com finalidade, rastreabilidade e acesso mínimo.'],
        ],
    ];

    return $items[$type][$label] ?? null;
}

function render_onboarding_assets(
    string $tourKey,
    string $tourVersion,
    string $profile,
    bool $autoStart = true
): void {
    $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
    $isAdminPath = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/frontend/admin/') || str_starts_with($requestPath, '/admin/');
    $assetPrefix = $isAdminPath ? '../' : '';
    $endpoint = static fn (string $route): string => app_url('/backend/public/index.php?rota=' . $route);
    $config = [
        'tourKey' => $tourKey,
        'tourVersion' => $tourVersion,
        'profile' => $profile,
        'userId' => current_user_id(),
        'autoStart' => $autoStart,
        'stateUrl' => $endpoint('/onboarding/state'),
        'startUrl' => $endpoint('/onboarding/start'),
        'completeUrl' => $endpoint('/onboarding/complete'),
        'skipUrl' => $endpoint('/onboarding/skip'),
        'resetUrl' => $endpoint('/onboarding/reset'),
        'csrfToken' => csrf_token(),
    ];
    ?>
    <link rel="stylesheet" href="<?= e($assetPrefix) ?>assets/css/onboarding.css?v=2026.06.28-1">
    <script type="application/json" id="justraduz-onboarding-config"><?= json_encode(
        $config,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?></script>
    <script src="<?= e($assetPrefix) ?>assets/js/onboarding-tour.js?v=2026.06.28-1" defer></script>
    <?php
}

function render_topbar(string $title, string $subtitle, string $roleLabel): void
{
    $initial = strtoupper(substr(current_user_name(), 0, 1));
    $photoUrl = current_user_photo_url();
    $accountPlanLabel = current_user_account_label();
    ?>
    <header class="topbar">
      <div>
        <h1><?= e($title) ?> <?= help_icon('Sobre esta página', context_help_description($title, current_user_type())) ?></h1>
        <p><?= e($subtitle) ?></p>
      </div>
      <div class="topbar-actions">
        <div class="topbar-account" title="<?= e(current_user_name() . ' · ' . $accountPlanLabel) ?>">
          <span class="topbar-account-copy">
            <strong><?= e(current_user_name()) ?></strong>
            <small><?= e($accountPlanLabel) ?></small>
          </span>
          <span class="avatar topbar-avatar" aria-label="Usuário: <?= e(current_user_name()) ?>">
            <span class="avatar-initial"><?= e($initial) ?></span>
            <?php if ($photoUrl): ?>
              <img src="<?= e($photoUrl) ?>" alt="<?= e(current_user_name()) ?>" referrerpolicy="no-referrer" onerror="this.remove()">
            <?php endif; ?>
          </span>
        </div>
      </div>
    </header>
    <?php
    render_query_alert();
}

function current_user_account_label(): string
{
    $type = current_user_type();
    if ($type !== 'cliente') {
        return sidebar_profile_label($type);
    }

    static $label = null;
    if ($label !== null) {
        return $label;
    }

    $label = 'Grátis';
    if (!is_logged_in()) {
        return $label;
    }

    global $pdo;
    if (!isset($pdo) || !$pdo instanceof PDO || !database_table_exists($pdo, 'subscriptions')) {
        return $label;
    }

    $stmt = $pdo->prepare(
        "SELECT p.name
         FROM subscriptions s
         INNER JOIN plans p ON p.id = s.plan_id
         WHERE s.user_id = ?
           AND s.status IN ('trialing', 'active', 'past_due')
         ORDER BY CASE s.status WHEN 'active' THEN 1 WHEN 'trialing' THEN 2 WHEN 'past_due' THEN 3 ELSE 9 END, s.created_at DESC
         LIMIT 1"
    );
    $stmt->execute([current_user_id()]);
    $planName = trim((string) ($stmt->fetchColumn() ?: ''));
    if ($planName !== '') {
        $label = $planName;
    }

    return $label;
}

function current_user_photo_url(): string
{
    static $photoPath = null;

    if ($photoPath === null) {
        $photoPath = '';

        if (is_logged_in()) {
            global $pdo;
            $stmt = $pdo->prepare('SELECT foto_perfil, google_picture FROM users WHERE id = ?');
            $stmt->execute([current_user_id()]);
            $userPhoto = $stmt->fetch();
            $localPhoto = trim((string) ($userPhoto['foto_perfil'] ?? ''));
            $googlePhoto = trim((string) ($userPhoto['google_picture'] ?? ''));

            if ($localPhoto !== '' && (preg_match('#^https?://#i', $localPhoto) || is_file(PROJECT_ROOT_PATH . '/' . ltrim($localPhoto, '/')))) {
                $photoPath = $localPhoto;
            } else {
                $photoPath = $googlePhoto;
            }
        }
    }

    if ($photoPath === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $photoPath)) {
        return $photoPath;
    }

    $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
    $prefix = (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/frontend/admin/') || str_starts_with($requestPath, '/admin/')) ? '../../' : '../';
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
    $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
    $path = (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/frontend/admin/') || str_starts_with($requestPath, '/admin/')) ? '../assets/js/theme.js' : 'assets/js/theme.js';
    return $path . '?v=theme-slow-3';
}

function sidebar_asset_path(bool $isAdminPath): string
{
    $path = $isAdminPath ? '../assets/js/sidebar.js' : 'assets/js/sidebar.js';
    return $path . '?v=sidebar-open-button-1';
}

function app_interactions_asset_path(bool $isAdminPath): string
{
    $path = $isAdminPath ? '../assets/js/app-interactions.js' : 'assets/js/app-interactions.js';
    return $path . '?v=app-interactions-20260704-1';
}

function context_help_asset_path(bool $isAdminPath, string $type): string
{
    $path = $isAdminPath ? '../assets/' : 'assets/';
    return $path . ($type === 'css' ? 'css/onboarding.css' : 'js/context-help.js') . '?v=2026.06.11-7';
}

function accessibility_asset_path(bool $isAdminPath): string
{
    $path = $isAdminPath ? '../assets/js/accessibility.js' : 'assets/js/accessibility.js';
    return $path . '?v=2026.07.05-a11y-global-1';
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
        default => 'dashboard-cliente.php',
    };
}

function sidebar_profile_label(string $type): string
{
    return match ($type) {
        'advogado' => 'Advogado',
        'admin' => 'Administração',
        default => 'Cliente',
    };
}
