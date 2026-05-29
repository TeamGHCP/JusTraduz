<?php
// Traz a conexão que você já sabe que funciona
require_once "backend/app/config/database.php"; 

// Gera o hash perfeito direto no seu servidor atual
$senha_perfeita = password_hash("123456", PASSWORD_DEFAULT);

try {
    // Atualiza a senha do admin diretamente
    $sql = "UPDATE users SET senha = ? WHERE email = 'admin@justraduz.com'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$senha_perfeita]);
    
    echo "<h2>Senha do Admin atualizada com sucesso direto pelo servidor!</h2>";
    echo "Agora apague este arquivo (forçar-admin.php) e tente fazer o login.";
} catch (PDOException $e) {
    echo "Erro ao atualizar: " . $e->getMessage();
}
?>