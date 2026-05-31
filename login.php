<?php

// Compatibilidade para formulários antigos. A regra real fica no backend.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: frontend/login.html');
    exit;
}

$_GET['rota'] = '/auth/login';
require __DIR__ . '/backend/public/index.php';
