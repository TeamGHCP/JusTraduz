<?php

defined('FRONTEND_APP_PATH') || define('FRONTEND_APP_PATH', __DIR__);
defined('PROJECT_ROOT_PATH') || define('PROJECT_ROOT_PATH', dirname(__DIR__, 2));

require_once PROJECT_ROOT_PATH . '/backend/app/support/session.php';
require_once PROJECT_ROOT_PATH . '/backend/app/support/security.php';
security_headers(false);
secure_session_start();

require_once PROJECT_ROOT_PATH . '/backend/app/config/app.php';
require_once PROJECT_ROOT_PATH . '/backend/app/support/autoload.php';
ErrorHandler::register();

require_once PROJECT_ROOT_PATH . '/backend/app/config/database.php';
require_once PROJECT_ROOT_PATH . '/backend/app/services/PermissionService.php';

require_once FRONTEND_APP_PATH . '/support/http.php';
require_once FRONTEND_APP_PATH . '/support/database.php';
require_once FRONTEND_APP_PATH . '/support/session.php';
require_once FRONTEND_APP_PATH . '/support/csrf.php';

require_once FRONTEND_APP_PATH . '/ui/icons.php';
require_once FRONTEND_APP_PATH . '/ui/navigation.php';
require_once FRONTEND_APP_PATH . '/ui/components.php';
