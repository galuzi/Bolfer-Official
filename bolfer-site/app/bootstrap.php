<?php

declare(strict_types=1);

$root = dirname(__DIR__);

spl_autoload_register(function (string $class) use ($root) {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $relative = str_replace('App\\', '', $class);
    $path = $root . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require_once $root . '/app/Support/helpers.php';

\App\Support\Env::load($root . '/.env');
\App\Support\Session::start();

date_default_timezone_set(env('APP_TZ', 'America/Sao_Paulo'));
ini_set('default_charset', 'UTF-8');

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$isDesktopApiRequest = str_starts_with($requestPath, '/api/desktop');
$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$isSensitivePath = str_starts_with($requestPath, '/admin')
    || str_starts_with($requestPath, '/usuario')
    || str_starts_with($requestPath, '/pedido')
    || in_array($requestPath, ['/login', '/register'], true)
    || $isDesktopApiRequest;
$isSecureRequest = \App\Support\ClientContext::isSecureRequest();

$normalizeHost = static function (string $value): string {
    $value = trim(strtolower($value));
    if ($value === '') {
        return '';
    }

    $parts = explode(':', $value, 2);

    return trim($parts[0]);
};

$currentHost = $normalizeHost((string) ($_SERVER['HTTP_HOST'] ?? ''));
$allowedHosts = [];
$configuredAllowedHosts = trim((string) env('APP_ALLOWED_HOSTS', ''));
if ($configuredAllowedHosts !== '') {
    foreach (explode(',', $configuredAllowedHosts) as $allowedHost) {
        $host = $normalizeHost($allowedHost);
        if ($host !== '') {
            $allowedHosts[] = $host;
        }
    }
} elseif (env('APP_ENV', 'local') !== 'local') {
    $appUrlHost = $normalizeHost((string) parse_url((string) env('APP_URL', ''), PHP_URL_HOST));
    if ($appUrlHost !== '') {
        $allowedHosts[] = $appUrlHost;
    }
}

if (
    PHP_SAPI !== 'cli'
    && $currentHost !== ''
    && $allowedHosts !== []
    && !in_array($currentHost, $allowedHosts, true)
) {
    http_response_code(400);
    echo 'Host invalido.';
    exit;
}

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; script-src 'self' 'unsafe-inline' https://www.googletagmanager.com; connect-src 'self' https://api.mercadopago.com https://api.mercadopago.com.br https://fonts.googleapis.com https://fonts.gstatic.com https://www.google-analytics.com https://region1.google-analytics.com https://www.googletagmanager.com; font-src 'self' data: https://fonts.gstatic.com; form-action 'self' https://www.mercadopago.com.br https://www.mercadopago.com https://mercadopago.com.br https://mercadopago.com;");
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('X-Permitted-Cross-Domain-Policies: none');
    if ($isSensitivePath) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
    if ($isSecureRequest) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    if ($isDesktopApiRequest) {
        $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
        $allowedOrigins = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('DESKTOP_API_ALLOWED_ORIGINS', ''))
        )));

        if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        }
    } else {
        header('Content-Type: text/html; charset=UTF-8');
    }
}

if ($isDesktopApiRequest && $requestMethod === 'OPTIONS') {
    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    $allowedOrigins = array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('DESKTOP_API_ALLOWED_ORIGINS', ''))
    )));

    if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
        http_response_code(204);
    } else {
        http_response_code(403);
    }
    exit;
}

if (env('APP_ENV', 'local') === 'local') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}

\App\Support\Csrf::validateCurrentRequest($requestMethod, $requestPath);
