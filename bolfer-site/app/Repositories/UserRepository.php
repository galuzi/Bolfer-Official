<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;

final class UserRepository
{
    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO users (
                username,
                email,
                password_hash,
                role,
                email_verified_at,
                email_verification_token_hash,
                email_verification_sent_at
            ) VALUES (
                :username,
                :email,
                :password_hash,
                :role,
                :email_verified_at,
                :email_verification_token_hash,
                :email_verification_sent_at
            )'
        );
        $stmt->execute([
            'username' => $data['username'],
            'email' => $data['email'],
            'password_hash' => $data['password_hash'],
            'role' => $data['role'] ?? 'user',
            'email_verified_at' => $data['email_verified_at'] ?? null,
            'email_verification_token_hash' => $data['email_verification_token_hash'] ?? null,
            'email_verification_sent_at' => $data['email_verification_sent_at'] ?? null,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public function findByLogin(string $login): ?array
    {
        $login = trim($login);
        if ($login === '') {
            return null;
        }

        if (str_contains($login, '@')) {
            $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE email = :login LIMIT 1');
        } else {
            $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE username = :login LIMIT 1');
        }

        $stmt->execute(['login' => $login]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function existsByUsername(string $username): bool
    {
        $stmt = Database::pdo()->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        return (bool) $stmt->fetchColumn();
    }

    public function existsByEmail(string $email): bool
    {
        $stmt = Database::pdo()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        return (bool) $stmt->fetchColumn();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => strtolower(trim($email))]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findByEmailVerificationTokenHash(string $tokenHash): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM users
             WHERE email_verification_token_hash = :token_hash
             LIMIT 1'
        );
        $stmt->execute(['token_hash' => strtolower(trim($tokenHash))]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findByPasswordResetTokenHash(string $tokenHash): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM users
             WHERE password_reset_token_hash = :token_hash
             LIMIT 1'
        );
        $stmt->execute(['token_hash' => strtolower(trim($tokenHash))]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function allWithBans(): array
    {
        $stmt = Database::pdo()->query('SELECT u.*, a.username AS banned_by_username FROM users u LEFT JOIN admins a ON a.id = u.banned_by_admin_id ORDER BY u.created_at DESC');
        return $stmt->fetchAll();
    }

    public function searchWithBans(array $filters): array
    {
        $sql = 'SELECT u.*, a.username AS banned_by_username FROM users u LEFT JOIN admins a ON a.id = u.banned_by_admin_id';
        $where = [];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(u.username LIKE :q OR u.email LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        $role = trim((string) ($filters['role'] ?? ''));
        if ($role !== '') {
            $where[] = 'u.role = :role';
            $params['role'] = $role;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status === 'banned') {
            $where[] = 'u.is_banned = 1';
        } elseif ($status === 'active') {
            $where[] = 'u.is_banned = 0';
        }

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY u.created_at DESC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function setBan(int $id, bool $ban, ?int $adminId, ?string $reason): void
    {
        if ($ban) {
            $stmt = Database::pdo()->prepare('UPDATE users SET is_banned = 1, banned_at = NOW(), banned_by_admin_id = :admin_id, banned_reason = :reason, session_revoked_at = NOW() WHERE id = :id');
            $stmt->execute([
                'admin_id' => $adminId,
                'reason' => $reason,
                'id' => $id,
            ]);
            return;
        }

        $stmt = Database::pdo()->prepare('UPDATE users SET is_banned = 0, banned_at = NULL, banned_by_admin_id = NULL, banned_reason = NULL WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function updateLastLogin(int $id): void
    {
        $stmt = Database::pdo()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function updateSecurityContext(int $id, ?string $ipAddress, ?string $fingerprintHash, bool $touchLastLogin = true): void
    {
        $sql = $touchLastLogin
            ? 'UPDATE users
               SET last_login_at = NOW(),
                   last_ip_address = :last_ip_address,
                   last_fingerprint_hash = :last_fingerprint_hash
               WHERE id = :id'
            : 'UPDATE users
               SET last_ip_address = :last_ip_address,
                   last_fingerprint_hash = :last_fingerprint_hash
               WHERE id = :id';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'last_ip_address' => $ipAddress !== null && $ipAddress !== '' ? $ipAddress : null,
            'last_fingerprint_hash' => $fingerprintHash !== null && $fingerprintHash !== '' ? strtolower($fingerprintHash) : null,
        ]);
    }

    public function activateTwoFactor(int $id, string $secret, array $recoveryCodeHashes): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE users
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
            'UPDATE users
             SET two_factor_recovery_codes = :two_factor_recovery_codes
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'two_factor_recovery_codes' => json_encode(array_values($recoveryCodeHashes), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function setEmailVerificationToken(int $id, string $tokenHash): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE users
             SET email_verification_token_hash = :token_hash,
                 email_verification_sent_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'token_hash' => strtolower(trim($tokenHash)),
        ]);
    }

    public function setPasswordResetToken(int $id, string $tokenHash): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE users
             SET password_reset_token_hash = :token_hash,
                 password_reset_sent_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'token_hash' => strtolower(trim($tokenHash)),
        ]);
    }

    public function clearPasswordResetToken(int $id): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE users
             SET password_reset_token_hash = NULL,
                 password_reset_sent_at = NULL
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE users
             SET password_hash = :password_hash,
                 password_reset_token_hash = NULL,
                 password_reset_sent_at = NULL,
                 session_revoked_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'password_hash' => $passwordHash,
        ]);
    }

    public function markEmailVerified(int $id): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE users
             SET email_verified_at = NOW(),
                 email_verification_token_hash = NULL,
                 email_verification_sent_at = NULL
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }
}
