<?php

declare(strict_types=1);

use App\Support\Env;

function env(string $key, ?string $default = null): ?string
{
    return Env::get($key, $default);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    $base = rtrim(env('APP_URL', ''), '/');
    return $base . $path;
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function flash_set(string $key, string $message): void
{
    $_SESSION['_flash'][$key] = $message;
}

function flash_get(string $key): ?string
{
    if (!isset($_SESSION['_flash'][$key])) {
        return null;
    }
    $msg = $_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);
    return $msg;
}

function admin_user(): ?array
{
    return $_SESSION['admin'] ?? null;
}

function require_admin(): void
{
    if (!admin_user()) {
        redirect('/admin/login');
    }
}

function admin_is_full(): bool
{
    return admin_role_level((string) (admin_user()['role'] ?? '')) >= 30;
}

function admin_is_founder(): bool
{
    return admin_role_level((string) (admin_user()['role'] ?? '')) >= 40;
}

function require_full_admin(): void
{
    require_admin();

    if (!admin_is_full()) {
        flash_set('error', 'Sem permissao para acessar esta area.');
        redirect('/admin/dashboard');
    }
}

function require_founder_admin(): void
{
    require_admin();

    if (!admin_is_founder()) {
        flash_set('error', 'Sem permissao para acessar esta area.');
        redirect('/admin/dashboard');
    }
}

function user_session(): ?array
{
    $sessionUser = $_SESSION['user'] ?? null;
    if (!$sessionUser) {
        return null;
    }

    $banService = new \App\Services\BanService();
    if (!$banService->validateCurrentUserSession($sessionUser)) {
        return null;
    }

    return $_SESSION['user'] ?? null;
}

function require_user(): void
{
    if (!user_session()) {
        redirect('/login');
    }
}

function request_json(): array
{
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        $cached = [];
        return $cached;
    }

    $decoded = json_decode($raw, true);
    $cached = is_array($decoded) ? $decoded : [];

    return $cached;
}

function request_data(): array
{
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($contentType, 'application/json')) {
        return request_json();
    }

    if ($_POST !== []) {
        return $_POST;
    }

    return request_json();
}

function json_response(array $payload, int $status = 200): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
    }

    http_response_code($status);
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo $json === false ? '{"ok":false,"message":"Falha ao gerar JSON."}' : $json;
    exit;
}

function csrf_token(): string
{
    return \App\Support\Csrf::token();
}

function csrf_field(): string
{
    return \App\Support\Csrf::field();
}

function captcha_challenge(string $scope): array
{
    return \App\Support\Captcha::challenge($scope);
}

function bearer_token(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

    if ($header === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if (!is_string($header) || $header === '') {
        return null;
    }

    if (!preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
        return null;
    }

    $token = trim((string) ($matches[1] ?? ''));

    return $token !== '' ? $token : null;
}

function admin_api_context(): ?array
{
    static $resolved = false;
    static $context = null;

    if ($resolved) {
        return $context;
    }

    $resolved = true;
    $token = bearer_token();
    if ($token === null) {
        return null;
    }

    $context = (new \App\Services\AdminApiAuthService())->authenticateFromBearer($token);

    return $context;
}

function require_admin_api(): array
{
    $context = admin_api_context();
    if (!$context) {
        json_response([
            'ok' => false,
            'message' => 'Nao autenticado.',
        ], 401);
    }

    return $context;
}

function admin_role_level(string $role): int
{
    return match ($role) {
        'founder' => 40,
        'admin' => 30,
        'staff' => 20,
        default => 0,
    };
}

function user_role_level(string $role): int
{
    return match ($role) {
        'moderador' => 25,
        'vip' => 15,
        'user' => 10,
        default => 10,
    };
}
