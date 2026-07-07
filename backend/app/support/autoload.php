<?php

// Include app configuration and helpers
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/support/session.php';
require_once dirname(__DIR__) . '/support/security.php';

// Include Composer autoloader when available. In local/XAMPP setups the vendor
// directory may not exist yet, so keep a small PSR-4 fallback for first run.
$composerAutoload = dirname(__DIR__, 3) . '/vendor/autoload.php';

if (is_file($composerAutoload)) {
    require_once $composerAutoload;
} else {
    spl_autoload_register(function ($class) {
        $prefixes = [
            'App\\Core\\' => dirname(__DIR__) . '/core/',
            'App\\Controllers\\' => dirname(__DIR__) . '/controllers/',
            'App\\Services\\Payments\\Asaas\\' => dirname(__DIR__) . '/services/payments/asaas/',
            'App\\Services\\Payments\\' => dirname(__DIR__) . '/services/payments/',
            'App\\Services\\' => dirname(__DIR__) . '/services/',
            'App\\Middlewares\\' => dirname(__DIR__) . '/middlewares/',
            'App\\Support\\' => dirname(__DIR__) . '/support/',
            'App\\Repositories\\' => dirname(__DIR__) . '/repositories/',
            'App\\Validators\\' => dirname(__DIR__) . '/validators/',
            'App\\Dtos\\' => dirname(__DIR__) . '/dtos/',
            'App\\Helpers\\' => dirname(__DIR__) . '/helpers/',
            'App\\Policies\\' => dirname(__DIR__) . '/policies/',
            'App\\Exceptions\\' => dirname(__DIR__) . '/exceptions/',
            'App\\Transformers\\' => dirname(__DIR__) . '/transformers/',
            'App\\Resources\\' => dirname(__DIR__) . '/resources/',
        ];

        foreach ($prefixes as $prefix => $baseDir) {
            if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
                continue;
            }

            $relativeClass = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

            if (is_file($file)) {
                require_once $file;
            }

            return;
        }
    });
}

spl_autoload_register(function ($class) {
    $map = [
        // Core
        'BaseController' => 'App\Core\BaseController',
        'ErrorHandler' => 'App\Core\ErrorHandler',
        'RedirectException' => 'App\Core\RedirectException',
        'Request' => 'App\Core\Request',
        'Response' => 'App\Core\Response',
        'Router' => 'App\Core\Router',

        // Exceptions
        'BaseException' => 'App\Exceptions\BaseException',
        'ValidationException' => 'App\Exceptions\ValidationException',

        // Middlewares
        'CsrfMiddleware' => 'App\Middlewares\CsrfMiddleware',
        'RateLimiterMiddleware' => 'App\Middlewares\RateLimiterMiddleware',

        // Controllers
        'AdminController' => 'App\Controllers\AdminController',
        'AiController' => 'App\Controllers\AiController',
        'ApiV1Controller' => 'App\Controllers\ApiV1Controller',
        'AuditExportController' => 'App\Controllers\AuditExportController',
        'AuthController' => 'App\Controllers\AuthController',
        'BillingController' => 'App\Controllers\BillingController',
        'CaseController' => 'App\Controllers\CaseController',
        'DocumentController' => 'App\Controllers\DocumentController',
        'HealthController' => 'App\Controllers\HealthController',
        'IntegrationController' => 'App\Controllers\IntegrationController',
        'NotificationController' => 'App\Controllers\NotificationController',
        'OnboardingController' => 'App\Controllers\OnboardingController',
        'OrganizationInviteController' => 'App\Controllers\OrganizationInviteController',
        'P2AdminController' => 'App\Controllers\P2AdminController',
        'PrivacyController' => 'App\Controllers\PrivacyController',
        'ProcessController' => 'App\Controllers\ProcessController',
        'PublicApiController' => 'App\Controllers\PublicApiController',
        'ScheduleController' => 'App\Controllers\ScheduleController',

        // Services
        'AiRateLimiter' => 'App\Services\AiRateLimiter',
        'AuditService' => 'App\Services\AuditService',
        'BillingEmailService' => 'App\Services\BillingEmailService',
        'DataJudService' => 'App\Services\DataJudService',
        'EscalationService' => 'App\Services\EscalationService',
        'GeminiService' => 'App\Services\GeminiService',
        'GoogleOAuthService' => 'App\Services\GoogleOAuthService',
        'JobQueueService' => 'App\Services\JobQueueService',
        'MailerService' => 'App\Services\MailerService',
        'NotificationService' => 'App\Services\NotificationService',
        'OcrService' => 'App\Services\OcrService',
        'OnboardingService' => 'App\Services\OnboardingService',
        'OrganizationInviteService' => 'App\Services\OrganizationInviteService',
        'OrganizationService' => 'App\Services\OrganizationService',
        'PdfTextExtractor' => 'App\Services\PdfTextExtractor',
        'PermissionService' => 'App\Services\PermissionService',
        'ProcessRunnerService' => 'App\Services\ProcessRunnerService',
        'PublicApiClientService' => 'App\Services\PublicApiClientService',
        'RbacService' => 'App\Services\RbacService',
        'SlaService' => 'App\Services\SlaService',
        'StorageService' => 'App\Services\StorageService',
        'SubscriptionService' => 'App\Services\SubscriptionService',
        'UploadScannerService' => 'App\Services\UploadScannerService',
        'UsageLimiter' => 'App\Services\UsageLimiter',

        // Payments
        'AsaasPaymentProvider' => 'App\Services\Payments\AsaasPaymentProvider',
        'ManualPaymentProvider' => 'App\Services\Payments\ManualPaymentProvider',
        'PixPaymentProvider' => 'App\Services\Payments\PixPaymentProvider',
        'PaymentCheckoutResult' => 'App\Services\Payments\PaymentCheckoutResult',
        'PaymentProviderFactory' => 'App\Services\Payments\PaymentProviderFactory',
        'PaymentProviderInterface' => 'App\Services\Payments\PaymentProviderInterface',
    ];

    if (isset($map[$class])) {
        $namespacedClass = $map[$class];
        if (class_exists($namespacedClass) || interface_exists($namespacedClass)) {
            if (!class_exists($class, false) && !interface_exists($class, false)) {
                class_alias($namespacedClass, $class);
            }
        }
    }
});
