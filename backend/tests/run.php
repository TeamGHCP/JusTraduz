<?php

$tests = [
    __DIR__ . '/AiGuardrailsTest.php',
    __DIR__ . '/PermissionAndCriticalFlowsTest.php',
    __DIR__ . '/P1OperationsTest.php',
    __DIR__ . '/P2SaasTest.php',
    __DIR__ . '/RateLimiterTest.php',
];

foreach ($tests as $test) {
    passthru(PHP_BINARY . ' ' . escapeshellarg($test), $exitCode);
    if ($exitCode !== 0) {
        exit($exitCode);
    }
}

echo "Test suite: OK\n";
