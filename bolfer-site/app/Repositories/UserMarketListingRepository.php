<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;

final class UserMarketListingRepository
{
    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO user_market_listings (
                seller_user_id,
                source_inventory_id,
                item_name,
                item_type,
                quantity,
                description,
                unlock_cost,
                locked_content,
                price_coins,
                status
            ) VALUES (
                :seller_user_id,
                :source_inventory_id,
                :item_name,
                :item_type,
                :quantity,
                :description,
                :unlock_cost,
                :locked_content,
                :price_coins,
                :status
            )'
        );

        $stmt->execute([
            'seller_user_id' => $data['seller_user_id'],
            'source_inventory_id' => $data['source_inventory_id'] ?? null,
            'item_name' => $data['item_name'],
            'item_type' => $data['item_type'],
            'quantity' => $data['quantity'],
            'description' => $data['description'] !== '' ? $data['description'] : null,
            'unlock_cost' => max(0, (int) ($data['unlock_cost'] ?? 0)),
            'locked_content' => $data['locked_content'] !== '' ? $data['locked_content'] : null,
            'price_coins' => $data['price_coins'],
            'status' => $data['status'] ?? 'active',
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public function findById(int $listingId, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT l.*, seller.username AS seller_username, buyer.username AS buyer_username
                FROM user_market_listings l
                JOIN users seller ON seller.id = l.seller_user_id
                LEFT JOIN users buyer ON buyer.id = l.buyer_user_id
                WHERE l.id = :id
                LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['id' => $listingId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function listActive(int $currentUserId = 0, array $filters = []): array
    {
        $params = [];
        $sql = 'SELECT l.*, seller.username AS seller_username';

        if ($currentUserId > 0) {
            $sql .= ', CASE WHEN l.seller_user_id = :current_user_id THEN 1 ELSE 0 END AS is_own_listing';
            $params['current_user_id'] = $currentUserId;
        } else {
            $sql .= ', 0 AS is_own_listing';
        }

        $sql .= '
                FROM user_market_listings l
                JOIN users seller ON seller.id = l.seller_user_id
                WHERE l.status = "active"';

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (
                l.item_name LIKE :search
                OR COALESCE(l.description, "") LIKE :search
                OR seller.username LIKE :search
            )';
            $params['search'] = '%' . $search . '%';
        }

        $itemType = trim((string) ($filters['item_type'] ?? ''));
        if ($itemType !== '') {
            $sql .= ' AND l.item_type = :item_type';
            $params['item_type'] = $itemType;
        }

        $priceMax = (int) ($filters['price_max'] ?? 0);
        if ($priceMax > 0) {
            $sql .= ' AND l.price_coins <= :price_max';
            $params['price_max'] = $priceMax;
        }

        $sort = (string) ($filters['sort'] ?? 'recent');
        $sql .= match ($sort) {
            'price_asc' => ' ORDER BY l.price_coins ASC, l.created_at DESC, l.id DESC',
            'price_desc' => ' ORDER BY l.price_coins DESC, l.created_at DESC, l.id DESC',
            'unlock_asc' => ' ORDER BY l.unlock_cost ASC, l.price_coins ASC, l.created_at DESC, l.id DESC',
            default => ' ORDER BY l.created_at DESC, l.id DESC',
        };

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function listBySeller(int $sellerUserId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT l.*, buyer.username AS buyer_username
             FROM user_market_listings l
             LEFT JOIN users buyer ON buyer.id = l.buyer_user_id
             WHERE l.seller_user_id = :seller_user_id
             ORDER BY l.created_at DESC, l.id DESC'
        );
        $stmt->execute(['seller_user_id' => $sellerUserId]);

        return $stmt->fetchAll();
    }

    public function markSold(int $listingId, int $buyerUserId): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE user_market_listings
             SET buyer_user_id = :buyer_user_id,
                 status = "sold",
                 sold_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $listingId,
            'buyer_user_id' => $buyerUserId,
        ]);
    }

    public function updateQuantity(int $listingId, int $quantity): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE user_market_listings
             SET quantity = :quantity,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $listingId,
            'quantity' => max(1, $quantity),
        ]);
    }

    public function markCancelled(int $listingId): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE user_market_listings
             SET status = "cancelled",
                 cancelled_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute(['id' => $listingId]);
    }
}
