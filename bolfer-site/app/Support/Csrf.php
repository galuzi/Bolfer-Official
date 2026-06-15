<?php

declare(strict_types=1);

namespace App\Support;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function validateCurrentRequest(string $method, string $path): void
    {
        $method = strtoupper($method);
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        if (self::isExemptPath($path)) {
            return;
        }

        if (!self::hasValidOrigin()) {
            self::reject('Origem da requisicao invalida.');
        }

        $providedToken = self::requestToken();
        if ($providedToken === null || !hash_equals(self::token(), $providedToken)) {
            self::reject('Sessao expirada ou token de seguranca invalido.');
        }
    }

    private static function isExemptPath(string $path): bool
    {
        if (str_starts_with($path, '/api/desktop')) {
            return true;
        }

        return $path === '/webhook/mercadopago';
    }

    private static function requestToken(): ?string
    {
        $postToken = trim((string) ($_POST['csrf_token'] ?? ''));
        if ($postToken !== '') {
            return $postToken;
        }

        $headerToken = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if ($headerToken !== '') {
            return $headerToken;
        }

        $json = \request_json();
        $jsonToken = trim((string) ($json['csrf_token'] ?? ''));

        return $jsonToken !== '' ? $jsonToken : null;
    }

    private static function hasValidOrigin(): bool
    {
        $targetHost = self::normalizedHost((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($targetHost === '') {
            return false;
        }

        foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $header) {
            $value = trim((string) ($_SERVER[$header] ?? ''));
            if ($value === '') {
                continue;
            }

            $host = self::normalizedHost((string) parse_url($value, PHP_URL_HOST));
            if ($host === '' || !hash_equals($targetHost, $host)) {
                return false;
            }
        }

        return true;
    }

    private static function normalizedHost(string $value): string
    {
        $value = trim(strtolower($value));
        if ($value === '') {
            return '';
        }

        $parts = explode(':', $value, 2);

        return trim($parts[0]);
    }

    private static function reject(string $message): void
    {
        if (!headers_sent()) {
            http_response_code(419);
        }

        flash_set('error', $message);

        $referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
        if ($referer !== '') {
            $refererHost = self::normalizedHost((string) parse_url($referer, PHP_URL_HOST));
            $currentHost = self::normalizedHost((string) ($_SERVER['HTTP_HOST'] ?? ''));
            if ($refererHost !== '' && $currentHost !== '' && hash_equals($currentHost, $refererHost)) {
                $path = (string) (parse_url($referer, PHP_URL_PATH) ?? '/');
                $query = (string) (parse_url($referer, PHP_URL_QUERY) ?? '');
                redirect($query !== '' ? ($path . '?' . $query) : $path);
            }
        }

        redirect('/');
    }
}
