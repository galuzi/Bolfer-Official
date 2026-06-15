<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

final class RankingRepository
{
    public function topCoins(int $limit = 10): array
    {
        $limit = max(1, min(25, $limit));
        $sql = <<<'SQL'
SELECT
    u.id,
    u.username,
    u.role,
    u.market_coins,
    COALESCE(SUM(CASE WHEN t.status = 'paid' THEN t.amount_brl ELSE 0 END), 0) AS total_topup_brl,
    COALESCE(SUM(CASE WHEN t.status = 'paid' THEN t.coins_amount ELSE 0 END), 0) AS total_topup_coins,
    COALESCE(SUM(CASE WHEN t.status = 'paid' THEN 1 ELSE 0 END), 0) AS topup_count,
    MAX(CASE WHEN t.status = 'paid' THEN t.updated_at ELSE NULL END) AS last_topup_at
FROM users u
LEFT JOIN user_market_topups t ON t.user_id = u.id
WHERE u.market_coins > 0
GROUP BY u.id, u.username, u.role, u.market_coins
ORDER BY u.market_coins DESC, total_topup_coins DESC, u.username ASC
LIMIT :limit
SQL;

        $stmt = Database::pdo()->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function topDonates(int $limit = 10): array
    {
        if (!$this->ordersHaveUserId()) {
            return [];
        }

        $limit = max(1, min(25, $limit));
        $sql = <<<'SQL'
SELECT
    u.id,
    u.username,
    u.role,
    SUM(o.total_amount_snapshot) AS total_donated,
    COUNT(*) AS donations_count,
    MAX(o.created_at) AS last_donation_at
FROM orders o
INNER JOIN users u ON u.id = o.user_id
WHERE o.user_id IS NOT NULL
  AND o.status = 'delivered'
  AND LOWER(TRIM(COALESCE(o.delivery_notes, ''))) = 'doacao'
GROUP BY u.id, u.username, u.role
ORDER BY total_donated DESC, donations_count DESC, last_donation_at ASC
LIMIT :limit
SQL;

        $stmt = Database::pdo()->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    private function ordersHaveUserId(): bool
    {
        static $resolved = null;
        if ($resolved !== null) {
            return $resolved;
        }

        $stmt = Database::pdo()->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'user_id'"
        );

        $resolved = ((int) $stmt->fetchColumn()) > 0;

        return $resolved;
    }
}