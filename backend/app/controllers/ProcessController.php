<?php

require_once dirname(__DIR__) . '/core/BaseController.php';
require_once dirname(__DIR__) . '/middlewares/CsrfMiddleware.php';
require_once dirname(__DIR__) . '/services/AuditService.php';
require_once dirname(__DIR__) . '/services/DataJudService.php';
require_once dirname(__DIR__) . '/services/UsageLimiter.php';

class ProcessController extends BaseController
{
    private AuditService $audit;

    public function __construct()
    {
        parent::__construct();
        $this->audit = new AuditService($this->pdo);
    }

    public function sync(): void
    {
        $this->startSession();
        CsrfMiddleware::validate();

        if (empty($_SESSION['logado']) || empty($_SESSION['id'])) {
            $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('Faça login para consultar processos.')));
        }

        $userId = (int) $_SESSION['id'];
        $type = (string) ($_SESSION['tipo'] ?? '');
        $service = new DataJudService($this->pdo);

        if ($type === 'cliente') {
            $stmt = $this->pdo->prepare("SELECT cpf FROM users WHERE id = ? AND status = 'ativo'");
            $stmt->execute([$userId]);
            $cpf = preg_replace('/\D+/', '', (string) $stmt->fetchColumn()) ?? '';

            if (!$this->isValidCpf($cpf)) {
                $this->response->redirect(app_url('/frontend/processos.php?erro=' . urlencode('Cadastre seu CPF no perfil antes de consultar processos.')));
            }

            $processNumber = (string) $this->request->post('process_number', '');
            $lgpdConsent = $this->request->post('lgpd_consent', '') === '1';
            $usage = new UsageLimiter($this->pdo);
            $quota = $usage->allow($userId, 'datajud_cnj');
            if (!$quota['allowed']) {
                $this->audit->log('usage.limit_blocked', 'external_process', null, [
                    'feature' => 'datajud_cnj',
                    'limit' => (int) ($quota['limit'] ?? 0),
                    'used' => (int) ($quota['used'] ?? 0),
                ]);
                $this->response->redirect(app_url('/frontend/processos.php?erro=' . urlencode($usage->limitMessage('datajud_cnj', $quota))));
            }

            $result = $service->syncProcessByCnj($userId, $cpf, $processNumber, $lgpdConsent);
            if ($result['success'] ?? false) {
                $usage->record($userId, 'datajud_cnj', 1, null, ['process_number' => preg_replace('/\D+/', '', $processNumber) ?? '']);
            }
            $this->audit->log('datajud.cnj_sync', 'user', $userId, [
                'success' => (bool) $result['success'],
                'imported' => (int) ($result['imported'] ?? 0),
                'configured' => (bool) ($result['configured'] ?? true),
                'process_number' => preg_replace('/\D+/', '', $processNumber) ?? '',
                'lgpd_consent' => $lgpdConsent,
            ]);

            $this->redirectWithSyncResult($result);
        }

        $this->response->redirect(app_url('/frontend/processos.php?erro=' . urlencode('A consulta DataJud por CNJ está disponível para clientes nesta versão inicial.')));
    }

    private function redirectWithSyncResult(array $result): void
    {
        if (!($result['success'] ?? false)) {
            $message = (string) ($result['message'] ?? 'Falha ao sincronizar processos.');
            if (!($result['configured'] ?? true)) {
                $message = 'A integração DataJud não está autorizada neste ambiente. Configure DATAJUD_API_KEY se o endpoint exigir chave.';
            }

            $this->response->redirect(app_url('/frontend/processos.php?erro=' . urlencode($message)));
        }

        $message = (string) ($result['message'] ?? 'Processo consultado.');
        $imported = (int) ($result['imported'] ?? 0);
        if ($imported > 0) {
            $message .= ' Registros atualizados: ' . $imported . '.';
        }

        $this->response->redirect(app_url('/frontend/processos.php?sucesso=' . urlencode($message)));
    }

    private function isValidCpf(?string $cpf): bool
    {
        $digits = preg_replace('/\D+/', '', (string) $cpf) ?? '';

        if (strlen($digits) !== 11 || preg_match('/^(\d)\1{10}$/', $digits)) {
            return false;
        }

        for ($position = 9; $position <= 10; $position++) {
            $sum = 0;
            for ($index = 0; $index < $position; $index++) {
                $sum += (int) $digits[$index] * (($position + 1) - $index);
            }

            $checkDigit = ($sum * 10) % 11;
            if ($checkDigit === 10) {
                $checkDigit = 0;
            }

            if ($checkDigit !== (int) $digits[$position]) {
                return false;
            }
        }

        return true;
    }
}
