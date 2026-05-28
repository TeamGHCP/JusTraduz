<?php

require_once dirname(__DIR__) . '/app/core/Router.php';

$router = new Router();

// ── Auth ──────────────────────────────────────────
$router->post('/auth/registrar', 'AuthController', 'registrar');
$router->post('/auth/login',    'AuthController', 'login');
$router->get( '/auth/logout',   'AuthController', 'logout');

// ── Dispatcher ────────────────────────────────────
$router->dispatch();