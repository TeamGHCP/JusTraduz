<?php

require_once dirname(__DIR__) . '/core/BaseController.php';
require_once dirname(__DIR__) . '/services/AuditService.php';

class NotificationController extends BaseController
{
    private AuditService $audit;

    public function __construct()
    {
        parent::__construct();
        $this->audit = new AuditService($this->pdo);
    }

    public function markRead(): void
    {
        $this->startSession();

        if (empty($_SESSION['logado'])) {
            $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('Faça login para continuar.')));
        }

        $userId = (int) ($_SESSION['id'] ?? 0);
        $notificationId = (int) $this->request->post('notification_id', 0);
        $all = (string) $this->request->post('all', '') === '1';

        if ($all) {
            $stmt = $this->pdo->prepare('UPDATE notifications SET lida = TRUE WHERE user_id = ?');
            $stmt->execute([$userId]);
            $this->audit->log('notification.mark_all_read', 'notification', null);
        } elseif ($notificationId > 0) {
            $stmt = $this->pdo->prepare('UPDATE notifications SET lida = TRUE WHERE id = ? AND user_id = ?');
            $stmt->execute([$notificationId, $userId]);
            $this->audit->log('notification.mark_read', 'notification', $notificationId);
        }

        $this->response->redirect(app_url('/frontend/notificacoes.php'));
    }
}
