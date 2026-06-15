<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\UserInventoryRepository;

final class DesktopApiPresenter
{
    public static function admin(array $admin): array
    {
        return [
            'id' => (int) ($admin['id'] ?? 0),
            'username' => (string) ($admin['username'] ?? ''),
            'role' => (string) ($admin['role'] ?? 'staff'),
            'discordActivityEnabled' => !empty($admin['discord_activity_enabled']),
            'discordActivityDisplayName' => $admin['discord_activity_display_name'] ?? null,
            'twoFactorEnabled' => !empty($admin['two_factor_enabled']),
            'twoFactorConfirmedAt' => $admin['two_factor_confirmed_at'] ?? null,
            'lastLoginAt' => $admin['last_login_at'] ?? null,
        ];
    }

    public static function permissions(array $admin): array
    {
        $role = (string) ($admin['role'] ?? 'staff');
        $roleLevel = \admin_role_level($role);
        $isFounder = $role === 'founder';
        $isAdmin = $roleLevel >= 30;
        $canManageOrders = $roleLevel >= 20;
        $canModerateUsers = $roleLevel >= 20;
        $canAdjustCoins = $roleLevel >= 30;
        $canViewLogs = $roleLevel >= 30;
        $canManageSettings = $roleLevel >= 30;

        return [
            'roleLevel' => $roleLevel,
            'isFounder' => $isFounder,
            'manageOrders' => $isFounder || $canManageOrders,
            'manageUsers' => $isFounder || $canModerateUsers,
            'manageProducts' => $isFounder,
            'viewLogs' => $isFounder || $canViewLogs,
            'publishDiscordActivity' => $roleLevel >= 20,
            'manageSettings' => $isFounder || $canManageSettings,
            'manageInvites' => $isFounder,
            'manageAdmins' => $isFounder,
            'updateOrderStatus' => $isFounder || $canManageOrders,
            'addOrderNote' => $isFounder || $canManageOrders,
            'moderateUsers' => $isFounder || $canModerateUsers,
            'adjustCoins' => $isFounder || $canAdjustCoins,
            'manageOrderOwnership' => $isFounder || $canManageOrders,
            'resolveOrderConflicts' => $isFounder || $canManageOrders,
            'viewSensitiveInfo' => $isFounder || $isAdmin,
            'saveSettings' => $isFounder || $canManageSettings,
        ];
    }

    public static function category(array $category): array
    {
        return [
            'id' => (int) ($category['id'] ?? 0),
            'name' => (string) ($category['name'] ?? ''),
            'slug' => (string) ($category['slug'] ?? ''),
            'isActive' => !empty($category['is_active']),
            'sortOrder' => (int) ($category['sort_order'] ?? 0),
        ];
    }

    public static function product(array $product): array
    {
        $images = [];
        if (!empty($product['account_images'])) {
            $decoded = json_decode((string) $product['account_images'], true);
            if (is_array($decoded)) {
                $images = array_values(array_filter($decoded, static fn($value): bool => is_string($value) && trim($value) !== ''));
            }
        }

        return [
            'id' => (int) ($product['id'] ?? 0),
            'categoryId' => (int) ($product['category_id'] ?? 0),
            'categoryName' => (string) ($product['category_name'] ?? ''),
            'name' => (string) ($product['name'] ?? ''),
            'slug' => (string) ($product['slug'] ?? ''),
            'unitPrice' => (float) ($product['unit_price'] ?? 0),
            'stock' => $product['stock'] === null ? null : (int) ($product['stock'] ?? 0),
            'minimumQuantity' => max(1, (int) ($product['minimum_quantity'] ?? 1)),
            'serverLabel' => (string) ($product['server_label'] ?? 'LDMO Omegamon'),
            'deliveryEta' => (string) ($product['delivery_eta'] ?? '5min-1h'),
            'deliveryMethod' => $product['delivery_method'] ?? null,
            'productType' => (string) ($product['product_type'] ?? 'item'),
            'productDescription' => (string) ($product['product_description'] ?? ''),
            'accountInfo' => (string) ($product['account_info'] ?? ''),
            'accountImages' => $images,
            'description' => (string) ($product['description'] ?? ''),
            'notes' => (string) ($product['notes'] ?? ''),
            'isActive' => !empty($product['is_active']),
            'createdAt' => $product['created_at'] ?? null,
            'updatedAt' => $product['updated_at'] ?? null,
        ];
    }

