<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AdminApiTokenRepository;

final class AdminApiAuthService
{
    private const TOKEN_PREFIX = 'blf_';

    private AdminApiTokenRepository $tokenRepository;

    public function __construct()
    {
        $this->tokenRepository = new AdminApiTokenRepository();
    }

    public function issueToken(array $admin, string $tokenName): array
    {
        $plainTextToken = self::TOKEN_PREFIX . bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainTextToken);
        $ttlDays = max(1, (int) env('DESKTOP_TOKEN_TTL_DAYS', '7'));
        $normalizedTokenName = $this->normalizeTokenName($tokenName);
        $expiresAt = date('Y-m-d H:i:s', time() + ($ttlDays * 86400));

        $tokenId = $this->tokenRepository->create([
            'admin_id' => (int) ($admin['id'] ?? 0),
            'token_name' => $normalizedTokenName,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);

        return [
            'token_id' => $tokenId,
            'plain_text_token' => $plainTextToken,
            'expires_at' => $expiresAt,
            'token_name' => $normalizedTokenName,
        ];
    }

    public function authenticateFromBearer(?string $token): ?array
    {
        if ($token === null || $token === '') {
            return null;
        }

        $record = $this->tokenRepository->findActiveByHash(hash('sha256', $token));
        if (!$record) {
            return null;
        }

        $role = (string) ($record['role'] ?? 'staff');
        $requiresTwoFactor = \admin_role_level($role) >= 30 || !empty($record['two_factor_enabled']);
        if ($requiresTwoFactor && empty($record['two_factor_enabled'])) {
            return null;
        }

        $this->tokenRepository->touch((int) ($record['id'] ?? 0));

        return [
            'token' => [
                'id' => (int) ($record['id'] ?? 0),
                'name' => (string) ($record['token_name'] ?? 'Bolfer Desktop'),
                'last_used_at' => $record['last_used_at'] ?? null,
                'expires_at' => $record['expires_at'] ?? null,
            ],
            'admin' => [
                'id' => (int) ($record['admin_id'] ?? 0),
                'username' => (string) ($record['username'] ?? ''),
                'role' => $role,
                'discord_activity_display_name' => $record['discord_activity_display_name'] ?? null,
                'discord_activity_enabled' => !empty($record['discord_activity_enabled']) ? 1 : 0,
                'two_factor_enabled' => !empty($record['two_factor_enabled']) ? 1 : 0,
                'two_factor_confirmed_at' => $record['two_factor_confirmed_at'] ?? null,
                'last_login_at' => $record['last_login_at'] ?? null,
            ],
        ];
    }

    public function revoke(?string $token): void
    {
        if ($token === null || $token === '') {
            return;
        }

        $this->tokenRepository->revokeByHash(hash('sha256', $token));
    }

    private function normalizeTokenName(string $tokenName): string
    {
        $tokenName = trim($tokenName);

        if ($tokenName === '') {
            return 'Bolfer Desktop';
        }

        if (strlen($tokenName) > 120) {
            return substr($tokenName, 0, 120);
        }

        return $tokenName;
    }
}
