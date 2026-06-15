<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;

final class BanRepository
{
    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO bans (
                user_id,
                username_snapshot,
                email_snapshot,
                ip_address,
                fingerprint_hash,
                reason,
                severity,
                note,
                status,
                banned_by_admin_id
            ) VALUES (
                :user_id,
                :username_snapshot,
                :email_snapshot,
                :ip_address,
                :fingerprint_hash,
                :reason,
                :severity,
                :note,
                :status,
                :banned_by_admin_id
            )'
        );

        $stmt->execute([
            'user_id' => !empty($data['user_id']) ? (int) $data['user_id'] : null,
            'username_snapshot' => !empty($data['username_snapshot']) ? strtolower(trim((string) $data['username_snapshot'])) : null,
            'email_snapshot' => !empty($data['email_snapshot']) ? strtolower(trim((string) $data['email_snapshot'])) : null,
            'ip_address' => !empty($data['ip_address']) ? trim((string) $data['ip_address']) : null,
            'fingerprint_hash' => !empty($data['fingerprint_hash']) ? strtolower(trim((string) $data['fingerprint_hash'])) : null,
            'reason' => trim((string) ($data['reason'] ?? 'Banimento reforcado')),
            'severity' => trim((string) ($data['severity'] ?? 'manual')),
            'note' => ($note = trim((string) ($data['note'] ?? ''))) !== '' ? $note : null,
            'status' => trim((string) ($data['status'] ?? 'active')),
            'banned_by_admin_id' => !empty($data['banned_by_admin_id']) ? (int) $data['banned_by_admin_id'] : null,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public function findActiveMatch(array $context, array $identifiers = []): ?array
    {
        $conditions = [];
        $params = ['status' => 'active'];

        $userId = (int) ($identifiers['user_id'] ?? 0);
        if ($userId > 0) {
            $conditions[] = 'b.user_id = :user_id';
            $params['user_id'] = $userId;
        }

        $username = strtolower(trim((string) ($identifiers['username'] ?? '')));
        if ($username !== '') {
            $conditions[] = 'b.username_snapshot = :username_snapshot';
            $params['username_snapshot'] = $username;
        }

        $email = strtolower(trim((string) ($identifiers['email'] ?? '')));
        if ($email !== '') {
            $conditions[] = 'b.email_snapshot = :email_snapshot';
            $params['email_snapshot'] = $email;
        }

        $ipAddress = trim((string) ($context['ip_address'] ?? ''));
        if ($ipAddress !== '') {
            $conditions[] = 'b.ip_address = :ip_address';
            $params['ip_address'] = $ipAddress;
        }

        $fingerprintHash = strtolower(trim((string) ($context['fingerprint_hash'] ?? '')));
        if ($fingerprintHash !== '') {
            $conditions[] = 'b.fingerprint_hash = :fingerprint_hash';
            $params['fingerprint_hash'] = $fingerprintHash;
        }

        if ($conditions === []) {
            return null;
        }

        $sql = 'SELECT b.*, a.username AS banned_by_username
                FROM bans b
                LEFT JOIN admins a ON a.id = b.banned_by_admin_id
                WHERE b.status = :status
                  AND (' . implode(' OR ', $conditions) . ')
                ORDER BY b.created_at DESC, b.id DESC
                LIMIT 1';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function revokeByUserId(int $userId, ?int $adminId = null): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE bans
             SET status = "revoked",
                 revoked_at = CURRENT_TIMESTAMP,
                 revoked_by_admin_id = :revoked_by_admin_id
             WHERE user_id = :user_id
               AND status = "active"'
        );
        $stmt->execute([
            'user_id' => $userId,
            'revoked_by_admin_id' => $adminId,
        ]);
    }
}
