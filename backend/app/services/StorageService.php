<?php

class StorageService
{
    private string $projectRoot;
    private string $documentRoot;
    private string $attachmentRoot;
    private string $legacyDocumentRoot;
    private string $legacyAttachmentRoot;

    public function __construct()
    {
        $this->projectRoot = dirname(__DIR__, 3);
        $this->legacyDocumentRoot = $this->projectRoot . '/backend/storage/documents';
        $this->legacyAttachmentRoot = $this->projectRoot . '/backend/storage/message-attachments';
        $this->documentRoot = $this->configuredPath('DOCUMENT_STORAGE_PATH', $this->projectRoot . '/storage-private/documents');
        $this->attachmentRoot = $this->configuredPath('ATTACHMENT_STORAGE_PATH', $this->projectRoot . '/storage-private/message-attachments');
    }

    public function documentDirectory(int $userId): string
    {
        return $this->documentRoot . DIRECTORY_SEPARATOR . $userId;
    }

    public function documentReference(int $userId, string $filename): string
    {
        if ($this->isSameStorageRoot($this->documentRoot, $this->legacyDocumentRoot)) {
            return 'backend/storage/documents/' . $userId . '/' . $filename;
        }

        return 'private://documents/' . $userId . '/' . $filename;
    }

    public function documentPathFromReference(string $reference): ?string
    {
        $reference = ltrim(str_replace('\\', '/', $reference), '/');

        if (str_starts_with($reference, 'private://documents/')) {
            $relative = substr($reference, strlen('private://documents/'));
            return $this->safeJoin($this->documentRoot, $relative);
        }

        return $this->safeLegacyProjectPath($reference, $this->legacyDocumentRoot);
    }

    public function attachmentDirectory(int $caseId): string
    {
        return $this->attachmentRoot . DIRECTORY_SEPARATOR . $caseId;
    }

    public function attachmentReference(int $caseId, string $filename): string
    {
        if ($this->isSameStorageRoot($this->attachmentRoot, $this->legacyAttachmentRoot)) {
            return 'backend/storage/message-attachments/' . $caseId . '/' . $filename;
        }

        return 'private://message-attachments/' . $caseId . '/' . $filename;
    }

    public function attachmentPathFromReference(string $reference): ?string
    {
        $reference = ltrim(str_replace('\\', '/', $reference), '/');

        if (str_starts_with($reference, 'private://message-attachments/')) {
            $relative = substr($reference, strlen('private://message-attachments/'));
            return $this->safeJoin($this->attachmentRoot, $relative);
        }

        return $this->safeLegacyProjectPath($reference, $this->legacyAttachmentRoot);
    }

    public function isDocumentStorageOutsideWebroot(): bool
    {
        return !$this->isSameStorageRoot($this->documentRoot, $this->legacyDocumentRoot);
    }

    private function configuredPath(string $key, string $default): string
    {
        $value = getenv($key);
        if ($value === false || trim((string) $value) === '') {
            $env = function_exists('database_env_values') ? database_env_values(dirname(__DIR__, 2) . '/.env') : [];
            $value = $env[$key] ?? $default;
        }

        $path = trim((string) $value);
        if ($path === '') {
            $path = $default;
        }

        if (!preg_match('#^[A-Za-z]:[\\\\/]#', $path) && !str_starts_with($path, '/')) {
            $path = $this->projectRoot . '/' . $path;
        }

        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }

    private function safeJoin(string $root, string $relative): ?string
    {
        $relative = trim(str_replace('\\', '/', $relative), '/');
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $rootReal = realpath($root) ?: $root;
        $pathReal = realpath($path);

        if ($pathReal !== false) {
            return str_starts_with($pathReal, rtrim($rootReal, "\\/") . DIRECTORY_SEPARATOR) ? $pathReal : null;
        }

        return str_starts_with(dirname($path), rtrim($root, "\\/") . DIRECTORY_SEPARATOR) ? $path : null;
    }

    private function safeLegacyProjectPath(string $reference, string $storageRoot): ?string
    {
        $absolutePath = realpath($this->projectRoot . '/' . $reference);
        $storageRoot = realpath($storageRoot);

        if (!$absolutePath || !$storageRoot) {
            return null;
        }

        return str_starts_with($absolutePath, rtrim($storageRoot, "\\/") . DIRECTORY_SEPARATOR) ? $absolutePath : null;
    }

    private function isSameStorageRoot(string $left, string $right): bool
    {
        return $this->normalizePath($left) === $this->normalizePath($right);
    }

    private function normalizePath(string $path): string
    {
        $real = realpath($path) ?: $path;
        return rtrim(strtolower(str_replace('\\', '/', $real)), '/');
    }
}
