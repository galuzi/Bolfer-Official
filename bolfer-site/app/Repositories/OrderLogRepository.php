<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
final class OrderLogRepository
{
    public function create(array $data): void
    {
        $stmt = Database::pdo()->prepare('INSERT INTO order_logs (order_id, admin_id, action, message) VALUES (:order_id, :admin_id, :action, :message)');
        $stmt->execute([
            'order_id' => (int) $data['order_id'],
            'admin_id' => $data['admin_id'] ? (int) $data['admin_id'] : null,
            'action' => $data['action'],
            'message' => $data['message'],
        ]);
    }

    public function listByOrder(int $orderId): array
    {
        $stmt = Database::pdo()->prepare('SELECT l.*, a.username AS admin_username FROM order_logs l LEFT JOIN admins a ON a.id = l.admin_id WHERE l.order_id = :order_id ORDER BY l.created_at DESC');
        $stmt->execute(['order_id' => $orderId]);
        return $stmt->fetchAll();
    }
}
