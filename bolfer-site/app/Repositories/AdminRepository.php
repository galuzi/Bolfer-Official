<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;

final class AdminRepository
{
    public function countAll(): int
    {
        $stmt = Database::pdo()->query('SELECT COUNT(*) FROM admins');
        return (int) $stmt->fetchColumn();
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM admins WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        $admin = $stmt->fetch();
        return $admin ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM admins WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $admin = $stmt->fetch();
        return $admin ?: null;
    }

    public function existsByUsername(string $username): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM admins WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        return (bool) $stmt->fetchColumn();
    }

    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO admins (
                username,
                password_hash,
                role,
                discord_activity_display_name,
                discord_activity_enabled,
                two_factor_secret,
                two_factor_enabled,
                two_factor_recovery_codes,
                two_factor_confirmed_at
            ) VALUES (
                :username,
                :password_hash,
                :role,
                :discord_activity_display_name,
                :discord_activity_enabled,
                :two_factor_secret,
                :two_factor_enabled,
                :two_factor_recovery_codes,
                :two_factor_confirmed_at
            )'
        );
        $stmt->execute([
            'username' => $data['username'],
            'password_hash' => $data['password_hash'],
            'role' => $data['role'] ?? 'admin',
            'discord_activity_display_name' => $data['discord_activity_display_name'] ?? null,
            'discord_activity_enabled' => !array_key_exists('discord_activity_enabled', $data) || !empty($data['discord_activity_enabled']) ? 1 : 0,
            'two_factor_secret' => ($secret = trim((string) ($data['two_factor_secret'] ?? ''))) !== '' ? $secret : null,
            'two_factor_enabled' => !empty($data['two_factor_enabled']) ? 1 : 0,
            'two_factor_recovery_codes' => !empty($data['two_factor_recovery_codes'])
                ? json_encode(array_values((array) $data['two_factor_recovery_codes']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null,
            'two_factor_confirmed_at' => $data['two_factor_confirmed_at'] ?? null,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public function updateLastLogin(int $id): void
    {
        $stmt = Database::pdo()->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function updateDiscordProfile(int $id, ?string $displayName, bool $enabled): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE admins
             SET discord_activity_display_name = :discord_activity_display_name,
                 discord_activity_enabled = :discord_activity_enabled
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'discord_activity_display_name' => ($display = trim((string) $displayName)) !== '' ? $display : null,
            'discord_activity_enabled' => $enabled ? 1 : 0,
        ]);
    }

    public function activateTwoFactor(int $id, string $secret, array $recoveryCodeHashes): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE admins
             SET two_factor_secret = :two_factor_secret,
                 two_factor_enabled = 1,
                 two_factor_recovery_codes = :two_factor_recovery_codes,
                 two_factor_confirmed_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'two_factor_secret' => strtoupper(trim($secret)),
            'two_factor_recovery_codes' => json_encode(array_values($recoveryCodeHashes), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function updateTwoFactorRecoveryCodes(int $id, array $recoveryCodeHashes): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE admins
             SET two_factor_recovery_codes = :two_factor_recovery_codes
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'two_factor_recovery_codes' => json_encode(array_values($recoveryCodeHashes), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}
