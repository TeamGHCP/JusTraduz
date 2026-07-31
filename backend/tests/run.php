<?php

$tests = [
    __DIR__ . '/AiGuardrailsTest.php',
    __DIR__ . '/PermissionAndCriticalFlowsTest.php',
    __DIR__ . '/P1OperationsTest.php',
    __DIR__ . '/P2SaasTest.php',
    __DIR__ . '/RateLimiterTest.php',
];

foreach ($tests as $test) {
    $process = proc_open(
        [PHP_BINARY, $test],
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes
    );

    if (!is_resource($process)) {
        fwrite(STDERR, 'Nao foi possivel iniciar o teste: ' . $test . PHP_EOL);
        exit(1);
    }

    fclose($pipes[0]);
    echo stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    if ($errors !== '') {
        fwrite(STDERR, $errors);
    }
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        exit($exitCode);
    }
}

echo "Test suite: OK\n";
