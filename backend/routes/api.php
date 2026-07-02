<?php

use App\Core\Router;

// Legacy compatibility mapping: '/api/v1' . $path
$router = new Router();

$publicApiRoutes = [
    ['GET', '/api/v1/health', 'HealthController', 'show'],
    ['GET', '/api/v1/openapi.json', 'PublicApiController', 'openApi'],
    ['GET', '/api/v1/integrations/health', 'IntegrationController', 'health'],
    ['GET', '/api/v1/integrations/reports/summary', 'IntegrationController', 'reportsSummary'],
    ['GET', '/api/v1/me', 'ApiV1Controller', 'me'],
    ['GET', '/api/v1/cases', 'ApiV1Controller', 'cases'],
    ['GET', '/api/v1/reports', 'ApiV1Controller', 'reports'],
];

$webhookRoutes = [
    ['GET', '/billing/webhook', 'BillingController', 'webhookStatus'],
    ['POST', '/billing/webhook', 'BillingController', 'webhook'],
];

$adminRoutes = [
    ['POST', '/admin/users/status', 'AdminController', 'updateUserStatus'],
    ['POST', '/admin/cases/update', 'AdminController', 'updateCase'],
    ['POST', '/admin/professionals/oab', 'AdminController', 'updateProfessionalOab'],
    ['POST', '/admin/p2/subscriptions/update', 'P2AdminController', 'updateSubscription'],
    ['POST', '/admin/p2/organizations/create', 'P2AdminController', 'createOrganization'],
    ['POST', '/admin/p2/organizations/member', 'P2AdminController', 'addOrganizationMember'],
    ['POST', '/admin/p2/permissions/update', 'P2AdminController', 'updatePermission'],
    ['GET', '/admin/reports/summary', 'AdminController', 'reportsSummary'],
    ['GET', '/admin/reports/export', 'AdminController', 'reportsExport'],
    ['GET', '/admin/audit/export', 'AuditExportController', 'csv'],
];

$internalRoutes = [
    ['GET', '/health', 'HealthController', 'show'],
    ['GET', '/openapi.json', 'PublicApiController', 'openApi'],
    ['GET', '/integrations/health', 'IntegrationController', 'health'],
    ['GET', '/integrations/reports/summary', 'IntegrationController', 'reportsSummary'],
    ['GET', '/organization/invite', 'OrganizationInviteController', 'accept'],
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
    ['POST', '/privacy/cancel-delete-account', 'PrivacyController', 'cancelAccountDeletion'],
    ['POST', '/billing/subscribe', 'BillingController', 'subscribe'],
    ['POST', '/billing/checkout', 'BillingController', 'checkout'],
    ['POST', '/billing/checkout/cancel', 'BillingController', 'cancelCheckout'],
    ['POST', '/billing/sync', 'BillingController', 'sync'],
    ['POST', '/billing/cancel', 'BillingController', 'cancel'],
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
];

// Register public API routes
foreach ($publicApiRoutes as [$method, $path, $controller, $action]) {
    $router->{strtolower($method)}($path, $controller, $action, ['rate_limit' => ['profile' => 'public_api']]);
}

// Register webhook routes
foreach ($webhookRoutes as [$method, $path, $controller, $action]) {
    $router->{strtolower($method)}($path, $controller, $action, ['rate_limit' => ['profile' => 'webhook']]);
}

// Register admin routes
foreach ($adminRoutes as [$method, $path, $controller, $action]) {
    $router->{strtolower($method)}($path, $controller, $action, ['rate_limit' => ['profile' => 'admin']]);
}

// Register internal routes
foreach ($internalRoutes as [$method, $path, $controller, $action]) {
    $router->{strtolower($method)}($path, $controller, $action, ['rate_limit' => route_rate_limit_profile($path)]);
    $router->legacyRedirect('/api/v1' . $path, $path, [$method]);
}

$router->dispatch();

function route_rate_limit_profile(string $path): array
{
    if (in_array($path, ['/auth/login', '/auth/admin-login'], true)) {
        return ['profile' => 'auth'];
    }

    if ($path === '/auth/registrar') {
        return ['profile' => 'register'];
    }

    if (in_array($path, ['/auth/reset-password', '/profile/password-code', '/profile/password-reset'], true)) {
        return ['profile' => 'password_reset'];
    }

    if (str_starts_with($path, '/auth/google')) {
        return ['profile' => 'oauth'];
    }

    if (str_starts_with($path, '/billing/')) {
        return ['profile' => 'payment'];
    }

    if ($path === '/documents/upload' || $path === '/messages/send') {
        return ['profile' => 'upload'];
    }

    if (str_starts_with($path, '/organization/invite')) {
        return ['profile' => 'invite'];
    }

    return ['profile' => 'default'];
}