    public static function invite(array $invite): array
    {
        $inviteKey = (string) ($invite['invite_key'] ?? '');
        $usedByAdminId = !empty($invite['used_by_admin_id']) ? (int) $invite['used_by_admin_id'] : null;

        return [
            'id' => (int) ($invite['id'] ?? 0),
            'inviteKey' => $inviteKey,
            'status' => $usedByAdminId ? 'used' : 'available',
            'targetRole' => 'staff',
            'registrationUrl' => $inviteKey !== '' ? \url('/admin/register?invite=' . urlencode($inviteKey)) : '',
            'createdByUsername' => $invite['created_by_email'] ?? null,
            'usedByUsername' => $invite['used_by_email'] ?? null,
            'createdAt' => $invite['created_at'] ?? null,
            'usedAt' => $invite['used_at'] ?? null,
        ];
    }

    public static function order(array $order): array
    {
        return [
            'id' => (int) ($order['id'] ?? 0),
            'publicId' => (string) ($order['public_id'] ?? ''),
            'productId' => (int) ($order['product_id'] ?? 0),
            'productName' => (string) ($order['product_name'] ?? ''),
            'quantity' => (int) ($order['quantity'] ?? 0),
            'status' => (string) ($order['status'] ?? 'created'),
            'contactChannel' => $order['contact_channel'] ?? null,
            'contactValue' => $order['contact_value'] ?? null,
            'inGameNick' => (string) ($order['in_game_nick'] ?? ''),
            'inGameServer' => (string) ($order['in_game_server'] ?? ''),
            'deliveryNotes' => $order['delivery_notes'] ?? null,
            'unitPrice' => (float) ($order['unit_price_snapshot'] ?? 0),
            'totalAmount' => (float) ($order['total_amount_snapshot'] ?? 0),
            'createdAt' => $order['created_at'] ?? null,
            'updatedAt' => $order['updated_at'] ?? null,
        ];
    }

    public static function orderLog(array $log): array
    {
        return [
            'id' => (int) ($log['id'] ?? 0),
            'orderId' => (int) ($log['order_id'] ?? 0),
            'adminId' => !empty($log['admin_id']) ? (int) $log['admin_id'] : null,
            'adminUsername' => $log['admin_username'] ?? null,
            'action' => (string) ($log['action'] ?? ''),
            'message' => (string) ($log['message'] ?? ''),
            'createdAt' => $log['created_at'] ?? null,
        ];
    }

