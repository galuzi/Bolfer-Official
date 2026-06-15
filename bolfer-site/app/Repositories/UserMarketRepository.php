<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;

final class UserMarketRepository
{
    public function getBalance(int $userId): int
    {
        $stmt = Database::pdo()->prepare('SELECT market_coins FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $balance = $stmt->fetchColumn();

        return $balance === false ? 0 : (int) $balance;
    }

    public function getBalancesByUserIds(array $userIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $userIds)));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $stmt = Database::pdo()->prepare("SELECT id, market_coins FROM users WHERE id IN ({$placeholders})");
        $stmt->execute($ids);

        $balances = [];
        foreach ($stmt->fetchAll() as $row) {
            $balances[(int) ($row['id'] ?? 0)] = (int) ($row['market_coins'] ?? 0);
        }

        return $balances;
    }

    public function lockUserBalance(int $userId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT id, market_coins FROM users WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function updateBalance(int $userId, int $balance): void
    {
        $stmt = Database::pdo()->prepare('UPDATE users SET market_coins = :balance WHERE id = :id');
        $stmt->execute([
            'id' => $userId,
            'balance' => $balance,
        ]);
    }

    public function addTransaction(array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO user_market_transactions (
                user_id,
                amount,
                transaction_type,
                note,
                created_by_admin_id,
                related_inventory_id
            ) VALUES (
                :user_id,
                :amount,
                :transaction_type,
                :note,
                :created_by_admin_id,
                :related_inventory_id
            )'
        );

        $stmt->execute([
            'user_id' => $data['user_id'],
            'amount' => $data['amount'],
            'transaction_type' => $data['transaction_type'],
            'note' => $data['note'] !== '' ? $data['note'] : null,
            'created_by_admin_id' => $data['created_by_admin_id'] ?? null,
            'related_inventory_id' => $data['related_inventory_id'] ?? null,
        ]);
    }

    public function listRecentByUserId(int $userId, int $limit = 8): array
    {
        $limit = max(1, min(20, $limit));
        $stmt = Database::pdo()->prepare(
            "SELECT t.*, a.username AS admin_username
             FROM user_market_transactions t
             LEFT JOIN admins a ON a.id = t.created_by_admin_id
             WHERE t.user_id = :user_id
             ORDER BY t.created_at DESC, t.id DESC
             LIMIT {$limit}"
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }
}
