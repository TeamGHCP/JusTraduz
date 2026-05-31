<?php

require_once dirname(__DIR__) . '/app/core/Router.php';

$router = new Router();

$routes = [
    ['POST', '/auth/registrar', 'AuthController', 'registrar'],
    ['POST', '/auth/login', 'AuthController', 'login'],
    ['GET', '/auth/csrf', 'AuthController', 'csrf'],
    ['POST', '/auth/force-logout', 'AuthController', 'forceLogout'],
    ['POST', '/auth/admin-login', 'AuthController', 'adminLogin'],
    ['POST', '/auth/reset-password', 'AuthController', 'resetPassword'],
    ['POST', '/auth/logout', 'AuthController', 'logout'],
    ['POST', '/profile/update', 'AuthController', 'updateProfile'],
    ['POST', '/profile/password-code', 'AuthController', 'profilePasswordCode'],
    ['POST', '/profile/password-reset', 'AuthController', 'profilePasswordReset'],
    ['POST', '/oab/lookup', 'OabController', 'lookup'],

    ['POST', '/documents/upload', 'DocumentController', 'upload'],
    ['POST', '/documents/analyze', 'DocumentController', 'analyze'],
    ['POST', '/documents/delete', 'DocumentController', 'delete'],
    ['GET', '/documents/download', 'DocumentController', 'download'],

    ['POST', '/cases/create', 'CaseController', 'create'],
    ['POST', '/cases/accept', 'CaseController', 'accept'],
    ['POST', '/cases/status', 'CaseController', 'updateStatus'],
    ['POST', '/tasks/create', 'CaseController', 'createTask'],
    ['POST', '/tasks/update', 'CaseController', 'updateTask'],
    ['POST', '/messages/send', 'CaseController', 'sendMessage'],

    ['POST', '/notifications/read', 'NotificationController', 'markRead'],

    ['POST', '/schedule/slots/create', 'ScheduleController', 'createSlot'],
    ['POST', '/schedule/slots/update', 'ScheduleController', 'updateSlot'],
    ['POST', '/schedule/book', 'ScheduleController', 'book'],
    ['POST', '/schedule/appointments/update', 'ScheduleController', 'updateAppointment'],
    ['GET', '/schedule/calendar', 'ScheduleController', 'calendarData'],

    ['POST', '/admin/users/status', 'AdminController', 'updateUserStatus'],
    ['POST', '/admin/cases/update', 'AdminController', 'updateCase'],
    ['POST', '/admin/professionals/oab', 'AdminController', 'updateProfessionalOab'],
];

foreach ($routes as [$method, $path, $controller, $action]) {
    $router->{strtolower($method)}($path, $controller, $action);
}

$router->dispatch();
