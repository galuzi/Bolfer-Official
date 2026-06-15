<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;

final class UserInventoryRepository
{
    private const TYPE_OPTIONS = [
        'jogo' => 'Jogo',
        'chave' => 'Chave',
        'jogo_aaa' => 'Jogo AAA',
        'nitro' => 'Nitro',
        'skin' => 'Skin',
        'conta' => 'Conta',
        'beneficio' => 'Beneficio',
        'moeda' => 'Moeda',
        'outro' => 'Outro',
    ];

    public static function typeOptions(): array
    {
        return self::TYPE_OPTIONS;
    }

    public static function marketTypeOptions(): array
    {
        return [
            'jogo' => 'Jogo',
            'chave' => 'Chave',
        ];
    }

    public static function isKeyType(string $itemType): bool
    {
        return $itemType === 'chave';
    }

    public static function usesKeyUnlock(string $itemType): bool
    {
        return in_array($itemType, ['jogo', 'jogo_aaa'], true);
    }

    public function listByUserId(int $userId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM user_inventory WHERE user_id = :user_id ORDER BY updated_at DESC, id DESC');
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    public function listByUserIds(array $userIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $userIds)));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $stmt = Database::pdo()->prepare("SELECT * FROM user_inventory WHERE user_id IN ({$placeholders}) ORDER BY user_id ASC, updated_at DESC, id DESC");
        $stmt->execute($ids);

        return $stmt->fetchAll();
    }

    public function listSellableByUserId(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM user_inventory
             WHERE user_id = :user_id
               AND quantity > 0
               AND (
                    (
                        unlock_cost > 0
                        AND locked_content IS NOT NULL
                        AND locked_content <> ""
                        AND is_unlocked = 0
                    )
                    OR (
                        item_type = "chave"
                        AND is_unlocked = 1
                    )
               )
             ORDER BY updated_at DESC, id DESC'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    public function addItem(array $data): void
    {
        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $itemType = (string) ($data['item_type'] ?? 'outro');
        $unlockCost = max(0, (int) ($data['unlock_cost'] ?? 0));
        $lockedContent = trim((string) ($data['locked_content'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $mergeExisting = !empty($data['merge_existing']);
        $isKeyType = self::isKeyType($itemType);

        if ($isKeyType) {
            $unlockCost = 0;
            $lockedContent = '';
        }

        if ($mergeExisting && $unlockCost === 0 && $lockedContent === '') {
            $existing = $this->findMergeableOpenItem(
                (int) $data['user_id'],
                (string) $data['item_name'],
                $itemType,
                $description
            );

            if ($existing) {
                $stmt = Database::pdo()->prepare('UPDATE user_inventory SET quantity = quantity + :quantity, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
                $stmt->execute([
                    'id' => $existing['id'],
                    'quantity' => $quantity,
                ]);
                return;
            }
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO user_inventory (
                user_id,
                item_name,
                item_type,
                quantity,
                description,
                unlock_cost,
                locked_content,
                is_unlocked
            ) VALUES (
                :user_id,
                :item_name,
                :item_type,
                :quantity,
                :description,
                :unlock_cost,
                :locked_content,
                :is_unlocked
            )'
        );

        $stmt->execute([
            'user_id' => (int) $data['user_id'],
            'item_name' => $data['item_name'],
            'item_type' => $itemType,
            'quantity' => $quantity,
            'description' => $description !== '' ? $description : null,
            'unlock_cost' => $unlockCost,
            'locked_content' => $lockedContent !== '' ? $lockedContent : null,
            'is_unlocked' => $unlockCost > 0 && $lockedContent !== '' ? 0 : 1,
        ]);
    }

    public function addTransferredItem(int $userId, array $listing): array
    {
        $itemType = (string) ($listing['item_type'] ?? 'outro');
        $unlockCost = max(0, (int) ($listing['unlock_cost'] ?? 0));
        $lockedContent = trim((string) ($listing['locked_content'] ?? ''));
        $description = trim((string) ($listing['description'] ?? ''));
        if (self::isKeyType($itemType)) {
            $unlockCost = 0;
            $lockedContent = '';
        }
        $isUnlocked = $unlockCost > 0 && $lockedContent !== '' ? 0 : 1;
        $quantity = max(1, (int) ($listing['quantity'] ?? 1));

        if ($unlockCost === 0 && $lockedContent === '') {
            $existing = $this->findMergeableOpenItem(
                $userId,
                (string) ($listing['item_name'] ?? 'Item'),
                $itemType,
                $description
            );

            if ($existing) {
                $stmt = Database::pdo()->prepare(
                    'UPDATE user_inventory
                     SET quantity = quantity + :quantity,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id'
                );
                $stmt->execute([
                    'id' => $existing['id'],
                    'quantity' => $quantity,
                ]);
                return [
                    'inventory_id' => (int) ($existing['id'] ?? 0),
                    'merged' => true,
                    'quantity' => $quantity,
                    'is_unlocked' => 1,
                ];
            }
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO user_inventory (
                user_id,
                item_name,
                item_type,
                quantity,
                description,
                unlock_cost,
                locked_content,
                is_unlocked
            ) VALUES (
                :user_id,
                :item_name,
                :item_type,
                :quantity,
                :description,
                :unlock_cost,
                :locked_content,
                :is_unlocked
            )'
        );

        $stmt->execute([
            'user_id' => $userId,
            'item_name' => (string) ($listing['item_name'] ?? 'Item'),
            'item_type' => $itemType,
            'quantity' => $quantity,
            'description' => $description !== '' ? $description : null,
            'unlock_cost' => $unlockCost,
            'locked_content' => $lockedContent !== '' ? $lockedContent : null,
            'is_unlocked' => $isUnlocked,
        ]);

        return [
            'inventory_id' => (int) Database::pdo()->lastInsertId(),
            'merged' => false,
            'quantity' => $quantity,
            'is_unlocked' => $isUnlocked,
        ];
    }

    public function getAvailableKeyCount(int $userId): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COALESCE(SUM(quantity), 0)
             FROM user_inventory
             WHERE user_id = :user_id
               AND item_type = "chave"
               AND quantity > 0
               AND is_unlocked = 1'
        );
        $stmt->execute(['user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    public function consumeKeys(int $userId, int $quantity): bool
    {
        $quantity = max(1, $quantity);
        $stmt = Database::pdo()->prepare(
            'SELECT id, quantity
             FROM user_inventory
             WHERE user_id = :user_id
               AND item_type = "chave"
               AND quantity > 0
               AND is_unlocked = 1
             ORDER BY updated_at ASC, id ASC
             FOR UPDATE'
        );
        $stmt->execute(['user_id' => $userId]);
        $keyRows = $stmt->fetchAll();

        $available = 0;
        foreach ($keyRows as $keyRow) {
            $available += (int) ($keyRow['quantity'] ?? 0);
        }

        if ($available < $quantity) {
            return false;
        }

        $remaining = $quantity;
        foreach ($keyRows as $keyRow) {
            if ($remaining <= 0) {
                break;
            }

            $rowId = (int) ($keyRow['id'] ?? 0);
            $rowQuantity = max(0, (int) ($keyRow['quantity'] ?? 0));
            if ($rowId <= 0 || $rowQuantity <= 0) {
                continue;
            }

            if ($rowQuantity <= $remaining) {
                $this->deleteById($rowId, $userId);
                $remaining -= $rowQuantity;
                continue;
            }

            $stmtUpdate = Database::pdo()->prepare(
                'UPDATE user_inventory
                 SET quantity = quantity - :quantity,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id
                   AND user_id = :user_id'
            );
            $stmtUpdate->execute([
                'id' => $rowId,
                'user_id' => $userId,
                'quantity' => $remaining,
            ]);
            $remaining = 0;
        }

        return true;
    }

    public function findByUserAndId(int $userId, int $inventoryId, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM user_inventory WHERE user_id = :user_id AND id = :id LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'id' => $inventoryId,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function markUnlocked(int $userId, int $inventoryId): void
    {
        $stmt = Database::pdo()->prepare('UPDATE user_inventory SET is_unlocked = 1, updated_at = CURRENT_TIMESTAMP WHERE user_id = :user_id AND id = :id LIMIT 1');
        $stmt->execute([
            'user_id' => $userId,
            'id' => $inventoryId,
        ]);
    }

    public function reduceQuantity(int $userId, int $inventoryId, int $quantity): void
    {
        $quantity = max(1, $quantity);
        $stmt = Database::pdo()->prepare('UPDATE user_inventory SET quantity = quantity - :quantity, updated_at = CURRENT_TIMESTAMP WHERE user_id = :user_id AND id = :id');
        $stmt->execute([
            'user_id' => $userId,
            'id' => $inventoryId,
            'quantity' => $quantity,
        ]);
    }

    public function deleteById(int $inventoryId, int $userId): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM user_inventory WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmt->execute([
            'id' => $inventoryId,
            'user_id' => $userId,
        ]);
    }

    public function removeItem(int $inventoryId, int $userId): void
    {
        $this->deleteById($inventoryId, $userId);
    }

    private function findMergeableOpenItem(int $userId, string $itemName, string $itemType, string $description): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM user_inventory
             WHERE user_id = :user_id
               AND item_name = :item_name
               AND item_type = :item_type
               AND COALESCE(description, "") = :description
               AND unlock_cost = 0
               AND (locked_content IS NULL OR locked_content = "")
               AND is_unlocked = 1
             LIMIT 1'
        );

        $stmt->execute([
            'user_id' => $userId,
            'item_name' => $itemName,
            'item_type' => $itemType,
            'description' => $description,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }
}
