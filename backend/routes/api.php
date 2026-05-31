<?php

require_once dirname(__DIR__) . '/app/core/Router.php';

$router = new Router();

$routes = [
    ['POST', '/auth/registrar', 'AuthController', 'registrar'],
    ['POST', '/auth/login', 'AuthController', 'login'],
    ['GET', '/auth/csrf', 'AuthController', 'csrf'],
    ['GET', '/auth/force-logout', 'AuthController', 'forceLogout'],
    ['POST', '/auth/admin-login', 'AuthController', 'adminLogin'],
    ['POST', '/auth/reset-password', 'AuthController', 'resetPassword'],
    ['GET', '/auth/logout', 'AuthController', 'logout'],
    ['POST', '/profile/update', 'AuthController', 'updateProfile'],
    ['POST', '/oab/lookup', 'OabController', 'lookup'],

    ['POST', '/documents/upload', 'DocumentController', 'upload'],
    ['POST', '/documents/analyze', 'DocumentController', 'analyze'],

    ['POST', '/cases/create', 'CaseController', 'create'],
    ['GET', '/cases/accept', 'CaseController', 'accept'],
    ['POST', '/cases/status', 'CaseController', 'updateStatus'],
    ['POST', '/tasks/create', 'CaseController', 'createTask'],
    ['POST', '/tasks/update', 'CaseController', 'updateTask'],
    ['POST', '/messages/send', 'CaseController', 'sendMessage'],

    ['POST', '/notifications/read', 'NotificationController', 'markRead'],

    ['POST', '/schedule/slots/create', 'ScheduleController', 'createSlot'],
    ['POST', '/schedule/slots/update', 'ScheduleController', 'updateSlot'],
    ['POST', '/schedule/book', 'ScheduleController', 'book'],
    ['POST', '/schedule/appointments/update', 'ScheduleController', 'updateAppointment'],

    ['POST', '/admin/users/status', 'AdminController', 'updateUserStatus'],
    ['POST', '/admin/cases/update', 'AdminController', 'updateCase'],
];

foreach ($routes as [$method, $path, $controller, $action]) {
    $router->{strtolower($method)}($path, $controller, $action);
}

$router->dispatch();
