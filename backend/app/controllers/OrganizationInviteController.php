<?php

require_once dirname(__DIR__) . '/core/BaseController.php';
require_once dirname(__DIR__) . '/services/OrganizationInviteService.php';

class OrganizationInviteController extends BaseController
{
    private OrganizationInviteService $invites;

    public function __construct()
    {
        parent::__construct();
        $this->invites = new OrganizationInviteService($this->pdo);
    }

    public function accept(): void
    {
        $this->startSession();

        $token = trim((string) $this->request->get('token', ''));
        if ($token === '') {
            $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('Convite inválido.')));
        }

        if (empty($_SESSION['logado']) || (int) ($_SESSION['id'] ?? 0) <= 0) {
            $_SESSION['pending_org_invite_token'] = $token;
            $result = $this->invites->acceptToken($token, null);
            if (($result['reason'] ?? '') === 'expired') {
                $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('Este convite expirou. Peça um novo convite ao responsável pelo escritório.')));
            }
            if (($result['reason'] ?? '') === 'lawyer_required') {
                unset($_SESSION['pending_org_invite_token']);
                $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('O plano Escritório aceita somente participantes cadastrados como advogado.')));
            }
            if (($result['reason'] ?? '') !== 'auth_required') {
                $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('Convite inválido ou já utilizado.')));
            }

            $target = !empty($result['has_account'])
                ? '/frontend/login.html?sucesso=' . urlencode('Entre para aceitar o convite do escritório.')
                : '/frontend/login.html?cadastro&sucesso=' . urlencode('Crie sua conta para aceitar o convite do escritório.');
            $this->response->redirect(app_url($target));
        }

        $result = $this->invites->acceptToken($token, (int) $_SESSION['id']);
        if (($result['ok'] ?? false) === true) {
            unset($_SESSION['pending_org_invite_token']);
            $this->response->redirect(app_url('/frontend/dashboard-advogado.php?sucesso=' . urlencode('Convite aceito. Você agora faz parte do plano Escritório.')));
        }

        $message = match ((string) ($result['reason'] ?? 'invalid')) {
            'expired' => 'Este convite expirou. Peça um novo convite ao responsável pelo escritório.',
            'wrong_user' => 'Este convite pertence a outro e-mail. Entre com a conta convidada.',
            'lawyer_required' => 'O plano Escritório aceita somente participantes cadastrados como advogado.',
            'invalid_inviter' => 'Este convite não foi emitido por uma conta de advogado válida.',
            default => 'Convite inválido ou já utilizado.',
        };
        $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode($message)));
    }
}
