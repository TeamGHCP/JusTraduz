<?php

require_once dirname(__DIR__) . '/core/BaseController.php';
require_once dirname(__DIR__) . '/services/AuditService.php';

class AuditExportController extends BaseController
{
    private AuditService $audit;

    public function __construct()
    {
        parent::__construct();
        $this->audit = new AuditService($this->pdo);
    }

    public function csv(): void
    {
        $this->startSession();
        if (empty($_SESSION['logado']) || ($_SESSION['tipo'] ?? '') !== 'admin') {
            http_response_code(403);
            echo 'Acesso administrativo obrigatorio.';
            return;
        }

        [$where, $params] = $this->filters();
        $sql = 'SELECT a.id, a.created_at, a.user_id, u.nome, u.email, a.action, a.entity_type, a.entity_id, a.details, a.ip_address, a.user_agent
                FROM audit_logs a
                LEFT JOIN users u ON u.id = a.user_id';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY a.created_at DESC LIMIT 5000';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $this->audit->log('audit.export_csv', 'audit', null, ['filters' => $_GET]);

        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="justraduz-auditoria-' . date('Ymd-His') . '.csv"');
            header('Cache-Control: private, no-store, max-age=0');
        }

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['id', 'created_at', 'user_id', 'nome', 'email', 'action', 'entity_type', 'entity_id', 'details', 'ip_address', 'user_agent'], ';');
        while ($row = $stmt->fetch()) {
            fputcsv($out, $row, ';');
        }
        fclose($out);
    }

    private function filters(): array
    {
        $where = [];
        $params = [];
        $userId = (int) ($_GET['user_id'] ?? 0);
        $action = trim((string) ($_GET['action'] ?? ''));
        $entity = trim((string) ($_GET['entity_type'] ?? ''));
        $date = trim((string) ($_GET['date'] ?? ''));
        $severity = (string) ($_GET['severity'] ?? '');

        if ($userId > 0) {
            $where[] = 'a.user_id = ?';
            $params[] = $userId;
        }
        if ($action !== '') {
            $where[] = 'a.action LIKE ?';
            $params[] = '%' . $action . '%';
        }
        if ($entity !== '') {
            $where[] = 'a.entity_type = ?';
            $params[] = $entity;
        }
        if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $where[] = 'DATE(a.created_at) = ?';
            $params[] = $date;
        }
        if ($severity === 'critical') {
            $where[] = "(a.action LIKE '%failed%' OR a.action LIKE '%error%' OR a.action LIKE '%delete%')";
        } elseif ($severity === 'warning') {
            $where[] = "(a.action LIKE 'admin.%' OR a.action LIKE 'case.%' OR a.action LIKE 'schedule.%')";
        } elseif ($severity === 'info') {
            $where[] = "(a.action NOT LIKE '%failed%' AND a.action NOT LIKE '%error%' AND a.action NOT LIKE '%delete%' AND a.action NOT LIKE 'admin.%' AND a.action NOT LIKE 'case.%' AND a.action NOT LIKE 'schedule.%')";
        }

        return [$where, $params];
    }
}
