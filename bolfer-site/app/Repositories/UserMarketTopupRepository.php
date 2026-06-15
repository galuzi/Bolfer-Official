<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;

final class UserMarketTopupRepository
{
    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO user_market_topups (
                user_id,
                order_id,
                amount_brl,
                coins_amount,
                status
            ) VALUES (
                :user_id,
                :order_id,
                :amount_brl,
                :coins_amount,
                :status
            )'
        );

        $stmt->execute([
            'user_id' => $data['user_id'],
            'order_id' => $data['order_id'],
            'amount_brl' => $data['amount_brl'],
            'coins_amount' => $data['coins_amount'],
            'status' => $data['status'] ?? 'pending',
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public function findByOrderId(int $orderId, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM user_market_topups WHERE order_id = :order_id LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['order_id' => $orderId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function markPaid(int $topupId): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE user_market_topups
             SET status = "paid",
                 paid_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute(['id' => $topupId]);
    }

    public function listRecentByUserId(int $userId, int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        $stmt = Database::pdo()->prepare(
            "SELECT t.*, o.public_id
             FROM user_market_topups t
             JOIN orders o ON o.id = t.order_id
             WHERE t.user_id = :user_id
             ORDER BY t.created_at DESC, t.id DESC
             LIMIT {$limit}"
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }
}
