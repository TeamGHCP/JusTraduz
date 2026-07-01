<?php

require_once dirname(__DIR__) . '/app/config/app.php';
require_once dirname(__DIR__) . '/app/support/security.php';
security_headers(false);

require_once dirname(__DIR__) . '/app/support/session.php';
secure_session_configure();

require_once dirname(__DIR__) . '/app/support/autoload.php';
ErrorHandler::register();

require_once dirname(__DIR__) . '/routes/api.php';
