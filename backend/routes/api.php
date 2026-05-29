<?php

require_once dirname(__DIR__) . '/app/core/Router.php';

$router = new Router();

// ── Auth ──────────────────────────────────────────
$router->post('/auth/registrar', 'AuthController', 'registrar');
$router->post('/auth/login',    'AuthController', 'login');
$router->get( '/auth/logout',   'AuthController', 'logout');
$router->post('/profile/update', 'AuthController', 'updateProfile');

$router->post('/documents/upload', 'DocumentController', 'upload');

$router->post('/cases/create', 'CaseController', 'create');
$router->get( '/cases/accept', 'CaseController', 'accept');
$router->post('/messages/send', 'CaseController', 'sendMessage');

// ── Dispatcher ────────────────────────────────────
$router->dispatch();
