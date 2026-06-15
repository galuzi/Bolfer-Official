<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
final class AdminInviteKeyRepository
{
    public function all(array $filters = []): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = (string) ($filters['status'] ?? 'all');

        $sql = 'SELECT k.*, c.username AS created_by_email, u.username AS used_by_email
            FROM admin_invite_keys k
            LEFT JOIN admins c ON c.id = k.created_by_admin_id
            LEFT JOIN admins u ON u.id = k.used_by_admin_id';
        $where = [];
        $params = [];

        if ($status === 'available') {
            $where[] = 'k.used_by_admin_id IS NULL';
        } elseif ($status === 'used') {
            $where[] = 'k.used_by_admin_id IS NOT NULL';
        }

        if ($search !== '') {
            $where[] = '(k.invite_key LIKE :search OR c.username LIKE :search OR u.username LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY k.created_at DESC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countAvailable(): int
    {
        $stmt = Database::pdo()->query('SELECT COUNT(*) FROM admin_invite_keys WHERE used_by_admin_id IS NULL');
        return (int) $stmt->fetchColumn();
    }

    public function exists(string $key): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM admin_invite_keys WHERE invite_key = :key LIMIT 1');
        $stmt->execute(['key' => $key]);
        return (bool) $stmt->fetchColumn();
    }

    public function findAvailable(string $key, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM admin_invite_keys WHERE invite_key = :key AND used_by_admin_id IS NULL LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $key, ?int $createdByAdminId): int
    {
        $stmt = Database::pdo()->prepare('INSERT INTO admin_invite_keys (invite_key, created_by_admin_id) VALUES (:invite_key, :created_by_admin_id)');
        $stmt->execute([
            'invite_key' => $key,
            'created_by_admin_id' => $createdByAdminId,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public function markUsed(int $id, int $adminId): bool
    {
        $stmt = Database::pdo()->prepare('UPDATE admin_invite_keys SET used_by_admin_id = :admin_id, used_at = NOW() WHERE id = :id AND used_by_admin_id IS NULL');
        $stmt->execute([
            'id' => $id,
            'admin_id' => $adminId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = Database::pdo()->prepare('DELETE FROM admin_invite_keys WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }
}
