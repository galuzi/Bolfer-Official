<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;

final class AdminApiTokenRepository
{
    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO admin_api_tokens (
                admin_id,
                token_name,
                token_hash,
                expires_at
            ) VALUES (
                :admin_id,
                :token_name,
                :token_hash,
                :expires_at
            )'
        );

        $stmt->execute([
            'admin_id' => (int) ($data['admin_id'] ?? 0),
            'token_name' => trim((string) ($data['token_name'] ?? 'Bolfer Desktop')),
            'token_hash' => trim((string) ($data['token_hash'] ?? '')),
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public function findActiveByHash(string $tokenHash): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT
                t.*,
                a.username,
                a.role,
                a.discord_activity_display_name,
                a.discord_activity_enabled,
                a.two_factor_enabled,
                a.two_factor_confirmed_at,
                a.last_login_at
             FROM admin_api_tokens t
             JOIN admins a ON a.id = t.admin_id
             WHERE t.token_hash = :token_hash
               AND t.revoked_at IS NULL
               AND (t.expires_at IS NULL OR t.expires_at > NOW())
             LIMIT 1'
        );
        $stmt->execute(['token_hash' => $tokenHash]);
        $token = $stmt->fetch();

        return $token ?: null;
    }

    public function touch(int $id): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE admin_api_tokens
             SET last_used_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    public function revokeByHash(string $tokenHash): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE admin_api_tokens
             SET revoked_at = COALESCE(revoked_at, NOW())
             WHERE token_hash = :token_hash'
        );
        $stmt->execute(['token_hash' => $tokenHash]);
    }
}
