<?php

// Cabeçalhos de segurança básicos
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: no-referrer-when-downgrade');
// Ajuste CSP: permitir apenas fontes necessárias (frontend servido pelo mesmo host).
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://www.google.com https://www.gstatic.com; style-src 'self' 'unsafe-inline' https:; img-src 'self' data: blob:; connect-src 'self' https: wss:; font-src 'self' data:; object-src 'none';");
header('X-XSS-Protection: 1; mode=block');

// HSTS quando estiver em HTTPS
if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
	header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

// Ponto de entrada de todas as requisições do backend
// Configurações seguras de cookie de sessão (se aplicável)
$secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
// Normalize domain for session cookie: strip port if present to avoid "host:port" in Domain attribute
$domain = $_SERVER['HTTP_HOST'] ?? '';
// If host includes port, remove it (e.g., 127.0.0.1:8080 -> 127.0.0.1)
if (is_string($domain) && strpos($domain, ':') !== false) {
	$domain = explode(':', $domain, 2)[0];
}

@session_set_cookie_params([
	'lifetime' => 0,
	'path' => '/',
	'domain' => $domain,
	'secure' => $secure,
	'httponly' => true,
	'samesite' => 'Lax',
]);

// Registrar handler de erros/exceções
$errorHandlerFile = dirname(__DIR__) . '/app/core/ErrorHandler.php';
if (file_exists($errorHandlerFile)) {
    require_once $errorHandlerFile;
    ErrorHandler::register();
}

require_once dirname(__DIR__) . '/routes/api.php';