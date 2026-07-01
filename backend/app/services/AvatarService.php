<?php

namespace App\Services;

use App\Exceptions\ValidationException;
use RuntimeException;

class AvatarService
{
    public function handleProfilePhotoUpload(int $userId, ?array $file): ?string
    {
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new ValidationException('Não foi possível enviar a foto.');
        }

        if ((int) ($file['size'] ?? 0) > 2 * 1024 * 1024) {
            throw new ValidationException('A foto deve ter no máximo 2 MB.');
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $mime = '';
        if (is_file($tmpPath) && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? (string) finfo_file($finfo, $tmpPath) : '';
            if ($finfo) {
                finfo_close($finfo);
            }
        }

        $canConvertToWebp = function_exists('imagecreatetruecolor') && function_exists('imagewebp');
        $extensions = [
            'image/jpeg' => $canConvertToWebp ? 'webp' : 'jpg',
            'image/png' => $canConvertToWebp ? 'webp' : 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($extensions[$mime])) {
            throw new ValidationException('Envie uma imagem JPG, PNG ou WebP.');
        }

        $storage = $this->profilePhotoStorage();
        $relativeDir = $storage['relative_dir'];
        $targetDir = $storage['target_dir'];
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Não foi possível criar a pasta de fotos de perfil.');
        }

        $filename = $userId . '_' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
        $targetPath = $targetDir . '/' . $filename;

        if (!$this->saveProfilePhotoWithoutMetadata($tmpPath, $targetPath, $mime)) {
            throw new ValidationException('Não foi possível salvar a foto.');
        }

        return $relativeDir . '/' . $filename;
    }

    public function saveProfilePhotoWithoutMetadata(string $sourcePath, string $targetPath, string $mime): bool
    {
        if (!function_exists('imagecreatetruecolor')) {
            return move_uploaded_file($sourcePath, $targetPath);
        }

        $image = match ($mime) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($sourcePath) : false,
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($sourcePath) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            default => false,
        };

        if (!$image) {
            return move_uploaded_file($sourcePath, $targetPath);
        }

        $targetExtension = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
        if ($targetExtension === 'webp' && function_exists('imagewebp')) {
            $saved = imagewebp($image, $targetPath, 82);
        } else {
            $saved = match ($mime) {
                'image/jpeg' => imagejpeg($image, $targetPath, 88),
                'image/png' => imagepng($image, $targetPath, 6),
                'image/webp' => function_exists('imagewebp') ? imagewebp($image, $targetPath, 86) : false,
                default => false,
            };
        }
        imagedestroy($image);

        if (!$saved) {
            @unlink($targetPath);
            return move_uploaded_file($sourcePath, $targetPath);
        }

        @unlink($sourcePath);
        return true;
    }

    public function deleteOldProfilePhoto(string $oldPhoto, string $newPhoto): void
    {
        if ($oldPhoto === '' || $oldPhoto === $newPhoto) {
            return;
        }

        $storage = $this->profilePhotoStorage();
        $projectRoot = dirname(__DIR__, 3);
        $baseDir = realpath($storage['target_dir']);
        $oldPath = realpath($projectRoot . '/' . ltrim($oldPhoto, '/'));

        if ($baseDir && $oldPath && str_starts_with($oldPath, $baseDir) && is_file($oldPath)) {
            @unlink($oldPath);
        }
    }

    public function profilePhotoStorage(): array
    {
        $projectRoot = dirname(__DIR__, 3);
        $configuredPath = trim((string) getenv('PROFILE_PHOTO_STORAGE_PATH'));
        if ($configuredPath === '' && function_exists('database_env_values')) {
            $env = database_env_values($projectRoot . '/backend/.env');
            $configuredPath = trim((string) ($env['PROFILE_PHOTO_STORAGE_PATH'] ?? ''));
        }

        $configuredPath = $configuredPath !== '' ? $configuredPath : 'backend/storage/profile_photos';
        $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $configuredPath);
        $targetDir = $this->isAbsolutePath($normalizedPath)
            ? $normalizedPath
            : $projectRoot . DIRECTORY_SEPARATOR . ltrim($normalizedPath, DIRECTORY_SEPARATOR);

        $projectReal = realpath($projectRoot);
        $targetParent = realpath(dirname($targetDir)) ?: dirname($targetDir);
        $targetComparable = $targetParent . DIRECTORY_SEPARATOR . basename($targetDir);

        if ($projectReal && !str_starts_with($targetComparable, $projectReal . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('PROFILE_PHOTO_STORAGE_PATH precisa apontar para uma pasta dentro do projeto.');
        }

        $relativeDir = trim(str_replace(DIRECTORY_SEPARATOR, '/', substr($targetComparable, strlen((string) $projectReal))), '/');
        if ($relativeDir === '') {
            throw new RuntimeException('PROFILE_PHOTO_STORAGE_PATH inválido para fotos de perfil.');
        }

        return [
            'target_dir' => $targetDir,
            'relative_dir' => $relativeDir,
        ];
    }

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1
            || str_starts_with($path, DIRECTORY_SEPARATOR)
            || str_starts_with($path, '\\\\');
    }
}
