<?php

// Compatibilidade para formulários antigos. A regra real fica no backend.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: frontend/cadastro.html');
    exit;
}

$_GET['rota'] = '/auth/registrar';
require __DIR__ . '/backend/public/index.php';
