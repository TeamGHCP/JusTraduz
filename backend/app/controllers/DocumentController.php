<?php

require_once dirname(__DIR__) . '/core/Request.php';
require_once dirname(__DIR__) . '/core/Response.php';

class DocumentController
{
    private Response $response;
    private PDO $pdo;

    public function __construct()
    {
        require_once dirname(__DIR__) . '/config/database.php';
        $this->response = new Response();
        $this->pdo = $pdo;
    }

    public function upload(): void
    {
        session_start();

        if (empty($_SESSION['logado']) || $_SESSION['tipo'] !== 'cliente') {
            $this->response->redirect('/justraduz/frontend/login.html?erro=' . urlencode('Faça login como cliente para enviar documentos.'));
        }

        if (empty($_FILES['documento']) || $_FILES['documento']['error'] !== UPLOAD_ERR_OK) {
            $this->response->redirect('/justraduz/frontend/dashboard-cliente.php?erro=' . urlencode('Arquivo inválido ou não enviado.'));
        }

        $file = $_FILES['documento'];
        $maxSize = 50 * 1024 * 1024;
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'png', 'jpg', 'jpeg', 'webp'];
        $allowedMimes = ['application/pdf', 'image/png', 'image/jpeg', 'image/webp'];

        if ($file['size'] <= 0 || $file['size'] > $maxSize) {
            $this->response->redirect('/justraduz/frontend/dashboard-cliente.php?erro=' . urlencode('O arquivo deve ter no máximo 50 MB.'));
        }

        $mime = mime_content_type($file['tmp_name']) ?: '';
        if (!in_array($extension, $allowedExtensions, true) || !in_array($mime, $allowedMimes, true)) {
            $this->response->redirect('/justraduz/frontend/dashboard-cliente.php?erro=' . urlencode('Formato não permitido.'));
        }

        $userId = (int) $_SESSION['id'];
        $storageDir = dirname(__DIR__, 2) . '/storage/documents/' . $userId;

        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0775, true);
        }

        $safeName = bin2hex(random_bytes(12)) . '.' . $extension;
        $destination = $storageDir . '/' . $safeName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $this->response->redirect('/justraduz/frontend/dashboard-cliente.php?erro=' . urlencode('Não foi possível salvar o arquivo.'));
        }

        $relativePath = 'backend/storage/documents/' . $userId . '/' . $safeName;

        $stmt = $this->pdo->prepare(
            'INSERT INTO documents (user_id, nome_arquivo, tipo_arquivo, caminho) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $file['name'], $extension, $relativePath]);

        $this->response->redirect('/justraduz/frontend/dashboard-cliente.php?sucesso=' . urlencode('Documento enviado com sucesso.'));
    }
}
