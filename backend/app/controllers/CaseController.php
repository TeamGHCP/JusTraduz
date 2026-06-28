<?php

require_once dirname(__DIR__) . '/core/BaseController.php';
require_once dirname(__DIR__) . '/services/AuditService.php';
require_once dirname(__DIR__) . '/services/NotificationService.php';
require_once dirname(__DIR__) . '/services/PermissionService.php';
require_once dirname(__DIR__) . '/services/StorageService.php';
require_once dirname(__DIR__) . '/services/UploadScannerService.php';

class CaseController extends BaseController
{
    private const MESSAGE_ATTACHMENT_MAX_SIZE = 25 * 1024 * 1024;
    private const MESSAGE_ATTACHMENT_ALLOWED_EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'txt', 'doc', 'docx'];
    private NotificationService $notifications;
    private AuditService $audit;
    private StorageService $storage;

    public function __construct()
    {
        parent::__construct();
        $this->notifications = new NotificationService($this->pdo);
        $this->audit = new AuditService($this->pdo);
        $this->storage = new StorageService();
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
        $documentId = (int) $this->request->post('document_id', 0);

        if ($titulo === '' || $descricao === '') {
            $this->response->redirect(app_url('/frontend/solicitar-ajuda.php?erro=' . urlencode('Preencha titulo e descricao.')));
        }

        if (!in_array($prioridade, ['baixa', 'media', 'alta'], true)) {
            $prioridade = 'media';
        }

        if ($advogadoId) {
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE id = ? AND tipo = 'advogado' AND status = 'ativo' AND oab_verificado = TRUE");
            $stmt->execute([(int) $advogadoId]);

            if (!$stmt->fetch()) {
                $this->response->redirect(app_url('/frontend/solicitar-ajuda.php?erro=' . urlencode('Advogado inválido.')));
            }
        }

        if ($documentId > 0 && !$this->documentBelongsToCurrentClient($documentId)) {
            $this->response->redirect(app_url('/frontend/solicitar-ajuda.php?erro=' . urlencode('Documento inválido para esta solicitação.')));
        }

        $status = $advogadoId ? 'em_andamento' : 'aberto';
        if ($documentId > 0 && $this->casesHasDocumentIdColumn()) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO cases (cliente_id, advogado_id, document_id, titulo, descricao, status, prioridade) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([(int) $_SESSION['id'], $advogadoId ? (int) $advogadoId : null, $documentId, $titulo, $descricao, $status, $prioridade]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO cases (cliente_id, advogado_id, titulo, descricao, status, prioridade) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([(int) $_SESSION['id'], $advogadoId ? (int) $advogadoId : null, $titulo, $descricao, $status, $prioridade]);
        }

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
            'document_id' => $documentId > 0 ? $documentId : null,
            'status' => $status,
        ]);

        $this->response->redirect(app_url('/frontend/chat.php?case_id=' . $caseId . '&sucesso=' . urlencode('Solicitação criada. Use o chat para acompanhar o atendimento.')));
    }

    public function accept(): void
    {
        $this->startSession();

        if (empty($_SESSION['logado']) || $_SESSION['tipo'] !== 'advogado') {
            $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('Faça login como advogado.')));
        }

        if (!$this->currentProfessionalIsVerified()) {
            $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('Sua OAB ainda precisa ser validada pela administracao.')));
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

        $this->response->redirect(app_url('/frontend/chat.php?case_id=' . $caseId . '&sucesso=' . urlencode('Caso aceito. Continue pelo chat.')));
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
            $this->response->redirect(app_url('/frontend/tarefas.php?erro=' . urlencode('Informe o titulo da tarefa.')));
        }

        $stmt = $this->pdo->prepare('INSERT INTO tasks (case_id, titulo, descricao) VALUES (?, ?, ?)');
        $stmt->execute([$caseId, $titulo, $descricao ?: null]);
        $taskId = (int) $this->pdo->lastInsertId();

        $this->notifications->notifyMany(
            $this->notifications->caseParticipantIds($caseId),
            'Nova tarefa no caso "' . (string) $case['titulo'] . '": ' . $titulo
        );
        $this->audit->log('task.create', 'task', $taskId, ['case_id' => $caseId]);

        $this->response->redirect(app_url('/frontend/tarefas.php?case_id=' . $caseId . '&sucesso=' . urlencode('Tarefa criada.')));
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

        $this->response->redirect(app_url('/frontend/tarefas.php?case_id=' . (int) $task['case_id'] . '&sucesso=' . urlencode('Tarefa atualizada.')));
    }

    public function sendMessage(): void
    {
        $this->startSession();

        if (empty($_SESSION['logado'])) {
            $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('Faça login para enviar mensagens.')));
        }

        $caseId = (int) $this->request->post('case_id', 0);
        $message = mb_substr(trim((string) $this->request->post('mensagem', '')), 0, 4000);
        $file = $_FILES['anexo'] ?? null;
        $hasAttachment = is_array($file) && (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);

        if ($caseId <= 0 || ($message === '' && !$hasAttachment)) {
            $this->response->redirect(app_url('/frontend/chat.php?erro=' . urlencode('Mensagem invalida.')));
        }

        $userId = (int) $_SESSION['id'];
        $type = (string) ($_SESSION['tipo'] ?? '');

        if (!$this->canAccessCaseId($caseId, $userId, $type)) {
            $this->response->redirect(app_url('/frontend/chat.php?erro=' . urlencode('Você não tem acesso a este caso.')));
        }

        $case = $this->caseById($caseId);
        if ($case && (string) ($case['status'] ?? '') === 'finalizado' && $type !== 'admin') {
            $this->response->redirect(app_url('/frontend/chat.php?case_id=' . $caseId . '&erro=' . urlencode('Caso finalizado não aceita novas mensagens.')));
        }

        $attachment = $hasAttachment ? $this->storeMessageAttachment($caseId, $userId, $file) : null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO messages (case_id, sender_id, mensagem, attachment_original_name, attachment_path, attachment_mime, attachment_size)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $caseId,
            $userId,
            $message,
            $attachment['original_name'] ?? null,
            $attachment['path'] ?? null,
            $attachment['mime'] ?? null,
            $attachment['size'] ?? null,
        ]);
        $messageId = (int) $this->pdo->lastInsertId();

        if ($case) {
            $recipients = array_diff($this->notifications->caseParticipantIds($caseId), [$userId]);
            $notificationText = $attachment
                ? 'Nova mensagem com anexo no caso: ' . (string) $case['titulo']
                : 'Nova mensagem no caso: ' . (string) $case['titulo'];
            $this->notifications->notifyMany($recipients, $notificationText);
        }

        $this->audit->log('message.send', 'case', $caseId, [
            'sender_id' => $userId,
            'message_id' => $messageId,
            'has_attachment' => (bool) $attachment,
            'attachment_name' => $attachment['original_name'] ?? null,
        ]);

        $this->response->redirect(app_url('/frontend/chat.php?case_id=' . $caseId));
    }

    public function downloadAttachment(): void
    {
        $this->startSession();

        if (empty($_SESSION['logado'])) {
            http_response_code(401);
            echo 'Faça login para acessar este anexo.';
            return;
        }

        $messageId = (int) $this->request->get('id', 0);
        if ($messageId <= 0) {
            http_response_code(400);
            echo 'Anexo inválido.';
            return;
        }

        $message = $this->messageById($messageId);
        $userId = (int) ($_SESSION['id'] ?? 0);
        $type = (string) ($_SESSION['tipo'] ?? '');

        if (
            !$message
            || empty($message['attachment_path'])
            || !$this->canAccessCaseId((int) $message['case_id'], $userId, $type)
        ) {
            http_response_code(404);
            echo 'Anexo não encontrado ou indisponível para seu perfil.';
            return;
        }

        $absolutePath = $this->messageAttachmentPath($message);
        if ($absolutePath === null || !is_file($absolutePath) || !is_readable($absolutePath)) {
            http_response_code(404);
            echo 'Arquivo não encontrado.';
            return;
        }

        $mime = (string) ($message['attachment_mime'] ?: (mime_content_type($absolutePath) ?: 'application/octet-stream'));
        $filename = $this->safeAttachmentName((string) ($message['attachment_original_name'] ?? ('anexo-' . $messageId)));
        $disposition = $this->request->get('download', '') === '1' ? 'attachment' : 'inline';

        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($absolutePath));
        header('Content-Disposition: ' . $disposition . '; filename="' . addcslashes($filename, "\\\"") . '"');

        readfile($absolutePath);
    }

    private function caseById(int $caseId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cases WHERE id = ?');
        $stmt->execute([$caseId]);
        $case = $stmt->fetch();

        return $case ?: null;
    }

    private function messageById(int $messageId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM messages WHERE id = ?');
        $stmt->execute([$messageId]);
        $message = $stmt->fetch();

        return $message ?: null;
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

        return PermissionService::canManageCase($type, $userId, $case);
    }

    private function currentProfessionalIsVerified(): bool
    {
        $stmt = $this->pdo->prepare("SELECT oab_verificado FROM users WHERE id = ? AND status = 'ativo'");
        $stmt->execute([(int) ($_SESSION['id'] ?? 0)]);

        return (int) ($stmt->fetchColumn() ?: 0) === 1;
    }

    private function documentBelongsToCurrentClient(int $documentId): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM documents WHERE id = ? AND user_id = ?');
        $stmt->execute([$documentId, (int) ($_SESSION['id'] ?? 0)]);

        return (bool) $stmt->fetch();
    }

    private function casesHasDocumentIdColumn(): bool
    {
        static $hasColumn = null;
        if ($hasColumn !== null) {
            return $hasColumn;
        }

        $hasColumn = database_table_has_column($this->pdo, 'cases', 'document_id');
        return $hasColumn;
    }

    private function canAccessCaseId(int $caseId, int $userId, string $type): bool
    {
        if (PermissionService::roleHas($type, 'cases.view_all')) {
            $stmt = $this->pdo->prepare('SELECT id FROM cases WHERE id = ?');
            $stmt->execute([$caseId]);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT id FROM cases WHERE id = ? AND (cliente_id = ? OR advogado_id = ?)'
            );
            $stmt->execute([$caseId, $userId, $userId]);
        }

        return (bool) $stmt->fetch();
    }

    private function storeMessageAttachment(int $caseId, int $userId, array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            $this->response->redirect(app_url('/frontend/chat.php?case_id=' . $caseId . '&erro=' . urlencode('Anexo inválido ou não enviado.')));
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MESSAGE_ATTACHMENT_MAX_SIZE) {
            $this->response->redirect(app_url('/frontend/chat.php?case_id=' . $caseId . '&erro=' . urlencode('O anexo deve ter no maximo 25 MB.')));
        }

        $originalName = (string) ($file['name'] ?? 'anexo');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $mime = $tmpName !== '' && is_file($tmpName) ? (mime_content_type($tmpName) ?: '') : '';

        if (!in_array($extension, self::MESSAGE_ATTACHMENT_ALLOWED_EXTENSIONS, true) || !$this->isAllowedAttachmentMime($extension, $mime)) {
            $this->response->redirect(app_url('/frontend/chat.php?case_id=' . $caseId . '&erro=' . urlencode('Formato de anexo não permitido.')));
        }

        if ($extension === 'docx' && !$this->hasValidDocxStructure($tmpName)) {
            $this->audit->log('message.attachment_blocked', 'case', $caseId, [
                'sender_id' => $userId,
                'attachment_name' => $originalName,
                'reason' => 'DOCX sem estrutura interna obrigatoria.',
            ]);
            $this->response->redirect(app_url('/frontend/chat.php?case_id=' . $caseId . '&erro=' . urlencode('Arquivo DOCX inválido ou corrompido.')));
        }

        $scanner = new UploadScannerService();
        if (!$scanner->scan($tmpName, $originalName, $mime)) {
            $this->audit->log('message.attachment_blocked', 'case', $caseId, [
                'sender_id' => $userId,
                'attachment_name' => $originalName,
                'reason' => $scanner->lastError(),
            ]);
            $this->response->redirect(app_url('/frontend/chat.php?case_id=' . $caseId . '&erro=' . urlencode($scanner->lastError() ?: 'Anexo reprovado pelo scanner de segurança.')));
        }

        $storageDir = $this->storage->attachmentDirectory($caseId);
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0775, true);
        }

        $safeName = bin2hex(random_bytes(12)) . '.' . $extension;
        $destination = $storageDir . '/' . $safeName;

        if (!move_uploaded_file($tmpName, $destination)) {
            $this->response->redirect(app_url('/frontend/chat.php?case_id=' . $caseId . '&erro=' . urlencode('Não foi possível salvar o anexo.')));
        }

        return [
            'original_name' => $this->safeAttachmentName($originalName),
            'path' => $this->storage->attachmentReference($caseId, $safeName),
            'mime' => $mime,
            'size' => $size,
        ];
    }

    private function messageAttachmentPath(array $message): ?string
    {
        return $this->storage->attachmentPathFromReference((string) ($message['attachment_path'] ?? ''));
    }

    private function isAllowedAttachmentMime(string $extension, string $mime): bool
    {
        $allowedByExtension = [
            'pdf' => ['application/pdf'],
            'png' => ['image/png'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'webp' => ['image/webp'],
            'txt' => ['text/plain'],
            'doc' => ['application/msword'],
            'docx' => [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip',
            ],
        ];

        return in_array($mime, $allowedByExtension[$extension] ?? [], true);
    }

    private function hasValidDocxStructure(string $path): bool
    {
        if (!class_exists(ZipArchive::class) || !is_readable($path)) {
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return false;
        }

        $requiredEntries = [
            '[Content_Types].xml',
            '_rels/.rels',
            'word/document.xml',
        ];

        foreach ($requiredEntries as $entry) {
            if ($zip->locateName($entry) === false) {
                $zip->close();
                return false;
            }
        }

        $zip->close();
        return true;
    }

    private function safeAttachmentName(string $filename): string
    {
        $filename = trim(preg_replace('/[^\w.\- ]+/u', '_', $filename) ?? '');
        return $filename !== '' ? $filename : 'anexo';
    }
}
