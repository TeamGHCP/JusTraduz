<?php

require_once dirname(__DIR__) . '/core/BaseController.php';
require_once dirname(__DIR__) . '/services/AuditService.php';
require_once dirname(__DIR__) . '/services/NotificationService.php';

class CaseController extends BaseController
{
    private NotificationService $notifications;
    private AuditService $audit;

    public function __construct()
    {
        parent::__construct();
        $this->notifications = new NotificationService($this->pdo);
        $this->audit = new AuditService($this->pdo);
    }

    public function create(): void
    {
        $this->startSession();

        if (empty($_SESSION['logado']) || $_SESSION['tipo'] !== 'cliente') {
            $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('Faça login como cliente para solicitar ajuda.')));
        }

        $titulo = trim((string) $this->request->post('titulo', ''));
        $descricao = trim((string) $this->request->post('descricao', ''));
        $prioridade = (string) $this->request->post('prioridade', 'media');
        $advogadoId = $this->request->post('advogado_id') ?: null;

        if (!$titulo || !$descricao) {
            $this->response->redirect(app_url('/frontend/solicitar-ajuda.php?erro=' . urlencode('Preencha título e descrição.')));
        }

        if (!in_array($prioridade, ['baixa', 'media', 'alta'], true)) {
            $prioridade = 'media';
        }

        if ($advogadoId) {
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE id = ? AND tipo = 'advogado' AND status = 'ativo' AND (oab_verificado = TRUE OR (status_cna = 'pendente' AND COALESCE(oab, '') <> '' AND COALESCE(oab_uf, '') <> ''))");
            $stmt->execute([(int) $advogadoId]);

            if (!$stmt->fetch()) {
                $this->response->redirect(app_url('/frontend/solicitar-ajuda.php?erro=' . urlencode('Advogado inválido.')));
            }
        }

        $status = $advogadoId ? 'em_andamento' : 'aberto';
        $stmt = $this->pdo->prepare(
            'INSERT INTO cases (cliente_id, advogado_id, titulo, descricao, status, prioridade) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([(int) $_SESSION['id'], $advogadoId ? (int) $advogadoId : null, $titulo, $descricao, $status, $prioridade]);
        $caseId = (int) $this->pdo->lastInsertId();

        if ($advogadoId) {
            $this->notifications->notify((int) $advogadoId, 'Você recebeu uma nova solicitação: ' . $titulo);
        } else {
            $this->notifications->notifyMany($this->notifications->activeLawyers(), 'Nova solicitação aberta: ' . $titulo);
        }

        $this->notifications->notifyMany($this->notifications->activeAdmins(), 'Nova solicitação cadastrada: ' . $titulo);
        $this->notifications->notify((int) $_SESSION['id'], 'Sua solicitação foi criada: ' . $titulo);
        $this->audit->log('case.create', 'case', $caseId, [
            'prioridade' => $prioridade,
            'advogado_id' => $advogadoId ? (int) $advogadoId : null,
            'status' => $status,
        ]);

        $this->response->redirect(app_url('/frontend/acompanhar-solicitacoes.php?sucesso=' . urlencode('Solicitação criada.')));
    }

    public function accept(): void
    {
        $this->startSession();

        if (empty($_SESSION['logado']) || $_SESSION['tipo'] !== 'advogado') {
            $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('Faça login como advogado.')));
        }

        $caseId = (int) $this->request->post('case_id', 0);

        if ($caseId <= 0) {
            $this->response->redirect(app_url('/frontend/acompanhar-solicitacoes.php?erro=' . urlencode('Caso inválido.')));
        }

        $stmt = $this->pdo->prepare(
            "UPDATE cases SET advogado_id = ?, status = 'em_andamento' WHERE id = ? AND advogado_id IS NULL AND status = 'aberto'"
        );
        $stmt->execute([(int) $_SESSION['id'], $caseId]);

        if ($stmt->rowCount() > 0) {
            $case = $this->caseById($caseId);
            $this->notifications->notify((int) ($case['cliente_id'] ?? 0), 'Um advogado aceitou sua solicitação: ' . (string) ($case['titulo'] ?? 'Caso'));
            $this->audit->log('case.accept', 'case', $caseId, ['advogado_id' => (int) $_SESSION['id']]);
        }

        $this->response->redirect(app_url('/frontend/acompanhar-solicitacoes.php'));
    }

    public function updateStatus(): void
    {
        $this->startSession();

        if (empty($_SESSION['logado'])) {
            $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('Faça login para continuar.')));
        }

        $caseId = (int) $this->request->post('case_id', 0);
        $status = (string) $this->request->post('status', '');

        if ($caseId <= 0 || !in_array($status, ['aberto', 'em_andamento', 'finalizado'], true)) {
            $this->response->redirect(app_url('/frontend/acompanhar-solicitacoes.php?erro=' . urlencode('Status inválido.')));
        }

        if (($_SESSION['tipo'] ?? '') === 'estagiario') {
            $this->response->redirect(app_url('/frontend/acompanhar-solicitacoes.php?erro=' . urlencode('Estagiários não podem alterar status de solicitações.')));
        }

        $case = $this->caseById($caseId);
        if (!$case || !$this->canManageCase($case)) {
            $this->response->redirect(app_url('/frontend/acompanhar-solicitacoes.php?erro=' . urlencode('Você não tem acesso a este caso.')));
        }

        if (($_SESSION['tipo'] ?? '') === 'cliente' && $status !== 'finalizado') {
            $this->response->redirect(app_url('/frontend/acompanhar-solicitacoes.php?erro=' . urlencode('Clientes podem apenas finalizar solicitações.')));
        }

        if ($status === 'em_andamento' && empty($case['advogado_id'])) {
            $this->response->redirect(app_url('/frontend/acompanhar-solicitacoes.php?erro=' . urlencode('Casos sem advogado não podem ir para em andamento.')));
        }

        $stmt = $this->pdo->prepare('UPDATE cases SET status = ? WHERE id = ?');
        $stmt->execute([$status, $caseId]);

        $this->notifications->notifyMany(
            $this->notifications->caseParticipantIds($caseId),
            'Status atualizado para "' . $status . '" no caso: ' . (string) $case['titulo']
        );
        $this->audit->log('case.status_update', 'case', $caseId, ['status' => $status]);

        $this->response->redirect(app_url('/frontend/acompanhar-solicitacoes.php?sucesso=' . urlencode('Status atualizado.')));
    }

    public function createTask(): void
    {
        $this->startSession();

        if (empty($_SESSION['logado']) || $_SESSION['tipo'] === 'cliente') {
            $this->response->redirect(app_url('/frontend/tarefas.php?erro=' . urlencode('Perfil sem permissão para criar tarefas.')));
        }

        $caseId = (int) $this->request->post('case_id', 0);
        $titulo = trim((string) $this->request->post('titulo', ''));
        $descricao = trim((string) $this->request->post('descricao', ''));

        $case = $this->caseById($caseId);
        if (!$case || !$this->canManageCase($case)) {
            $this->response->redirect(app_url('/frontend/tarefas.php?erro=' . urlencode('Caso inválido ou indisponível.')));
        }

        if ($titulo === '') {
            $this->response->redirect(app_url('/frontend/tarefas.php?erro=' . urlencode('Informe o título da tarefa.')));
        }

        $stmt = $this->pdo->prepare('INSERT INTO tasks (case_id, titulo, descricao) VALUES (?, ?, ?)');
        $stmt->execute([$caseId, $titulo, $descricao ?: null]);
        $taskId = (int) $this->pdo->lastInsertId();

        $this->notifications->notifyMany(
            $this->notifications->caseParticipantIds($caseId),
            'Nova tarefa no caso "' . (string) $case['titulo'] . '": ' . $titulo
        );
        $this->audit->log('task.create', 'task', $taskId, ['case_id' => $caseId]);

        $this->response->redirect(app_url('/frontend/tarefas.php?sucesso=' . urlencode('Tarefa criada.')));
    }

    public function updateTask(): void
    {
        $this->startSession();

        if (empty($_SESSION['logado']) || $_SESSION['tipo'] === 'cliente') {
            $this->response->redirect(app_url('/frontend/tarefas.php?erro=' . urlencode('Perfil sem permissão para atualizar tarefas.')));
        }

        $taskId = (int) $this->request->post('task_id', 0);
        $status = (string) $this->request->post('status', '');

        if ($taskId <= 0 || !in_array($status, ['pendente', 'em_andamento', 'concluida'], true)) {
            $this->response->redirect(app_url('/frontend/tarefas.php?erro=' . urlencode('Dados inválidos da tarefa.')));
        }

        $task = $this->taskById($taskId);
        if (!$task || !$this->canManageCase($task)) {
            $this->response->redirect(app_url('/frontend/tarefas.php?erro=' . urlencode('Tarefa indisponível para seu perfil.')));
        }

        $stmt = $this->pdo->prepare('UPDATE tasks SET status = ? WHERE id = ?');
        $stmt->execute([$status, $taskId]);

        $this->notifications->notifyMany(
            $this->notifications->caseParticipantIds((int) $task['case_id']),
            'Tarefa atualizada para "' . $status . '": ' . (string) $task['titulo']
        );
        $this->audit->log('task.update', 'task', $taskId, ['status' => $status, 'case_id' => (int) $task['case_id']]);

        $this->response->redirect(app_url('/frontend/tarefas.php?sucesso=' . urlencode('Tarefa atualizada.')));
    }

    public function sendMessage(): void
    {
        $this->startSession();

        if (empty($_SESSION['logado'])) {
            $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('Faça login para enviar mensagens.')));
        }

        $caseId = (int) $this->request->post('case_id', 0);
        $message = trim((string) $this->request->post('mensagem', ''));

        if ($caseId <= 0 || !$message) {
            $this->response->redirect(app_url('/frontend/chat.php?erro=' . urlencode('Mensagem inválida.')));
        }

        $userId = (int) $_SESSION['id'];
        $type = (string) ($_SESSION['tipo'] ?? '');

        if ($type === 'admin') {
            $stmt = $this->pdo->prepare('SELECT id FROM cases WHERE id = ?');
            $stmt->execute([$caseId]);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT id FROM cases WHERE id = ? AND (cliente_id = ? OR advogado_id = ?)'
            );
            $stmt->execute([$caseId, $userId, $userId]);
        }

        if (!$stmt->fetch()) {
            $this->response->redirect(app_url('/frontend/chat.php?erro=' . urlencode('Você não tem acesso a este caso.')));
        }

        $stmt = $this->pdo->prepare('INSERT INTO messages (case_id, sender_id, mensagem) VALUES (?, ?, ?)');
        $stmt->execute([$caseId, $userId, $message]);

        $case = $this->caseById($caseId);
        if ($case) {
            $recipients = array_diff($this->notifications->caseParticipantIds($caseId), [$userId]);
            $this->notifications->notifyMany($recipients, 'Nova mensagem no caso: ' . (string) $case['titulo']);
        }
        $this->audit->log('message.send', 'case', $caseId, ['sender_id' => $userId]);

        $this->response->redirect(app_url('/frontend/chat.php?case_id=' . $caseId));
    }

    private function caseById(int $caseId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cases WHERE id = ?');
        $stmt->execute([$caseId]);
        $case = $stmt->fetch();

        return $case ?: null;
    }

    private function taskById(int $taskId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.*, c.cliente_id, c.advogado_id, c.titulo AS case_title
             FROM tasks t
             INNER JOIN cases c ON c.id = t.case_id
             WHERE t.id = ?'
        );
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();

        return $task ?: null;
    }

    private function canManageCase(array $case): bool
    {
        $userId = (int) ($_SESSION['id'] ?? 0);
        $type = (string) ($_SESSION['tipo'] ?? '');

        return match ($type) {
            'admin' => true,
            'advogado' => (int) ($case['advogado_id'] ?? 0) === $userId,
            'cliente' => (int) ($case['cliente_id'] ?? 0) === $userId,
            default => false,
        };
    }
}
