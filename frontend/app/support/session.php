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
    if (!in_array($type, ['advogado', 'estagiario'], true)) {
        return;
    }

    global $pdo;
    $stmt = $pdo->prepare("SELECT oab_verificado FROM users WHERE id = ? AND status = 'ativo'");
    $stmt->execute([current_user_id()]);

    if ((int) ($stmt->fetchColumn() ?: 0) === 1) {
        return;
    }

    secure_session_destroy_current();

    header('Location: ' . app_url('/frontend/login.html?erro=' . urlencode('Sua OAB ainda precisa ser validada pela administracao.')));
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
