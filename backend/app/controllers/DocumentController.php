<?php

require_once dirname(__DIR__) . '/core/BaseController.php';
require_once dirname(__DIR__) . '/services/AuditService.php';
require_once dirname(__DIR__) . '/services/GeminiService.php';
require_once dirname(__DIR__) . '/services/JobQueueService.php';
require_once dirname(__DIR__) . '/services/NotificationService.php';
require_once dirname(__DIR__) . '/services/OcrService.php';
require_once dirname(__DIR__) . '/services/OrganizationService.php';
require_once dirname(__DIR__) . '/services/PermissionService.php';
require_once dirname(__DIR__) . '/services/PdfTextExtractor.php';
require_once dirname(__DIR__) . '/services/StorageService.php';
require_once dirname(__DIR__) . '/services/SubscriptionService.php';
require_once dirname(__DIR__) . '/services/UploadScannerService.php';
require_once dirname(__DIR__) . '/services/UsageLimiter.php';
require_once dirname(__DIR__) . '/middlewares/CsrfMiddleware.php';

class DocumentController extends BaseController
{
    private NotificationService $notifications;
    private AuditService $audit;
    private OrganizationService $organizations;
    private StorageService $storage;
    private SubscriptionService $subscriptions;
    private UsageLimiter $usage;

    public function __construct()
    {
        parent::__construct();
        $this->notifications = new NotificationService($this->pdo);
        $this->audit = new AuditService($this->pdo);
        $this->organizations = new OrganizationService($this->pdo);
        $this->storage = new StorageService();
        $this->subscriptions = new SubscriptionService($this->pdo);
        $this->usage = new UsageLimiter($this->pdo);
    }

