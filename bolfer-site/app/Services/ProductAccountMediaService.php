<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Paths;

final class ProductAccountMediaService
{
    private const MAX_IMAGES = 8;
    private const MAX_FILE_SIZE = 5242880;

    public function sync(array $currentImages, array|null $uploadedFiles, array $removeImages = []): array
    {
        $currentImages = $this->sanitizeStoredImages($currentImages);
        $removeImages = $this->sanitizeStoredImages($removeImages);

        $images = array_values(array_filter(
            $currentImages,
            static fn(string $path): bool => !in_array($path, $removeImages, true)
        ));

        foreach ($removeImages as $path) {
            if (in_array($path, $currentImages, true)) {
                $this->deleteFile($path);
            }
        }

        $files = $this->normalizeUploadedFiles($uploadedFiles);
        if (count($images) + count($files) > self::MAX_IMAGES) {
            throw new \RuntimeException('Você pode manter no máximo 8 imagens por conta.');
        }

        foreach ($files as $file) {
            $images[] = $this->storeUploadedWebp($file);
        }

        return array_values(array_unique($images));
    }

    public function syncDesktop(array $currentImages, array $uploadedImages = [], array $removeImages = []): array
    {
        $currentImages = $this->sanitizeStoredImages($currentImages);
        $removeImages = $this->sanitizeStoredImages($removeImages);

        $images = array_values(array_filter(
            $currentImages,
            static fn(string $path): bool => !in_array($path, $removeImages, true)
        ));

        foreach ($removeImages as $path) {
            if (in_array($path, $currentImages, true)) {
                $this->deleteFile($path);
            }
        }

        $files = $this->normalizeDesktopImages($uploadedImages);
        if (count($images) + count($files) > self::MAX_IMAGES) {
            throw new \RuntimeException('Você pode manter no máximo 8 imagens por conta.');
        }

        foreach ($files as $file) {
            $images[] = $this->storeDesktopWebp($file);
        }

        return array_values(array_unique($images));
    }

    public function deleteAll(array $images): void
    {
        foreach ($this->sanitizeStoredImages($images) as $path) {
            $this->deleteFile($path);
        }
    }

    public function decodeStoredImages(?string $payload): array
    {
        if ($payload === null || trim($payload) === '') {
            return [];
        }

        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $this->sanitizeStoredImages($decoded);
    }

    private function sanitizeStoredImages(array $images): array
    {
        $items = [];
        foreach ($images as $image) {
            if (!is_string($image)) {
                continue;
            }

            $value = trim($image);
            if ($value === '' || !str_starts_with($value, '/assets/img/products/accounts/')) {
                continue;
            }

            $items[] = $value;
        }

        return array_values(array_unique($items));
    }

    private function normalizeUploadedFiles(array|null $uploadedFiles): array
    {
        if ($uploadedFiles === null || !isset($uploadedFiles['name']) || !is_array($uploadedFiles['name'])) {
            return [];
        }

        $normalized = [];
        foreach ($uploadedFiles['name'] as $index => $name) {
            $error = (int) ($uploadedFiles['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($error !== UPLOAD_ERR_OK) {
                throw new \RuntimeException('Falha ao enviar uma das imagens da conta.');
            }

            $normalized[] = [
                'name' => (string) $name,
                'tmp_name' => (string) ($uploadedFiles['tmp_name'][$index] ?? ''),
                'size' => (int) ($uploadedFiles['size'][$index] ?? 0),
            ];
        }

        return $normalized;
    }

    private function normalizeDesktopImages(array $uploadedImages): array
    {
        $normalized = [];

        foreach ($uploadedImages as $image) {
            if (!is_array($image)) {
                continue;
            }

            $name = trim((string) ($image['name'] ?? ''));
            $data = trim((string) ($image['data'] ?? $image['dataUrl'] ?? ''));
            if ($name === '' || $data === '') {
                continue;
            }

            $normalized[] = [
                'name' => $name,
                'type' => trim((string) ($image['type'] ?? '')),
                'data' => $data,
            ];
        }

        return $normalized;
    }

    private function storeUploadedWebp(array $file): string
    {
        $tmpPath = $file['tmp_name'] ?? '';
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new \RuntimeException('Não foi possível validar uma das imagens enviadas.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_FILE_SIZE) {
            throw new \RuntimeException('Cada imagem da conta deve ter no máximo 5 MB.');
        }

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($extension !== 'webp') {
            throw new \RuntimeException('As imagens da conta precisam estar em formato WEBP.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath);
        if ($mime !== 'image/webp') {
            throw new \RuntimeException('Foi enviada uma imagem inválida. Use apenas arquivos WEBP.');
        }

        $targetPath = $this->newTargetPath();

        if (!move_uploaded_file($tmpPath, $targetPath)) {
            throw new \RuntimeException('Não foi possível salvar uma das imagens da conta.');
        }

        return $this->toPublicPath($targetPath);
    }

    private function storeDesktopWebp(array $file): string
    {
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($extension !== 'webp') {
            throw new \RuntimeException('As imagens da conta precisam estar em formato WEBP.');
        }

        $rawPayload = (string) ($file['data'] ?? '');
        if ($rawPayload === '') {
            throw new \RuntimeException('Não foi possível ler uma das imagens da conta.');
        }

        $base64 = $rawPayload;
        if (str_starts_with($rawPayload, 'data:')) {
            if (!preg_match('/^data:image\/webp;base64,(.+)$/', $rawPayload, $matches)) {
                throw new \RuntimeException('Foi enviada uma imagem inválida. Use apenas arquivos WEBP.');
            }

            $base64 = (string) ($matches[1] ?? '');
        }

        $binary = base64_decode(str_replace(' ', '+', $base64), true);
        if (!is_string($binary) || $binary === '') {
            throw new \RuntimeException('Não foi possível decodificar uma das imagens da conta.');
        }

        $size = strlen($binary);
        if ($size <= 0 || $size > self::MAX_FILE_SIZE) {
            throw new \RuntimeException('Cada imagem da conta deve ter no máximo 5 MB.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($binary);
        if ($mime !== 'image/webp') {
            throw new \RuntimeException('Foi enviada uma imagem inválida. Use apenas arquivos WEBP.');
        }

        $targetPath = $this->newTargetPath();
        if (file_put_contents($targetPath, $binary) === false) {
            throw new \RuntimeException('Não foi possível salvar uma das imagens da conta.');
        }

        return $this->toPublicPath($targetPath);
    }

    private function newTargetPath(): string
    {
        $targetDir = Paths::publicPath('/assets/img/products/accounts');
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Não foi possível preparar a pasta de imagens da conta.');
        }

        return $targetDir . '/account-' . bin2hex(random_bytes(12)) . '.webp';
    }

    private function toPublicPath(string $targetPath): string
    {
        return '/assets/img/products/accounts/' . basename($targetPath);
    }

    private function deleteFile(string $publicPath): void
    {
        $fullPath = Paths::publicPath($publicPath);
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}
