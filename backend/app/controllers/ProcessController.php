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
            $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('Faca login para consultar processos.')));
        }

        $userId = (int) $_SESSION['id'];
        $type = (string) ($_SESSION['tipo'] ?? '');
        $service = new DataJudService($this->pdo);

        if ($type === 'cliente') {
            $stmt = $this->pdo->prepare("SELECT cpf FROM users WHERE id = ? AND status = 'ativo'");
            $stmt->execute([$userId]);
            $cpf = preg_replace('/\D+/', '', (string) $stmt->fetchColumn()) ?? '';

            if (strlen($cpf) !== 11) {
                $this->response->redirect(app_url('/frontend/processos.php?erro=' . urlencode('Cadastre seu CPF no perfil antes de consultar processos.')));
            }

            $processNumber = (string) $this->request->post('process_number', '');
            $lgpdConsent = $this->request->post('lgpd_consent', '') === '1';
            $usage = new UsageLimiter($this->pdo);
            $quota = $usage->allow($userId, 'datajud_cnj');
            if (!$quota['allowed']) {
                $this->response->redirect(app_url('/frontend/processos.php?erro=' . urlencode('Limite diario de consultas DataJud atingido. Tente novamente amanha.')));
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

        $this->response->redirect(app_url('/frontend/processos.php?erro=' . urlencode('A consulta DataJud por CNJ esta disponivel para clientes nesta versao inicial.')));
    }

    private function redirectWithSyncResult(array $result): void
    {
        if (!($result['success'] ?? false)) {
            $message = (string) ($result['message'] ?? 'Falha ao sincronizar processos.');
            if (!($result['configured'] ?? true)) {
                $message = 'A integracao DataJud nao esta autorizada neste ambiente. Configure DATAJUD_API_KEY se o endpoint exigir chave.';
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
}