    public function upload(): void
    {
        $this->startSession();

        // ValidaÃ§Ã£o CSRF adicional (defensiva)
        CsrfMiddleware::validate();

        $uploadRedirect = (string) $this->request->post('redirect_to', '') === 'documents'
            ? '/frontend/visualizar-documento.php'
            : '/frontend/dashboard-cliente.php';

        if (empty($_SESSION['logado']) || $_SESSION['tipo'] !== 'cliente') {
            $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('FaÃ§a login como cliente para enviar documentos.')));
        }

        if (empty($_FILES['documento']) || $_FILES['documento']['error'] !== UPLOAD_ERR_OK) {
            $this->response->redirect(app_url($uploadRedirect . '?erro=' . urlencode('Arquivo invÃ¡lido ou nÃ£o enviado.')));
        }

        $file = $_FILES['documento'];
        $userId = (int) $_SESSION['id'];
        if ($this->subscriptions->isBlocked($userId)) {
            $this->response->redirect(app_url('/frontend/subir-plano.php?erro=' . urlencode('Regularize seu plano para enviar documentos.')));
        }

        $quota = $this->usage->allow($userId, 'document_upload');
        if (!$quota['allowed']) {
            $this->audit->log('usage.limit_blocked', 'document', null, [
                'feature' => 'document_upload',
                'limit' => (int) ($quota['limit'] ?? 0),
                'used' => (int) ($quota['used'] ?? 0),
            ]);
            $this->response->redirect(app_url($uploadRedirect . '?erro=' . urlencode($this->usage->limitMessage('document_upload', $quota))));
        }

        $maxSize = 50 * 1024 * 1024;
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'docx'];
        if ($file['size'] <= 0 || $file['size'] > $maxSize) {
            $this->response->redirect(app_url($uploadRedirect . '?erro=' . urlencode('O arquivo deve ter no mÃ¡ximo 50 MB.')));
        }

        $mime = mime_content_type($file['tmp_name']) ?: '';
        if (!in_array($extension, $allowedExtensions, true) || !$this->isAllowedUploadMime($extension, $mime)) {
            $this->response->redirect(app_url($uploadRedirect . '?erro=' . urlencode('Formato nÃ£o permitido.')));
        }

        if ($extension === 'docx' && !$this->hasValidDocxStructure((string) $file['tmp_name'])) {
            $this->audit->log('document.upload_blocked', 'document', null, [
                'nome_arquivo' => $file['name'],
                'mime' => $mime,
                'reason' => 'DOCX sem estrutura interna obrigatoria.',
            ]);
            $this->response->redirect(app_url($uploadRedirect . '?erro=' . urlencode('Arquivo DOCX invalido ou corrompido.')));
        }

        $scanner = new UploadScannerService();
        if (!$scanner->scan((string) $file['tmp_name'], (string) $file['name'], $mime)) {
            $this->audit->log('document.upload_blocked', 'document', null, [
                'nome_arquivo' => $file['name'],
                'mime' => $mime,
                'reason' => $scanner->lastError(),
            ]);
            $this->response->redirect(app_url($uploadRedirect . '?erro=' . urlencode($scanner->lastError() ?: 'Arquivo reprovado pelo scanner de seguranÃ§a.')));
        }

        $storageDir = $this->storage->documentDirectory($userId);

        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0775, true);
        }

        $safeName = bin2hex(random_bytes(12)) . '.' . $extension;
        $destination = $storageDir . '/' . $safeName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $this->response->redirect(app_url($uploadRedirect . '?erro=' . urlencode('NÃ£o foi possÃ­vel salvar o arquivo.')));
        }

        $relativePath = $this->storage->documentReference($userId, $safeName);

        $textoExtraido = null;
        if ($extension === 'pdf') {
            $textoExtraido = PdfTextExtractor::extract($destination);
            if ($textoExtraido === '') {
                $textoExtraido = 'NÃ£o foi possÃ­vel extrair texto selecionÃ¡vel deste PDF. O arquivo pode estar escaneado como imagem e precisar de OCR.';
            }
        } elseif ($extension === 'docx') {
            $textoExtraido = $this->extractDocxText($destination);
            if ($textoExtraido === '') {
                $textoExtraido = 'NÃ£o foi possÃ­vel extrair texto deste DOCX. O arquivo pode estar vazio, protegido ou corrompido.';
            }
        } elseif (str_starts_with($mime, 'image/')) {
            $textoExtraido = $this->extractWithOcrOrFallback($destination, $mime, $userId);
        }
        if ($extension === 'pdf' && $this->isExtractionFailure((string) $textoExtraido)) {
            $textoExtraido = $this->extractWithOcrOrFallback($destination, $mime, $userId);
        }

        $organizationId = $this->organizations->currentOrganizationId($userId);
        if (database_table_has_column($this->pdo, 'documents', 'organization_id')) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO documents (user_id, organization_id, nome_arquivo, tipo_arquivo, caminho, texto_extraido) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$userId, $organizationId, $file['name'], $extension, $relativePath, $textoExtraido]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO documents (user_id, nome_arquivo, tipo_arquivo, caminho, texto_extraido) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$userId, $file['name'], $extension, $relativePath, $textoExtraido]);
        }
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
            : 'Documento enviado com sucesso. A anÃ¡lise por IA pode ser gerada ao abrir o documento.';

        if ($queued) {
            $message = 'Documento enviado. A anÃ¡lise por IA entrou na fila de processamento.';
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

        $this->response->redirect(app_url($uploadRedirect . '?sucesso=' . urlencode($message)));
    }

    public function analyze(): void
    {
        $this->startSession();

        // ValidaÃ§Ã£o CSRF adicional (defensiva)
        CsrfMiddleware::validate();

        if (empty($_SESSION['logado'])) {
            $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('FaÃ§a login para analisar documentos.')));
        }

        $documentId = (int) ($_POST['document_id'] ?? 0);
        if ($documentId <= 0) {
            $this->response->redirect(app_url('/frontend/visualizar-documento.php?erro=' . urlencode('Documento invÃ¡lido.')));
        }

        if ((string) $this->request->post('autorizar_ia', '') !== '1') {
            $this->response->redirect(app_url('/frontend/visualizar-documento.php?id=' . $documentId . '&erro=' . urlencode('Autorize a anÃ¡lise por IA antes de enviar o documento para processamento.')));
        }

        $document = $this->findDocumentForCurrentUser($documentId);
        if (!$document) {
            $this->response->redirect(app_url('/frontend/visualizar-documento.php?erro=' . urlencode('Documento nÃ£o encontrado ou indisponÃ­vel para seu perfil.')));
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
            $this->response->redirect($redirect . '&sucesso=' . urlencode('AnÃ¡lise por IA enfileirada. Atualize a pÃ¡gina apÃ³s o processamento.'));
        }

        if ($absolutePath === null) {
            $this->response->redirect($redirect . '&erro=' . urlencode('Arquivo original nÃ£o encontrado para anÃ¡lise.'));
        }

        $analysis = $this->generateAnalysisForUser($documentId, $absolutePath, $mime, $textoExtraido, (int) $document['user_id']);

        if (!$analysis) {
            $this->response->redirect($redirect . '&erro=' . urlencode('NÃ£o foi possÃ­vel gerar a anÃ¡lise por IA agora. Confira a chave/modelo da Gemini ou tente novamente.'));
        }

        $this->saveAnalysis($documentId, $analysis);
        $this->notifications->notify((int) $document['user_id'], 'Análise por IA atualizada para o documento: ' . (string) $document['nome_arquivo']);
        $this->audit->log('document.analyze', 'document', $documentId, ['analysis_generated' => true]);
        $this->response->redirect($redirect . '&sucesso=' . urlencode('Análise por IA gerada.'));
    }

    public function download(): void
    {
        $this->startSession();

        if (empty($_SESSION['logado'])) {
            http_response_code(401);
            echo 'FaÃ§a login para acessar este documento.';
            return;
        }

        $documentId = (int) $this->request->get('id', 0);
        if ($documentId <= 0) {
            http_response_code(400);
            echo 'Documento invÃ¡lido.';
            return;
        }

        $document = $this->findDocumentForCurrentUser($documentId);
        if (!$document) {
            http_response_code(404);
            echo 'Documento nÃ£o encontrado ou indisponÃ­vel para seu perfil.';
            return;
        }

        $absolutePath = $this->documentPath($document);
        if ($absolutePath === null || !is_file($absolutePath) || !is_readable($absolutePath)) {
            http_response_code(404);
            echo 'Arquivo nÃ£o encontrado.';
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
            $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('FaÃ§a login para continuar.')));
        }

        $documentId = (int) $this->request->post('document_id', 0);
        if ($documentId <= 0) {
            $this->response->redirect(app_url('/frontend/visualizar-documento.php?erro=' . urlencode('Documento invÃ¡lido.')));
        }

        $document = $this->findDocumentForCurrentUser($documentId);
        if (!$document || !$this->canDeleteDocument($document)) {
            $this->response->redirect(app_url('/frontend/visualizar-documento.php?erro=' . urlencode('VocÃª nÃ£o tem permissÃ£o para excluir este documento.')));
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

        $this->response->redirect(app_url('/frontend/visualizar-documento.php?sucesso=' . urlencode('Documento excluÃ­do.')));
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
        $this->notifications->notify((int) $document['user_id'], 'AnÃ¡lise por IA concluÃ­da para o documento: ' . (string) $document['nome_arquivo']);
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

        if (PermissionService::roleHas($type, 'documents.view_own')) {
            $sql = 'SELECT d.* FROM documents d WHERE d.id = ? AND d.user_id = ?';
            $params = [$documentId, $userId];
        } elseif (PermissionService::roleHas($type, 'documents.view_assigned')) {
            $sql = "SELECT d.* FROM documents d
                    WHERE d.id = ?
                    AND EXISTS (
                        SELECT 1 FROM cases c
                        WHERE c.cliente_id = d.user_id
                        AND c.advogado_id = ?
                    )";
            $params = [$documentId, $userId];
        } elseif (PermissionService::roleHas($type, 'documents.view_all')) {
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

        return PermissionService::canDeleteDocument($type, $userId, $document);
    }

    private function safeDownloadName(string $filename): string
    {
        $filename = trim(preg_replace('/[^\w.\- ]+/u', '_', $filename) ?? '');
        return $filename !== '' ? $filename : 'documento';
    }

    private function isAllowedUploadMime(string $extension, string $mime): bool
    {
        $allowedByExtension = [
            'pdf' => ['application/pdf'],
            'png' => ['image/png'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'webp' => ['image/webp'],
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

    private function extractDocxText(string $path): string
    {
        if (!class_exists(ZipArchive::class) || !is_readable($path)) {
            return '';
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (!is_string($xml) || $xml === '') {
            return '';
        }

        $xml = preg_replace('/<\/w:p>/', "\n", $xml) ?? $xml;
        $xml = preg_replace('/<\/w:tr>/', "\n", $xml) ?? $xml;
        $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\R{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
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
