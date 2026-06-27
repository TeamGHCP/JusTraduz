<?php

require_once dirname(__DIR__) . '/app/config/app.php';
require_once dirname(__DIR__) . '/app/support/security.php';
security_headers(false);

require_once dirname(__DIR__) . '/app/support/session.php';
secure_session_configure();

$errorHandlerFile = dirname(__DIR__) . '/app/core/ErrorHandler.php';
if (file_exists($errorHandlerFile)) {
    require_once $errorHandlerFile;
    ErrorHandler::register();
}

require_once dirname(__DIR__) . '/routes/api.php';
