<?php

function is_logged_in(): bool
{
    return isset($_SESSION['logado']) && $_SESSION['logado'] === true;
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . app_url('/frontend/login.html?erro=' . urlencode('Faça login para continuar.')));
        exit;
    }

    require_validated_professional_access();
}

function require_validated_professional_access(): void
{
    $type = $_SESSION['tipo'] ?? '';
    if ($type !== 'advogado') {
        return;
    }

    global $pdo;
    $stmt = $pdo->prepare("SELECT oab_verificado, oab_status, status_cna, cna_ultimo_erro, oab_rejection_reason FROM users WHERE id = ? AND status = 'ativo'");
    $stmt->execute([current_user_id()]);
    $user = $stmt->fetch();

    if ($user && (int) ($user['oab_verificado'] ?? 0) === 1) {
        return;
    }

    $status = (string) (($user['oab_status'] ?? '') ?: ($user['status_cna'] ?? 'pending'));
    $message = 'Seu cadastro profissional está aguardando aprovação do administrador interno. Você receberá um e-mail quando for aprovado.';
    if (in_array($status, ['rejected', 'invalido', 'nao_encontrado'], true)) {
        $reason = trim((string) (($user['oab_rejection_reason'] ?? '') ?: ($user['cna_ultimo_erro'] ?? '')));
        $message = 'Seu cadastro profissional não foi aprovado.' . ($reason !== '' ? ' Motivo: ' . $reason : '');
    }

    secure_session_destroy_current();

    header('Location: ' . app_url('/frontend/login.html?erro=' . urlencode($message)));
    exit;
}

function require_role(array $roles): void
{
    require_login();

    if (!in_array($_SESSION['tipo'] ?? '', $roles, true)) {
        header('Location: ' . dashboard_url($_SESSION['tipo'] ?? 'cliente'));
        exit;
    }
}

function current_user_can(string $permission): bool
{
    return PermissionService::sessionHas($permission);
}

function require_permission(string $permission): void
{
    require_login();

    if (!current_user_can($permission)) {
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

function unread_notifications_count(): int
{
    if (!is_logged_in()) {
        return 0;
    }

    global $pdo;
    return count_query($pdo, 'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND lida = FALSE', [current_user_id()]);
}
