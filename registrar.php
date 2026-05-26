<?php
session_start();
require "backend/app/config/database.php";

$nome   = $_POST['nome']      ?? '';
$email  = $_POST['email']     ?? '';
$senha  = $_POST['senha']     ?? '';
$tipo   = $_POST['tipo']      ?? 'cliente';
$oab    = $_POST['inscricao'] ?? null;
$oab_uf = $_POST['oab_uf']    ?? null;

// Validação básica
if (!$nome || !$email || !$senha) {
    die("Preencha todos os campos obrigatórios.");
}

// OAB só obrigatória para advogado/estagiário
if (($tipo === 'advogado' || $tipo === 'estagiario') && !$oab) {
    die("Número da OAB é obrigatório para advogados e estagiários.");
}

$senhaCriptografada = password_hash($senha, PASSWORD_DEFAULT);

$sql = "INSERT INTO users (nome, email, senha, tipo, oab, oab_uf)
        VALUES (:nome, :email, :senha, :tipo, :oab, :oab_uf)";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(":nome",   $nome);
$stmt->bindValue(":email",  $email);
$stmt->bindValue(":senha",  $senhaCriptografada);
$stmt->bindValue(":tipo",   $tipo);
$stmt->bindValue(":oab",    $oab);          // null para cliente = OK
$stmt->bindValue(":oab_uf", $oab_uf);       // null para cliente = OK

$stmt->execute();

header("Location: frontend/login.html");
exit;
?>