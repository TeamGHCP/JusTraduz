<?php

require_once dirname(__DIR__) . '/core/BaseController.php';
require_once dirname(__DIR__) . '/services/StorageService.php';

class HealthController extends BaseController
{
    public function show(): void
    {
        $storage = new StorageService();
        $checks = [
            'app_debug' => !$this->envEnabled('APP_DEBUG'),
            'database' => $this->databaseOk(),
            'storage_documents' => is_dir($storage->documentDirectory(0)) || is_dir(dirname($storage->documentDirectory(0))),
            'storage_attachments' => is_dir($storage->attachmentDirectory(0)) || is_dir(dirname($storage->attachmentDirectory(0))),
            'job_queue' => $this->tableOk('job_queue'),
            'mail_logs' => $this->tableOk('mail_logs'),
            'usage_events' => $this->tableOk('usage_events'),
        ];

        $ok = !in_array(false, $checks, true);
        $this->response->json([
            'status' => $ok ? 'ok' : 'degraded',
            'checks' => $checks,
            'timestamp' => date(DATE_ATOM),
        ], $ok ? 200 : 503);
    }

    private function tableOk(string $table): bool
    {
        try {
            return database_table_has_column($this->pdo, $table, 'id');
        } catch (Throwable) {
            return false;
        }
    }

    private function databaseOk(): bool
    {
        try {
            return $this->pdo->query('SELECT 1')->fetchColumn() !== false;
        } catch (Throwable $exception) {
            error_log('Health database check failed: ' . $exception->getMessage());
            return false;
        }
    }

    private function envEnabled(string $key): bool
    {
        return in_array(strtolower((string) getenv($key)), ['1', 'true', 'yes', 'on'], true);
    }
}
