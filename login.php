<?php
session_start();
require_once "backend/app/config/database.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $senha = isset($_POST['senha']) ? trim($_POST['senha']) : '';

    if (!$email || !$senha) {
        header("Location: frontend/login.html?erro=campos");
        exit();
    }

    // Buscar por EMAIL (não por nome)
    $sql = "SELECT id, nome, senha, tipo FROM users WHERE email = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && password_verify($senha, $usuario['senha'])) {

        $_SESSION['id']     = $usuario['id'];
        $_SESSION['nome']   = $usuario['nome'];
        $_SESSION['tipo']   = $usuario['tipo'];
        $_SESSION['logado'] = true;

        // Redireciona conforme tipo de usuário
        switch ($usuario['tipo']) {
            case 'admin':
                header("Location: frontend/admin/dashboard-admin.php");
                break;
            case 'advogado':
                header("Location: frontend/dashboard-advogado.php");
                break;
            case 'estagiario':
                header("Location: frontend/dashboard-estagiario.php");
                break;
            default: // cliente
                header("Location: frontend/dashboard-cliente.php");
                break;
        }
        exit();

    } elseif (!$usuario) {
        header("Location: frontend/login.html?erro=usuario");
        exit();
    } else {
        header("Location: frontend/login.html?erro=senha");
        exit();
    }
}
?>