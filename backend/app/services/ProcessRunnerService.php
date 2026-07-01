<?php

namespace App\Services {
    class ProcessRunnerService
    {
        public static function run(array $command, int $timeoutSeconds = 10, ?array $environment = null, ?string $input = null): array
        {
            if ($command === [] || !function_exists('proc_open')) {
                return [
                    'exit_code' => 127,
                    'stdout' => '',
                    'stderr' => 'Process execution is unavailable.',
                    'timed_out' => false,
                ];
            }

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = @proc_open($command, $descriptors, $pipes, null, $environment);
            if (!is_resource($process)) {
                return [
                    'exit_code' => 127,
                    'stdout' => '',
                    'stderr' => 'Unable to start process.',
                    'timed_out' => false,
                ];
            }

            fwrite($pipes[0], $input ?? '');
            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);

            $stdout = '';
            $stderr = '';
            $deadline = microtime(true) + max(1, $timeoutSeconds);
            $timedOut = false;

            while (true) {
                $status = proc_get_status($process);
                $stdout .= (string) stream_get_contents($pipes[1]);
                $stderr .= (string) stream_get_contents($pipes[2]);

                if (!$status['running']) {
                    break;
                }

                if (microtime(true) >= $deadline) {
                    $timedOut = true;
                    proc_terminate($process);
                    usleep(100000);
                    $status = proc_get_status($process);
                    if ($status['running']) {
                        proc_terminate($process, 9);
                    }
                    break;
                }

                usleep(50000);
            }

            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);
            if ($timedOut) {
                $exitCode = 124;
            }

            return [
                'exit_code' => $exitCode,
                'stdout' => $stdout,
                'stderr' => $stderr,
                'timed_out' => $timedOut,
            ];
        }
    }
}

namespace {
    if (!class_exists('ProcessRunnerService')) {
        class_alias('App\Services\ProcessRunnerService', 'ProcessRunnerService');
    }
}
