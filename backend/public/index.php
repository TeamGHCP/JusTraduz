<?php

// Cabeçalhos de segurança básicos
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: no-referrer-when-downgrade');
// CSP restritiva para o frontend servido pelo mesmo host.
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://www.google.com https://www.gstatic.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; font-src 'self' data:; object-src 'self'; frame-src 'self' https://www.google.com; base-uri 'self'; form-action 'self'; frame-ancestors 'self';");
header('X-XSS-Protection: 1; mode=block');

// HSTS quando estiver em HTTPS
if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
	header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

require_once dirname(__DIR__) . '/app/support/session.php';
secure_session_configure();

// Registrar handler de erros/exceções
$errorHandlerFile = dirname(__DIR__) . '/app/core/ErrorHandler.php';
if (file_exists($errorHandlerFile)) {
    require_once $errorHandlerFile;
    ErrorHandler::register();
}

require_once dirname(__DIR__) . '/routes/api.php';
