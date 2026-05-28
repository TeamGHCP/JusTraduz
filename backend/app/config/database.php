<?php

$host    = "localhost";
$dbname  = "justraduz";
$usuario = "root";
$senha   = "";
$port    = "3307";

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $usuario,
        $senha
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); // linha nova

} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}
?>