<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;

final class UserMarketLogRepository
{
    private const EVENT_LABELS = [
        'admin_coin_adjust' => 'Ajuste manual de coins',
        'inventory_unlocked' => 'Item desbloqueado',
        'listing_cancelled' => 'Oferta cancelada',
        'listing_created' => 'Oferta publicada',
        'listing_sold' => 'Oferta vendida',
        'topup_created' => 'Recarga criada',
        'topup_paid' => 'Recarga aprovada',
    ];

    public static function eventLabels(): array
    {
        return self::EVENT_LABELS;
    }

    public function create(array $data): int
    {
        $itemLockState = trim((string) ($data['item_lock_state'] ?? 'open'));
        if (!in_array($itemLockState, ['open', 'locked'], true)) {
            $itemLockState = 'open';
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO user_market_logs (
                event_type,
                actor_user_id,
                seller_user_id,
                buyer_user_id,
                target_user_id,
                admin_id,
                listing_id,
                inventory_id,
                topup_id,
                order_id,
                item_name_snapshot,
                item_type_snapshot,
                quantity,
                price_coins,
                coins_amount,
                unlock_cost,
                amount_brl,
                item_lock_state,
                note
            ) VALUES (
                :event_type,
                :actor_user_id,
                :seller_user_id,
                :buyer_user_id,
                :target_user_id,
                :admin_id,
                :listing_id,
                :inventory_id,
                :topup_id,
                :order_id,
                :item_name_snapshot,
                :item_type_snapshot,
                :quantity,
                :price_coins,
                :coins_amount,
                :unlock_cost,
                :amount_brl,
                :item_lock_state,
                :note
            )'
        );

        $stmt->execute([
            'event_type' => trim((string) ($data['event_type'] ?? 'unknown')),
            'actor_user_id' => !empty($data['actor_user_id']) ? (int) $data['actor_user_id'] : null,
            'seller_user_id' => !empty($data['seller_user_id']) ? (int) $data['seller_user_id'] : null,
            'buyer_user_id' => !empty($data['buyer_user_id']) ? (int) $data['buyer_user_id'] : null,
            'target_user_id' => !empty($data['target_user_id']) ? (int) $data['target_user_id'] : null,
            'admin_id' => !empty($data['admin_id']) ? (int) $data['admin_id'] : null,
            'listing_id' => !empty($data['listing_id']) ? (int) $data['listing_id'] : null,
            'inventory_id' => !empty($data['inventory_id']) ? (int) $data['inventory_id'] : null,
            'topup_id' => !empty($data['topup_id']) ? (int) $data['topup_id'] : null,
            'order_id' => !empty($data['order_id']) ? (int) $data['order_id'] : null,
            'item_name_snapshot' => ($itemName = trim((string) ($data['item_name_snapshot'] ?? ''))) !== '' ? $itemName : null,
            'item_type_snapshot' => ($itemType = trim((string) ($data['item_type_snapshot'] ?? ''))) !== '' ? $itemType : null,
            'quantity' => max(1, (int) ($data['quantity'] ?? 1)),
            'price_coins' => array_key_exists('price_coins', $data) && $data['price_coins'] !== null ? max(0, (int) $data['price_coins']) : null,
            'coins_amount' => array_key_exists('coins_amount', $data) && $data['coins_amount'] !== null ? (int) $data['coins_amount'] : null,
            'unlock_cost' => array_key_exists('unlock_cost', $data) && $data['unlock_cost'] !== null ? max(0, (int) $data['unlock_cost']) : null,
            'amount_brl' => array_key_exists('amount_brl', $data) && $data['amount_brl'] !== null ? (float) $data['amount_brl'] : null,
            'item_lock_state' => $itemLockState,
            'note' => ($note = trim((string) ($data['note'] ?? ''))) !== '' ? $note : null,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }
}
