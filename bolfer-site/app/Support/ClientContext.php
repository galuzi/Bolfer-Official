<?php

declare(strict_types=1);

namespace App\Support;

final class ClientContext
{
    public static function isSecureRequest(): bool
    {
        $https = strtolower(trim((string) ($_SERVER['HTTPS'] ?? '')));
        if ($https !== '' && $https !== 'off' && $https !== '0') {
            return true;
        }

        $requestScheme = strtolower(trim((string) ($_SERVER['REQUEST_SCHEME'] ?? '')));
        if ($requestScheme === 'https') {
            return true;
        }

        $serverPort = trim((string) ($_SERVER['SERVER_PORT'] ?? ''));
        if ($serverPort === '443') {
            return true;
        }

        $remoteAddr = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if (!self::isTrustedProxy($remoteAddr)) {
            return false;
        }

        $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
        if ($forwardedProto !== '') {
            foreach (explode(',', $forwardedProto) as $proto) {
                if (trim($proto) === 'https') {
                    return true;
                }
            }
        }

        $forwardedSsl = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')));
        if ($forwardedSsl === 'on' || $forwardedSsl === '1') {
            return true;
        }

        $forwardedPort = trim((string) ($_SERVER['HTTP_X_FORWARDED_PORT'] ?? ''));
        if ($forwardedPort === '443') {
            return true;
        }

        $frontEndHttps = strtolower(trim((string) ($_SERVER['HTTP_FRONT_END_HTTPS'] ?? '')));
        if ($frontEndHttps === 'on' || $frontEndHttps === '1') {
            return true;
        }

        $cfVisitor = trim((string) ($_SERVER['HTTP_CF_VISITOR'] ?? ''));
        if ($cfVisitor !== '') {
            $decoded = json_decode($cfVisitor, true);
            if (is_array($decoded) && strtolower((string) ($decoded['scheme'] ?? '')) === 'https') {
                return true;
            }
        }

        return false;
    }

    public static function ipAddress(): ?string
    {
        $remoteAddr = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if (!self::isTrustedProxy($remoteAddr)) {
            return filter_var($remoteAddr, FILTER_VALIDATE_IP) ? $remoteAddr : null;
        }

        $cfIp = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
        if ($cfIp !== '' && filter_var($cfIp, FILTER_VALIDATE_IP)) {
            return $cfIp;
        }

        $forwardedFor = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($forwardedFor !== '') {
            foreach (explode(',', $forwardedFor) as $forwardedIp) {
                $candidate = trim($forwardedIp);
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }
        }

        return filter_var($remoteAddr, FILTER_VALIDATE_IP) ? $remoteAddr : null;
    }

    public static function userAgent(): string
    {
        return trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    }

    public static function acceptLanguage(): string
    {
        return trim((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    }

    public static function fingerprintHash(): ?string
    {
        foreach ([
            (string) ($_POST['device_fingerprint'] ?? ''),
            (string) ($_COOKIE['bolfer_fp'] ?? ''),
        ] as $candidate) {
            $candidate = strtolower(trim($candidate));
            if ($candidate !== '' && preg_match('/^[a-f0-9]{64}$/', $candidate) === 1) {
                return $candidate;
            }
        }

        $fallbackSeed = implode('|', [
            self::userAgent(),
            self::acceptLanguage(),
            (string) self::ipAddress(),
        ]);
        $fallbackSeed = trim($fallbackSeed, '|');

        return $fallbackSeed !== '' ? hash('sha256', $fallbackSeed) : null;
    }

    public static function route(): string
    {
        return trim((string) ($_SERVER['REQUEST_URI'] ?? ''));
    }

    public static function summary(): array
    {
        return [
            'ip_address' => self::ipAddress(),
            'fingerprint_hash' => self::fingerprintHash(),
            'user_agent' => self::userAgent(),
            'accept_language' => self::acceptLanguage(),
            'route' => self::route(),
        ];
    }

    private static function isTrustedProxy(string $remoteAddr): bool
    {
        if ($remoteAddr === '' || !filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (env('TRUST_ALL_PROXIES', '0') === '1') {
            return true;
        }

        $trusted = array_values(array_filter(array_map(
            static fn(string $item): string => trim($item),
            explode(',', (string) env('TRUSTED_PROXIES', ''))
        )));

        return in_array($remoteAddr, $trusted, true);
    }
}