    public static function user(array $user, array $inventorySummary = [], ?int $coinBalance = null): array
    {
        $summary = array_merge([
            'entries' => 0,
            'units' => 0,
            'types' => 0,
            'locked' => 0,
        ], $inventorySummary);

        return [
            'id' => (int) ($user['id'] ?? 0),
            'username' => (string) ($user['username'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'role' => (string) ($user['role'] ?? 'user'),
            'isBanned' => !empty($user['is_banned']),
            'bannedReason' => $user['banned_reason'] ?? null,
            'bannedAt' => $user['banned_at'] ?? null,
            'bannedByUsername' => $user['banned_by_username'] ?? null,
            'marketCoins' => $coinBalance ?? (int) ($user['market_coins'] ?? 0),
            'lastLoginAt' => $user['last_login_at'] ?? null,
            'createdAt' => $user['created_at'] ?? null,
            'inventorySummary' => [
                'entries' => (int) ($summary['entries'] ?? 0),
                'units' => (int) ($summary['units'] ?? 0),
                'types' => (int) ($summary['types'] ?? 0),
                'locked' => (int) ($summary['locked'] ?? 0),
            ],
        ];
    }

    public static function inventoryItem(array $item): array
    {
        $itemType = (string) ($item['item_type'] ?? 'outro');
        $typeOptions = UserInventoryRepository::typeOptions();
        $lockedContent = trim((string) ($item['locked_content'] ?? ''));
        $usesKeyUnlock = UserInventoryRepository::usesKeyUnlock($itemType);

        return [
            'id' => (int) ($item['id'] ?? 0),
            'itemName' => (string) ($item['item_name'] ?? ''),
            'itemType' => $itemType,
            'itemTypeLabel' => $typeOptions[$itemType] ?? ucfirst($itemType),
            'quantity' => (int) ($item['quantity'] ?? 0),
            'description' => $item['description'] ?? null,
            'unlockCost' => (int) ($item['unlock_cost'] ?? 0),
            'unlockUnit' => $usesKeyUnlock ? 'keys' : 'coins',
            'unlockLabel' => $usesKeyUnlock ? 'chaves' : 'coins',
            'isUnlocked' => !empty($item['is_unlocked']),
            'hasLockedContent' => $lockedContent !== '',
            'createdAt' => $item['created_at'] ?? null,
            'updatedAt' => $item['updated_at'] ?? null,
        ];
    }

    public static function marketTransaction(array $transaction): array
    {
        return [
            'id' => (int) ($transaction['id'] ?? 0),
            'amount' => (int) ($transaction['amount'] ?? 0),
            'transactionType' => (string) ($transaction['transaction_type'] ?? ''),
            'note' => $transaction['note'] ?? null,
            'adminUsername' => $transaction['admin_username'] ?? null,
            'createdAt' => $transaction['created_at'] ?? null,
        ];
    }

    public static function discordActivity(array $log): array
    {
        return [
            'id' => (int) ($log['id'] ?? 0),
            'adminId' => !empty($log['admin_id']) ? (int) $log['admin_id'] : null,
            'adminUsername' => $log['admin_username'] ?? null,
            'activityType' => (string) ($log['activity_type'] ?? 'manual'),
            'activityScope' => (string) ($log['activity_scope'] ?? 'admin'),
            'title' => (string) ($log['title'] ?? ''),
            'description' => (string) ($log['description'] ?? ''),
            'status' => (string) ($log['status'] ?? 'skipped'),
            'isManual' => !empty($log['is_manual']),
            'errorMessage' => $log['error_message'] ?? null,
            'fields' => self::decodeJsonList($log['fields_json'] ?? null),
            'createdAt' => $log['created_at'] ?? null,
            'sentAt' => $log['sent_at'] ?? null,
        ];
    }

    public static function banLog(array $entry): array
    {
        return [
            'id' => (int) ($entry['id'] ?? 0),
            'targetUsername' => (string) ($entry['target_username'] ?? 'Conta removida'),
            'targetEmail' => (string) ($entry['target_email'] ?? '-'),
            'status' => (string) ($entry['status'] ?? 'active'),
            'reason' => (string) ($entry['reason'] ?? ''),
            'note' => $entry['note'] ?? null,
            'severity' => (string) ($entry['severity'] ?? 'manual'),
            'ipAddress' => $entry['ip_address'] ?? null,
            'createdByUsername' => $entry['created_by_username'] ?? null,
            'revokedByUsername' => $entry['revoked_by_username'] ?? null,
            'createdAt' => $entry['created_at'] ?? null,
            'revokedAt' => $entry['revoked_at'] ?? null,
        ];
    }

    public static function banAttempt(array $entry): array
    {
        return [
            'id' => (int) ($entry['id'] ?? 0),
            'action' => (string) ($entry['action'] ?? ''),
            'note' => $entry['note'] ?? null,
            'loginInput' => $entry['login_input'] ?? null,
            'usernameInput' => $entry['username_input'] ?? null,
            'emailInput' => $entry['email_input'] ?? null,
            'ipAddress' => $entry['ip_address'] ?? null,
            'matchedUsername' => $entry['matched_username'] ?? null,
            'matchedEmail' => $entry['matched_email'] ?? null,
            'matchedBanReason' => $entry['matched_ban_reason'] ?? null,
            'createdAt' => $entry['created_at'] ?? null,
        ];
    }

    public static function marketLog(array $entry): array
    {
        $itemType = (string) ($entry['item_type_snapshot'] ?? 'outro');
        $usesKeyUnlock = UserInventoryRepository::usesKeyUnlock($itemType);

        return [
            'id' => (int) ($entry['id'] ?? 0),
            'eventType' => (string) ($entry['event_type'] ?? ''),
            'listingId' => !empty($entry['listing_id']) ? (int) $entry['listing_id'] : null,
            'inventoryId' => !empty($entry['inventory_id']) ? (int) $entry['inventory_id'] : null,
            'topupId' => !empty($entry['topup_id']) ? (int) $entry['topup_id'] : null,
            'orderId' => !empty($entry['order_id']) ? (int) $entry['order_id'] : null,
            'itemName' => $entry['item_name_snapshot'] ?? null,
            'itemType' => $entry['item_type_snapshot'] ?? null,
            'quantity' => (int) ($entry['quantity'] ?? 0),
            'priceCoins' => array_key_exists('price_coins', $entry) && $entry['price_coins'] !== null ? (int) $entry['price_coins'] : null,
            'coinsAmount' => array_key_exists('coins_amount', $entry) && $entry['coins_amount'] !== null ? (int) $entry['coins_amount'] : null,
            'unlockCost' => array_key_exists('unlock_cost', $entry) && $entry['unlock_cost'] !== null ? (int) $entry['unlock_cost'] : null,
            'unlockUnit' => $usesKeyUnlock ? 'keys' : 'coins',
            'unlockLabel' => $usesKeyUnlock ? 'chaves' : 'coins',
            'amountBrl' => array_key_exists('amount_brl', $entry) && $entry['amount_brl'] !== null ? (float) $entry['amount_brl'] : null,
            'itemLockState' => (string) ($entry['item_lock_state'] ?? 'open'),
            'note' => $entry['note'] ?? null,
            'actorUsername' => $entry['actor_username'] ?? null,
            'sellerUsername' => $entry['seller_username'] ?? null,
            'buyerUsername' => $entry['buyer_username'] ?? null,
            'targetUsername' => $entry['target_username'] ?? null,
            'adminUsername' => $entry['admin_username'] ?? null,
            'createdAt' => $entry['created_at'] ?? null,
        ];
    }

    public static function accessLog(array $entry): array
    {
        return [
            'id' => (int) ($entry['id'] ?? 0),
            'targetUsername' => (string) ($entry['target_username'] ?? 'Conta removida'),
            'targetEmail' => (string) ($entry['target_email'] ?? '-'),
            'action' => (string) ($entry['action'] ?? ''),
            'route' => $entry['route'] ?? null,
            'ipAddress' => $entry['ip_address'] ?? null,
            'userAgent' => $entry['user_agent'] ?? null,
            'createdAt' => $entry['created_at'] ?? null,
        ];
    }

    public static function accessIpSummary(array $entry): array
    {
        return [
            'userId' => !empty($entry['user_id']) ? (int) $entry['user_id'] : null,
            'targetUsername' => (string) ($entry['target_username'] ?? 'Conta removida'),
            'targetEmail' => (string) ($entry['target_email'] ?? '-'),
            'ipAddress' => (string) ($entry['ip_address'] ?? ''),
            'totalHits' => (int) ($entry['total_hits'] ?? 0),
            'firstSeenAt' => $entry['first_seen_at'] ?? null,
            'lastSeenAt' => $entry['last_seen_at'] ?? null,
        ];
    }

    private static function decodeJsonList(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
