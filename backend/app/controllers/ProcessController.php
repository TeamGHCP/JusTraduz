<?php

require_once dirname(__DIR__) . '/core/BaseController.php';
require_once dirname(__DIR__) . '/middlewares/CsrfMiddleware.php';
require_once dirname(__DIR__) . '/services/AuditService.php';
require_once dirname(__DIR__) . '/services/JusbrasilService.php';

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
            $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('Faca login para sincronizar processos.')));
        }

        $userId = (int) $_SESSION['id'];
        $type = (string) ($_SESSION['tipo'] ?? '');
        $service = new JusbrasilService($this->pdo);

        if ($type === 'cliente') {
            $stmt = $this->pdo->prepare("SELECT cpf FROM users WHERE id = ? AND status = 'ativo'");
            $stmt->execute([$userId]);
            $cpf = preg_replace('/\D+/', '', (string) $stmt->fetchColumn()) ?? '';

            if (strlen($cpf) !== 11) {
                $this->response->redirect(app_url('/frontend/processos.php?erro=' . urlencode('Cadastre seu CPF no perfil antes de buscar processos.')));
            }

            $result = $service->syncCpfProcesses($userId, $cpf);
            $this->audit->log('jusbrasil.cpf_sync', 'user', $userId, [
                'success' => (bool) $result['success'],
                'imported' => (int) ($result['imported'] ?? 0),
                'configured' => (bool) ($result['configured'] ?? true),
            ]);

            $this->redirectWithSyncResult($result);
        }

        if (in_array($type, ['advogado', 'estagiario'], true)) {
            $stmt = $this->pdo->prepare("SELECT nome, oab, oab_uf, oab_parametro, oab_verificado FROM users WHERE id = ? AND status = 'ativo'");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user || (int) ($user['oab_verificado'] ?? 0) !== 1) {
                $this->response->redirect(app_url('/frontend/processos.php?erro=' . urlencode('Sua OAB precisa estar validada para buscar processos.')));
            }

            $result = $service->syncOabProcesses(
                $userId,
                (string) $user['nome'],
                (string) $user['oab'],
                (string) $user['oab_uf'],
                (string) ($user['oab_parametro'] ?? ''),
                $type
            );

            if (!empty($result['correlation_id'])) {
                $stmt = $this->pdo->prepare('UPDATE users SET oab_parametro = ? WHERE id = ?');
                $stmt->execute([(string) $result['correlation_id'], $userId]);
            }

            $this->audit->log('jusbrasil.oab_sync', 'user', $userId, [
                'success' => (bool) $result['success'],
                'imported' => (int) ($result['imported'] ?? 0),
                'configured' => (bool) ($result['configured'] ?? true),
            ]);

            $this->redirectWithSyncResult($result);
        }

        $this->response->redirect(app_url('/frontend/processos.php?erro=' . urlencode('Perfil sem consulta de processos.')));
    }

    private function redirectWithSyncResult(array $result): void
    {
        if (!($result['success'] ?? false)) {
            $message = (string) ($result['message'] ?? 'Falha ao sincronizar processos.');
            if (!($result['configured'] ?? true)) {
                $message = 'A integracao externa nao esta configurada neste ambiente. A tela continua funcionando com processos ja importados e dados demo.';
            }

            $this->response->redirect(app_url('/frontend/processos.php?erro=' . urlencode($message)));
        }

        $message = (string) ($result['message'] ?? 'Processos sincronizados.');
        $imported = (int) ($result['imported'] ?? 0);
        if ($imported > 0) {
            $message .= ' Registros atualizados: ' . $imported . '.';
        }

        $this->response->redirect(app_url('/frontend/processos.php?sucesso=' . urlencode($message)));
    }
}
