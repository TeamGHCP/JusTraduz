<?php

require_once dirname(__DIR__) . '/app/core/Router.php';

$router = new Router();

$routes = [
    ['GET', '/health', 'HealthController', 'show'],
    ['POST', '/auth/registrar', 'AuthController', 'registrar'],
    ['POST', '/auth/login', 'AuthController', 'login'],
    ['GET', '/auth/google', 'AuthController', 'googleRedirect'],
    ['GET', '/auth/google/callback', 'AuthController', 'googleCallback'],
    ['POST', '/auth/google/complete-profile', 'AuthController', 'completeGoogleProfile'],
    ['GET', '/auth/csrf', 'AuthController', 'csrf'],
    ['POST', '/auth/force-logout', 'AuthController', 'forceLogout'],
    ['POST', '/auth/admin-login', 'AuthController', 'adminLogin'],
    ['POST', '/auth/reset-password', 'AuthController', 'resetPassword'],
    ['POST', '/auth/logout', 'AuthController', 'logout'],
    ['POST', '/profile/update', 'AuthController', 'updateProfile'],
    ['POST', '/profile/password-code', 'AuthController', 'profilePasswordCode'],
    ['POST', '/profile/password-reset', 'AuthController', 'profilePasswordReset'],
    ['POST', '/privacy/export', 'PrivacyController', 'export'],
    ['POST', '/privacy/delete-account', 'PrivacyController', 'deleteAccount'],
    ['POST', '/billing/subscribe', 'BillingController', 'subscribe'],
    ['POST', '/billing/webhook', 'BillingController', 'webhook'],
    ['POST', '/documents/upload', 'DocumentController', 'upload'],
    ['POST', '/documents/analyze', 'DocumentController', 'analyze'],
    ['POST', '/documents/delete', 'DocumentController', 'delete'],
    ['GET', '/documents/download', 'DocumentController', 'download'],

    ['GET', '/ai/csrf', 'AiController', 'csrf'],
    ['POST', '/ai/chat', 'AiController', 'chat'],

    ['POST', '/cases/create', 'CaseController', 'create'],
    ['POST', '/cases/accept', 'CaseController', 'accept'],
    ['POST', '/cases/status', 'CaseController', 'updateStatus'],
    ['POST', '/tasks/create', 'CaseController', 'createTask'],
    ['POST', '/tasks/update', 'CaseController', 'updateTask'],
    ['POST', '/messages/send', 'CaseController', 'sendMessage'],
    ['GET', '/messages/attachment', 'CaseController', 'downloadAttachment'],

    ['POST', '/notifications/read', 'NotificationController', 'markRead'],

    ['POST', '/processes/sync', 'ProcessController', 'sync'],

    ['POST', '/schedule/slots/create', 'ScheduleController', 'createSlot'],
    ['POST', '/schedule/slots/update', 'ScheduleController', 'updateSlot'],
    ['POST', '/schedule/book', 'ScheduleController', 'book'],
    ['POST', '/schedule/appointments/update', 'ScheduleController', 'updateAppointment'],
    ['GET', '/schedule/calendar', 'ScheduleController', 'calendarData'],

    ['GET', '/onboarding/state', 'OnboardingController', 'state'],
    ['POST', '/onboarding/start', 'OnboardingController', 'start'],
    ['POST', '/onboarding/complete', 'OnboardingController', 'complete'],
    ['POST', '/onboarding/skip', 'OnboardingController', 'skip'],
    ['POST', '/onboarding/reset', 'OnboardingController', 'reset'],

    ['POST', '/admin/users/status', 'AdminController', 'updateUserStatus'],
    ['POST', '/admin/cases/update', 'AdminController', 'updateCase'],
    ['POST', '/admin/professionals/oab', 'AdminController', 'updateProfessionalOab'],
    ['POST', '/admin/p2/subscriptions/update', 'P2AdminController', 'updateSubscription'],
    ['POST', '/admin/p2/organizations/create', 'P2AdminController', 'createOrganization'],
    ['POST', '/admin/p2/organizations/member', 'P2AdminController', 'addOrganizationMember'],
    ['POST', '/admin/p2/permissions/update', 'P2AdminController', 'updatePermission'],
    ['GET', '/admin/audit/export', 'AuditExportController', 'csv'],
    ['GET', '/api/v1/me', 'ApiV1Controller', 'me'],
    ['GET', '/api/v1/cases', 'ApiV1Controller', 'cases'],
    ['GET', '/api/v1/reports', 'ApiV1Controller', 'reports'],
];

foreach ($routes as [$method, $path, $controller, $action]) {
    $router->{strtolower($method)}($path, $controller, $action);
}

$router->dispatch();
