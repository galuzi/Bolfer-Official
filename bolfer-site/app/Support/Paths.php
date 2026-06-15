<?php

declare(strict_types=1);

namespace App\Support;

final class Paths
{
    public static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function publicRoot(): string
    {
        $configured = trim((string) env('APP_PUBLIC_PATH', ''));
        if ($configured !== '') {
            $resolvedConfigured = self::normalizeDirectory($configured);
            if ($resolvedConfigured !== null) {
                return $resolvedConfigured;
            }
        }

        $documentRoot = trim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        if ($documentRoot !== '') {
            $resolvedDocumentRoot = self::normalizeDirectory($documentRoot);
            if ($resolvedDocumentRoot !== null) {
                return $resolvedDocumentRoot;
            }
        }

        $root = self::root();
        foreach ([
            $root . '/public_html',
            $root . '/public',
        ] as $candidate) {
            $resolvedCandidate = self::normalizeDirectory($candidate);
            if ($resolvedCandidate !== null) {
                return $resolvedCandidate;
            }
        }

        return $root . '/public_html';
    }

    public static function publicPath(string $suffix = ''): string
    {
        $base = self::publicRoot();
        if ($suffix === '') {
            return $base;
        }

        return $base . '/' . ltrim(str_replace('\\', '/', $suffix), '/');
    }

    private static function normalizeDirectory(string $path): ?string
    {
        $normalized = rtrim(str_replace('\\', '/', trim($path)), '/');
        if ($normalized === '' || !is_dir($normalized)) {
            return null;
        }

        return $normalized;
    }
}
