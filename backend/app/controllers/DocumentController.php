<?php

require_once dirname(__DIR__) . '/core/BaseController.php';
require_once dirname(__DIR__) . '/services/AuditService.php';
require_once dirname(__DIR__) . '/services/GeminiService.php';
require_once dirname(__DIR__) . '/services/NotificationService.php';
require_once dirname(__DIR__) . '/services/PdfTextExtractor.php';
require_once dirname(__DIR__) . '/middlewares/CsrfMiddleware.php';

class DocumentController extends BaseController
{
    private NotificationService $notifications;
    private AuditService $audit;

    public function __construct()
    {
        parent::__construct();
        $this->notifications = new NotificationService($this->pdo);
        $this->audit = new AuditService($this->pdo);
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
        $maxSize = 50 * 1024 * 1024;
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'png', 'jpg', 'jpeg', 'webp'];
        $allowedMimes = ['application/pdf', 'image/png', 'image/jpeg', 'image/webp'];

        if ($file['size'] <= 0 || $file['size'] > $maxSize) {
            $this->response->redirect(app_url('/frontend/dashboard-cliente.php?erro=' . urlencode('O arquivo deve ter no máximo 50 MB.')));
        }

        $mime = mime_content_type($file['tmp_name']) ?: '';
        if (!in_array($extension, $allowedExtensions, true) || !in_array($mime, $allowedMimes, true)) {
            $this->response->redirect(app_url('/frontend/dashboard-cliente.php?erro=' . urlencode('Formato não permitido.')));
        }

        $userId = (int) $_SESSION['id'];
        $storageDir = dirname(__DIR__, 2) . '/storage/documents/' . $userId;

        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0775, true);
        }

        $safeName = bin2hex(random_bytes(12)) . '.' . $extension;
        $destination = $storageDir . '/' . $safeName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $this->response->redirect(app_url('/frontend/dashboard-cliente.php?erro=' . urlencode('Não foi possível salvar o arquivo.')));
        }

        $relativePath = 'backend/storage/documents/' . $userId . '/' . $safeName;

        $textoExtraido = null;
        if ($extension === 'pdf') {
            $textoExtraido = PdfTextExtractor::extract($destination);
            if ($textoExtraido === '') {
                $textoExtraido = 'Não foi possível extrair texto selecionável deste PDF. O arquivo pode estar escaneado como imagem e precisar de OCR.';
            }
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO documents (user_id, nome_arquivo, tipo_arquivo, caminho, texto_extraido) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $file['name'], $extension, $relativePath, $textoExtraido]);
        $documentId = (int) $this->pdo->lastInsertId();

        $analysis = $this->generateAnalysis($destination, $mime, $textoExtraido);
        if ($analysis) {
            $this->saveAnalysis($documentId, $analysis);
        }

        $message = $analysis
            ? 'Documento enviado e analisado com IA.'
            : 'Documento enviado com sucesso. A análise por IA pode ser gerada ao abrir o documento.';

        $this->notifications->notify($userId, $message . ' Arquivo: ' . $file['name']);
        $this->notifications->notifyMany($this->notifications->activeAdmins(), 'Novo documento enviado por ' . (string) $_SESSION['nome'] . ': ' . $file['name']);
        $this->audit->log('document.upload', 'document', $documentId, [
            'nome_arquivo' => $file['name'],
            'tipo_arquivo' => $extension,
            'analysis_generated' => (bool) $analysis,
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

        $document = $this->findDocumentForCurrentUser($documentId);
        if (!$document) {
            $this->response->redirect(app_url('/frontend/visualizar-documento.php?erro=' . urlencode('Documento não encontrado ou indisponível para seu perfil.')));
        }

        $absolutePath = dirname(__DIR__, 3) . '/' . ltrim(str_replace('\\', '/', (string) $document['caminho']), '/');
        $mime = is_file($absolutePath) ? (mime_content_type($absolutePath) ?: '') : '';
        $textoExtraido = (string) ($document['texto_extraido'] ?? '');

        if ($this->isExtractionFailure($textoExtraido)) {
            $textoExtraido = '';
        }

        $analysis = $this->generateAnalysis($absolutePath, $mime, $textoExtraido);
        $redirect = app_url('/frontend/visualizar-documento.php?id=' . $documentId);

        if (!$analysis) {
            $this->response->redirect($redirect . '&erro=' . urlencode('Não foi possível gerar a análise por IA agora. Confira a chave/modelo da Gemini ou tente novamente.'));
        }

        $this->saveAnalysis($documentId, $analysis);
        $this->notifications->notify((int) $document['user_id'], 'Análise por IA atualizada para o documento: ' . (string) $document['nome_arquivo']);
        $this->audit->log('document.analyze', 'document', $documentId, ['analysis_generated' => true]);
        $this->response->redirect($redirect . '&sucesso=' . urlencode('Análise por IA gerada.'));
    }

    private function generateAnalysis(string $filePath, string $mime, ?string $textoExtraido): ?array
    {
        $gemini = new GeminiService();
        $textoExtraido = trim((string) $textoExtraido);

        if ($this->isExtractionFailure($textoExtraido)) {
            $textoExtraido = '';
        }

        if (is_file($filePath) && GeminiService::isSupportedFileMime($mime)) {
            return $gemini->analyzeDocumentFile($filePath, $mime, $textoExtraido);
        }

        if ($textoExtraido !== '') {
            return $gemini->analyzeDocument($textoExtraido);
        }

        return null;
    }

    private function saveAnalysis(int $documentId, array $analysis): void
    {
        $this->pdo->prepare('DELETE FROM ai_results WHERE document_id = ?')->execute([$documentId]);

        $stmt = $this->pdo->prepare(
            'INSERT INTO ai_results (document_id, resumo, explicacao, confianca) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $documentId,
            $analysis['resumo'],
            $analysis['explicacao'],
            $analysis['confianca'],
        ]);
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
                        AND (c.advogado_id = ? OR c.advogado_id IS NULL)
                    )";
            $params = [$documentId, $userId];
        } else {
            $sql = 'SELECT d.* FROM documents d WHERE d.id = ?';
            $params = [$documentId];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $document = $stmt->fetch();

        return $document ?: null;
    }
}
