<?php

require_once dirname(__DIR__) . '/core/BaseController.php';
require_once dirname(__DIR__) . '/services/AuditService.php';
require_once dirname(__DIR__) . '/services/GeminiService.php';
require_once dirname(__DIR__) . '/services/JobQueueService.php';
require_once dirname(__DIR__) . '/services/NotificationService.php';
require_once dirname(__DIR__) . '/services/OcrService.php';
require_once dirname(__DIR__) . '/services/PdfTextExtractor.php';
require_once dirname(__DIR__) . '/services/StorageService.php';
require_once dirname(__DIR__) . '/services/UploadScannerService.php';
require_once dirname(__DIR__) . '/services/UsageLimiter.php';
require_once dirname(__DIR__) . '/middlewares/CsrfMiddleware.php';

class DocumentController extends BaseController
{
    private NotificationService $notifications;
    private AuditService $audit;
    private StorageService $storage;
    private UsageLimiter $usage;

    public function __construct()
    {
        parent::__construct();
        $this->notifications = new NotificationService($this->pdo);
        $this->audit = new AuditService($this->pdo);
        $this->storage = new StorageService();
        $this->usage = new UsageLimiter($this->pdo);
    }

    public function upload(): void
    {
        $this->startSession();

        // Validação CSRF adicional (defensiva)
        CsrfMiddleware::validate();

        if (empty($_SESSION['logado']) || $_SESSION['tipo'] !== 'cliente') {
            $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('Faça login como cliente para enviar documentos.')));
        }

        if (empty($_FILES['documento']) || $_FILES['documento']['error'] !== UPLOAD_ERR_OK) {
            $this->response->redirect(app_url('/frontend/dashboard-cliente.php?erro=' . urlencode('Arquivo inválido ou não enviado.')));
        }

        $file = $_FILES['documento'];
        $userId = (int) $_SESSION['id'];
        $quota = $this->usage->allow($userId, 'document_upload');
        if (!$quota['allowed']) {
            $this->response->redirect(app_url('/frontend/dashboard-cliente.php?erro=' . urlencode('Limite diario de uploads atingido. Tente novamente amanha.')));
        }

        $maxSize = 50 * 1024 * 1024;
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'png', 'jpg', 'jpeg', 'webp'];
        $allowedMimes = ['application/pdf', 'image/png', 'image/jpeg', 'image/webp'];

        if ($file['size'] <= 0 || $file['size'] > $maxSize) {
            $this->response->redirect(app_url('/frontend/dashboard-cliente.php?erro=' . urlencode('O arquivo deve ter no m?ximo 50 MB.')));
        }

        $mime = mime_content_type($file['tmp_name']) ?: '';
        if (!in_array($extension, $allowedExtensions, true) || !in_array($mime, $allowedMimes, true)) {
            $this->response->redirect(app_url('/frontend/dashboard-cliente.php?erro=' . urlencode('Formato não permitido.')));
        }

        $scanner = new UploadScannerService();
        if (!$scanner->scan((string) $file['tmp_name'], (string) $file['name'], $mime)) {
            $this->audit->log('document.upload_blocked', 'document', null, [
                'nome_arquivo' => $file['name'],
                'mime' => $mime,
                'reason' => $scanner->lastError(),
            ]);
            $this->response->redirect(app_url('/frontend/dashboard-cliente.php?erro=' . urlencode($scanner->lastError() ?: 'Arquivo reprovado pelo scanner de segurança.')));
        }

        $storageDir = $this->storage->documentDirectory($userId);

        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0775, true);
        }

        $safeName = bin2hex(random_bytes(12)) . '.' . $extension;
        $destination = $storageDir . '/' . $safeName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $this->response->redirect(app_url('/frontend/dashboard-cliente.php?erro=' . urlencode('Não foi possível salvar o arquivo.')));
        }

        $relativePath = $this->storage->documentReference($userId, $safeName);

        $textoExtraido = null;
        if ($extension === 'pdf') {
            $textoExtraido = PdfTextExtractor::extract($destination);
            if ($textoExtraido === '') {
                $textoExtraido = 'Não foi possível extrair texto selecionável deste PDF. O arquivo pode estar escaneado como imagem e precisar de OCR.';
            }
        } elseif (str_starts_with($mime, 'image/')) {
            $textoExtraido = $this->extractWithOcrOrFallback($destination, $mime, $userId);
        }
        if ($extension === 'pdf' && $this->isExtractionFailure((string) $textoExtraido)) {
            $textoExtraido = $this->extractWithOcrOrFallback($destination, $mime, $userId);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO documents (user_id, nome_arquivo, tipo_arquivo, caminho, texto_extraido) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $file['name'], $extension, $relativePath, $textoExtraido]);
        $documentId = (int) $this->pdo->lastInsertId();
        $this->usage->record($userId, 'document_upload', 1, $documentId, ['tipo_arquivo' => $extension]);

        $aiAuthorized = (string) $this->request->post('autorizar_ia', '') === '1';
        $queued = false;
        $analysis = null;
        if ($aiAuthorized) {
            if ($this->asyncJobsEnabled()) {
                $this->enqueueDocumentAnalysis($documentId, $userId);
                $queued = true;
            } else {
                $analysis = $this->generateAnalysisForUser($documentId, $destination, $mime, $textoExtraido, $userId);
            }
        }
        if ($analysis) {
            $this->saveAnalysis($documentId, $analysis);
        }

        $message = $analysis
            ? 'Documento enviado e analisado com IA.'
            : 'Documento enviado com sucesso. A an?lise por IA pode ser gerada ao abrir o documento.';

        if ($queued) {
            $message = 'Documento enviado. A análise por IA entrou na fila de processamento.';
        }

        $this->notifications->notify($userId, $message . ' Arquivo: ' . $file['name']);
        $this->notifications->notifyMany($this->notifications->activeAdmins(), 'Novo documento enviado por ' . (string) $_SESSION['nome'] . ': ' . $file['name']);
        $this->audit->log('document.upload', 'document', $documentId, [
            'nome_arquivo' => $file['name'],
            'tipo_arquivo' => $extension,
            'ai_authorized' => $aiAuthorized,
            'analysis_generated' => (bool) $analysis,
            'analysis_queued' => $queued,
            'private_storage' => $this->storage->isDocumentStorageOutsideWebroot(),
        ]);

        $this->response->redirect(app_url('/frontend/dashboard-cliente.php?sucesso=' . urlencode($message)));
    }

    public function analyze(): void
    {
        $this->startSession();

        // Validação CSRF adicional (defensiva)
        CsrfMiddleware::validate();

        if (empty($_SESSION['logado'])) {
            $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('Faça login para analisar documentos.')));
        }

        $documentId = (int) ($_POST['document_id'] ?? 0);
        if ($documentId <= 0) {
            $this->response->redirect(app_url('/frontend/visualizar-documento.php?erro=' . urlencode('Documento inválido.')));
        }

        if ((string) $this->request->post('autorizar_ia', '') !== '1') {
            $this->response->redirect(app_url('/frontend/visualizar-documento.php?id=' . $documentId . '&erro=' . urlencode('Autorize a an?lise por IA antes de enviar o documento para processamento.')));
        }

        $document = $this->findDocumentForCurrentUser($documentId);
        if (!$document) {
            $this->response->redirect(app_url('/frontend/visualizar-documento.php?erro=' . urlencode('Documento não encontrado ou indisponível para seu perfil.')));
        }

        $absolutePath = $this->documentPath($document);
        $mime = is_file($absolutePath) ? (mime_content_type($absolutePath) ?: '') : '';
        $textoExtraido = (string) ($document['texto_extraido'] ?? '');

        if ($this->isExtractionFailure($textoExtraido)) {
            $textoExtraido = '';
        }

        $redirect = app_url('/frontend/visualizar-documento.php?id=' . $documentId);

        if ($this->asyncJobsEnabled()) {
            $this->enqueueDocumentAnalysis($documentId, (int) $document['user_id']);
            $this->response->redirect($redirect . '&sucesso=' . urlencode('Análise por IA enfileirada. Atualize a página após o processamento.'));
        }

        if ($absolutePath === null) {
            $this->response->redirect($redirect . '&erro=' . urlencode('Arquivo original não encontrado para análise.'));
        }

        $analysis = $this->generateAnalysisForUser($documentId, $absolutePath, $mime, $textoExtraido, (int) $document['user_id']);

        if (!$analysis) {
            $this->response->redirect($redirect . '&erro=' . urlencode('Não foi possível gerar a análise por IA agora. Confira a chave/modelo da Gemini ou tente novamente.'));
        }

        $this->saveAnalysis($documentId, $analysis);
        $this->notifications->notify((int) $document['user_id'], 'An?lise por IA atualizada para o documento: ' . (string) $document['nome_arquivo']);
        $this->audit->log('document.analyze', 'document', $documentId, ['analysis_generated' => true]);
        $this->response->redirect($redirect . '&sucesso=' . urlencode('An?lise por IA gerada.'));
    }

    public function download(): void
    {
        $this->startSession();

        if (empty($_SESSION['logado'])) {
            http_response_code(401);
            echo 'Faça login para acessar este documento.';
            return;
        }

        $documentId = (int) $this->request->get('id', 0);
        if ($documentId <= 0) {
            http_response_code(400);
            echo 'Documento inválido.';
            return;
        }

        $document = $this->findDocumentForCurrentUser($documentId);
        if (!$document) {
            http_response_code(404);
            echo 'Documento não encontrado ou indisponível para seu perfil.';
            return;
        }

        $absolutePath = $this->documentPath($document);
        if ($absolutePath === null || !is_file($absolutePath) || !is_readable($absolutePath)) {
            http_response_code(404);
            echo 'Arquivo não encontrado.';
            return;
        }

        $mime = mime_content_type($absolutePath) ?: 'application/octet-stream';
        $filename = $this->safeDownloadName((string) ($document['nome_arquivo'] ?? ('documento-' . $documentId)));
        $disposition = $this->request->get('download', '') === '1' ? 'attachment' : 'inline';

        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($absolutePath));
        header('Content-Disposition: ' . $disposition . '; filename="' . addcslashes($filename, "\\\"") . '"');

        readfile($absolutePath);
    }

    public function delete(): void
    {
        $this->startSession();

        if (empty($_SESSION['logado'])) {
            $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('Faça login para continuar.')));
        }

        $documentId = (int) $this->request->post('document_id', 0);
        if ($documentId <= 0) {
            $this->response->redirect(app_url('/frontend/visualizar-documento.php?erro=' . urlencode('Documento inválido.')));
        }

        $document = $this->findDocumentForCurrentUser($documentId);
        if (!$document || !$this->canDeleteDocument($document)) {
            $this->response->redirect(app_url('/frontend/visualizar-documento.php?erro=' . urlencode('Você não tem permissão para excluir este documento.')));
        }

        $absolutePath = $this->documentPath($document);

        $stmt = $this->pdo->prepare('DELETE FROM documents WHERE id = ?');
        $stmt->execute([$documentId]);

        if ($absolutePath && is_file($absolutePath)) {
            @unlink($absolutePath);
        }

        $this->audit->log('document.delete', 'document', $documentId, [
            'nome_arquivo' => $document['nome_arquivo'] ?? null,
            'owner_id' => (int) ($document['user_id'] ?? 0),
        ]);

        $this->response->redirect(app_url('/frontend/visualizar-documento.php?sucesso=' . urlencode('Documento exclu?do.')));
    }

    private function generateAnalysis(string $filePath, string $mime, ?string $textoExtraido): ?array
    {
        $gemini = new GeminiService();
        $textoExtraido = trim((string) $textoExtraido);

        if ($this->isExtractionFailure($textoExtraido)) {
            $textoExtraido = '';
        }

        if (is_file($filePath) && GeminiService::isSupportedFileMime($mime)) {
            return $this->withAnalysisMetadata($gemini, $gemini->analyzeDocumentFile($filePath, $mime, $textoExtraido));
        }

        if ($textoExtraido !== '') {
            return $this->withAnalysisMetadata($gemini, $gemini->analyzeDocument($textoExtraido));
        }

        return null;
    }

    public function processQueuedAnalysis(int $documentId): bool
    {
        $document = $this->findDocumentById($documentId);
        if (!$document) {
            return false;
        }

        $path = $this->documentPath($document);
        if ($path === null || !is_file($path)) {
            return false;
        }

        $mime = mime_content_type($path) ?: '';
        $analysis = $this->generateAnalysisForUser($documentId, $path, $mime, (string) ($document['texto_extraido'] ?? ''), (int) $document['user_id']);
        if (!$analysis) {
            return false;
        }

        $this->saveAnalysis($documentId, $analysis);
        $this->notifications->notify((int) $document['user_id'], 'Análise por IA concluída para o documento: ' . (string) $document['nome_arquivo']);
        $this->audit->log('document.analyze_queued_completed', 'document', $documentId);
        return true;
    }

    private function generateAnalysisForUser(int $documentId, string $filePath, string $mime, ?string $textoExtraido, int $userId): ?array
    {
        $quota = $this->usage->allow($userId, 'document_ai');
        if (!$quota['allowed']) {
            $this->audit->log('usage.limit_blocked', 'document', $documentId, ['feature' => 'document_ai']);
            return null;
        }

        $analysis = $this->generateAnalysis($filePath, $mime, $textoExtraido);
        if ($analysis) {
            $this->usage->record($userId, 'document_ai', 1, $documentId, ['mime' => $mime]);
        }

        return $analysis;
    }

    private function saveAnalysis(int $documentId, array $analysis): void
    {
        $this->pdo->prepare('DELETE FROM ai_results WHERE document_id = ?')->execute([$documentId]);

        if ($this->aiResultsHasMetadataColumns()) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO ai_results (document_id, resumo, explicacao, confianca, modelo, prompt_versao) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $documentId,
                $analysis['resumo'],
                $analysis['explicacao'],
                $analysis['confianca'],
                $analysis['modelo'] ?? null,
                $analysis['prompt_versao'] ?? null,
            ]);
            return;
        }

        $stmt = $this->pdo->prepare('INSERT INTO ai_results (document_id, resumo, explicacao, confianca) VALUES (?, ?, ?, ?)');
        $stmt->execute([$documentId, $analysis['resumo'], $analysis['explicacao'], $analysis['confianca']]);
    }

    private function withAnalysisMetadata(GeminiService $gemini, ?array $analysis): ?array
    {
        if (!$analysis) {
            $this->audit->log('document.ai_error', 'document', null, [
                'model' => $gemini->modelName(),
                'error' => $gemini->getLastError(),
            ]);
            return null;
        }

        $analysis['modelo'] = $gemini->modelName();
        $analysis['prompt_versao'] = GeminiService::promptVersion();
        return $analysis;
    }

    private function aiResultsHasMetadataColumns(): bool
    {
        static $hasColumns = null;
        if ($hasColumns !== null) {
            return $hasColumns;
        }

        $hasColumns = database_table_has_column($this->pdo, 'ai_results', 'modelo')
            && database_table_has_column($this->pdo, 'ai_results', 'prompt_versao');
        return $hasColumns;
    }

    private function isExtractionFailure(string $text): bool
    {
        $text = mb_strtolower($text);
        return str_contains($text, 'foi poss')
            && str_contains($text, 'extrair texto')
            && str_contains($text, 'pdf');
    }

    private function findDocumentForCurrentUser(int $documentId): ?array
    {
        $userId = (int) ($_SESSION['id'] ?? 0);
        $type = (string) ($_SESSION['tipo'] ?? '');

        if ($type === 'cliente') {
            $sql = 'SELECT d.* FROM documents d WHERE d.id = ? AND d.user_id = ?';
            $params = [$documentId, $userId];
        } elseif ($type === 'advogado') {
            $sql = "SELECT d.* FROM documents d
                    WHERE d.id = ?
                    AND EXISTS (
                        SELECT 1 FROM cases c
                        WHERE c.cliente_id = d.user_id
                        AND c.advogado_id = ?
                    )";
            $params = [$documentId, $userId];
        } elseif ($type === 'admin') {
            $sql = 'SELECT d.* FROM documents d WHERE d.id = ?';
            $params = [$documentId];
        } else {
            return null;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $document = $stmt->fetch();

        return $document ?: null;
    }

    private function findDocumentById(int $documentId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM documents WHERE id = ?');
        $stmt->execute([$documentId]);
        $document = $stmt->fetch();

        return $document ?: null;
    }

    private function documentPath(array $document): ?string
    {
        return $this->storage->documentPathFromReference((string) ($document['caminho'] ?? ''));
    }

    private function canDeleteDocument(array $document): bool
    {
        $type = (string) ($_SESSION['tipo'] ?? '');
        $userId = (int) ($_SESSION['id'] ?? 0);

        return $type === 'admin' || ($type === 'cliente' && (int) ($document['user_id'] ?? 0) === $userId);
    }

    private function safeDownloadName(string $filename): string
    {
        $filename = trim(preg_replace('/[^\w.\- ]+/u', '_', $filename) ?? '');
        return $filename !== '' ? $filename : 'documento';
    }

    private function extractWithOcrOrFallback(string $path, string $mime, int $userId): string
    {
        $ocr = new OcrService();
        $quota = $this->usage->allow($userId, 'ocr');
        if ($quota['allowed']) {
            $text = $ocr->extract($path, $mime);
            if ($text !== '') {
                $this->usage->record($userId, 'ocr', 1, null, ['mime' => $mime]);
                return $text;
            }
        }

        return $ocr->fallbackMessage($mime);
    }

    private function enqueueDocumentAnalysis(int $documentId, int $userId): void
    {
        $queue = new JobQueueService($this->pdo);
        if ($queue->pendingCountForEntity('document_analysis', 'document_id', $documentId) > 0) {
            return;
        }

        $jobId = $queue->enqueue('document_analysis', ['document_id' => $documentId], $userId);
        $this->audit->log('job.enqueued', 'document', $documentId, ['job_id' => $jobId, 'type' => 'document_analysis']);
    }

    private function asyncJobsEnabled(): bool
    {
        $value = getenv('ASYNC_JOBS_ENABLED');
        if ($value === false) {
            $env = function_exists('database_env_values') ? database_env_values(dirname(__DIR__, 2) . '/.env') : [];
            $value = $env['ASYNC_JOBS_ENABLED'] ?? 'false';
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
