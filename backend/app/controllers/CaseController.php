<?php

require_once dirname(__DIR__) . '/core/Request.php';
require_once dirname(__DIR__) . '/core/Response.php';

class CaseController
{
    private Request $request;
    private Response $response;
    private PDO $pdo;

    public function __construct()
    {
        require_once dirname(__DIR__) . '/config/database.php';
        $this->request = new Request();
        $this->response = new Response();
        $this->pdo = $pdo;
    }

    public function create(): void
    {
        session_start();

        if (empty($_SESSION['logado']) || $_SESSION['tipo'] !== 'cliente') {
            $this->response->redirect('/justraduz/frontend/login.html?erro=' . urlencode('Faça login como cliente para solicitar ajuda.'));
        }

        $titulo = trim((string) $this->request->post('titulo', ''));
        $descricao = trim((string) $this->request->post('descricao', ''));
        $prioridade = (string) $this->request->post('prioridade', 'media');
        $advogadoId = $this->request->post('advogado_id') ?: null;

        if (!$titulo || !$descricao) {
            $this->response->redirect('/justraduz/frontend/solicitar-ajuda.php?erro=' . urlencode('Preencha título e descrição.'));
        }

        if (!in_array($prioridade, ['baixa', 'media', 'alta'], true)) {
            $prioridade = 'media';
        }

        if ($advogadoId) {
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE id = ? AND tipo = 'advogado' AND status = 'ativo'");
            $stmt->execute([(int) $advogadoId]);

            if (!$stmt->fetch()) {
                $this->response->redirect('/justraduz/frontend/solicitar-ajuda.php?erro=' . urlencode('Advogado inválido.'));
            }
        }

        $status = $advogadoId ? 'em_andamento' : 'aberto';
        $stmt = $this->pdo->prepare(
            'INSERT INTO cases (cliente_id, advogado_id, titulo, descricao, status, prioridade) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([(int) $_SESSION['id'], $advogadoId ? (int) $advogadoId : null, $titulo, $descricao, $status, $prioridade]);

        $this->response->redirect('/justraduz/frontend/acompanhar-solicitacoes.php?sucesso=' . urlencode('Solicitação criada.'));
    }

    public function accept(): void
    {
        session_start();

        if (empty($_SESSION['logado']) || $_SESSION['tipo'] !== 'advogado') {
            $this->response->redirect('/justraduz/frontend/login.html?erro=' . urlencode('Faça login como advogado.'));
        }

        $caseId = (int) $this->request->get('id', 0);

        if ($caseId <= 0) {
            $this->response->redirect('/justraduz/frontend/acompanhar-solicitacoes.php?erro=' . urlencode('Caso inválido.'));
        }

        $stmt = $this->pdo->prepare(
            "UPDATE cases SET advogado_id = ?, status = 'em_andamento' WHERE id = ? AND advogado_id IS NULL AND status = 'aberto'"
        );
        $stmt->execute([(int) $_SESSION['id'], $caseId]);

        $this->response->redirect('/justraduz/frontend/acompanhar-solicitacoes.php');
    }

    public function sendMessage(): void
    {
        session_start();

        if (empty($_SESSION['logado'])) {
            $this->response->redirect('/justraduz/frontend/login.html?erro=' . urlencode('Faça login para enviar mensagens.'));
        }

        $caseId = (int) $this->request->post('case_id', 0);
        $message = trim((string) $this->request->post('mensagem', ''));

        if ($caseId <= 0 || !$message) {
            $this->response->redirect('/justraduz/frontend/chat.php?erro=' . urlencode('Mensagem inválida.'));
        }

        $stmt = $this->pdo->prepare(
            'SELECT id FROM cases WHERE id = ? AND (cliente_id = ? OR advogado_id = ?)'
        );
        $stmt->execute([$caseId, (int) $_SESSION['id'], (int) $_SESSION['id']]);

        if (!$stmt->fetch()) {
            $this->response->redirect('/justraduz/frontend/chat.php?erro=' . urlencode('Você não tem acesso a este caso.'));
        }

        $stmt = $this->pdo->prepare('INSERT INTO messages (case_id, sender_id, mensagem) VALUES (?, ?, ?)');
        $stmt->execute([$caseId, (int) $_SESSION['id'], $message]);

        $this->response->redirect('/justraduz/frontend/chat.php?case_id=' . $caseId);
    }
}
