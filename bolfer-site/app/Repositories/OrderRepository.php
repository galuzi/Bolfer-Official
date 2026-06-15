<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;

final class OrderRepository
{
    public function create(array $data): array
    {
        $publicId = $data['public_id'] ?? $this->generatePublicId();
        $useUserId = $this->ordersHaveUserId();

        if ($useUserId) {
            $stmt = Database::pdo()->prepare('INSERT INTO orders (public_id, product_id, user_id, unit_price_snapshot, quantity, total_amount_snapshot, status, contact_channel, contact_value, in_game_nick, in_game_server, delivery_notes) VALUES (:public_id, :product_id, :user_id, :unit_price_snapshot, :quantity, :total_amount_snapshot, :status, :contact_channel, :contact_value, :in_game_nick, :in_game_server, :delivery_notes)');
            $stmt->execute([
                'public_id' => $publicId,
                'product_id' => (int) $data['product_id'],
                'user_id' => !empty($data['user_id']) ? (int) $data['user_id'] : null,
                'unit_price_snapshot' => $data['unit_price_snapshot'],
                'quantity' => (int) $data['quantity'],
                'total_amount_snapshot' => $data['total_amount_snapshot'],
                'status' => $data['status'] ?? 'created',
                'contact_channel' => $data['contact_channel'] ?? null,
                'contact_value' => $data['contact_value'] ?? null,
                'in_game_nick' => $data['in_game_nick'],
                'in_game_server' => $data['in_game_server'] ?? 'LDMO Omegamon',
                'delivery_notes' => $data['delivery_notes'] ?? null,
            ]);
        } else {
            $stmt = Database::pdo()->prepare('INSERT INTO orders (public_id, product_id, unit_price_snapshot, quantity, total_amount_snapshot, status, contact_channel, contact_value, in_game_nick, in_game_server, delivery_notes) VALUES (:public_id, :product_id, :unit_price_snapshot, :quantity, :total_amount_snapshot, :status, :contact_channel, :contact_value, :in_game_nick, :in_game_server, :delivery_notes)');
            $stmt->execute([
                'public_id' => $publicId,
                'product_id' => (int) $data['product_id'],
                'unit_price_snapshot' => $data['unit_price_snapshot'],
                'quantity' => (int) $data['quantity'],
                'total_amount_snapshot' => $data['total_amount_snapshot'],
                'status' => $data['status'] ?? 'created',
                'contact_channel' => $data['contact_channel'] ?? null,
                'contact_value' => $data['contact_value'] ?? null,
                'in_game_nick' => $data['in_game_nick'],
                'in_game_server' => $data['in_game_server'] ?? 'LDMO Omegamon',
                'delivery_notes' => $data['delivery_notes'] ?? null,
            ]);
        }

        return $this->findByPublicId($publicId);
    }

    public function findByPublicId(string $publicId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT o.*, p.name AS product_name FROM orders o JOIN products p ON p.id = o.product_id WHERE o.public_id = :public_id LIMIT 1');
        $stmt->execute(['public_id' => $publicId]);
        $order = $stmt->fetch();
        return $order ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT o.*, p.name AS product_name FROM orders o JOIN products p ON p.id = o.product_id WHERE o.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $order = $stmt->fetch();
        return $order ?: null;
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = Database::pdo()->prepare('UPDATE orders SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public function updateNotes(int $id, ?string $notes): void
    {
        $stmt = Database::pdo()->prepare('UPDATE orders SET delivery_notes = :notes WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'notes' => $notes,
        ]);
    }

    public function updateContact(string $publicId, array $data): void
    {
        $stmt = Database::pdo()->prepare('UPDATE orders SET contact_channel = :contact_channel, contact_value = :contact_value, delivery_notes = :delivery_notes, in_game_nick = :in_game_nick WHERE public_id = :public_id');
        $stmt->execute([
            'public_id' => $publicId,
            'contact_channel' => $data['contact_channel'] ?? null,
            'contact_value' => $data['contact_value'] ?? null,
            'delivery_notes' => $data['delivery_notes'] ?? null,
            'in_game_nick' => $data['in_game_nick'] ?? '',
        ]);
    }

    public function list(array $filters = []): array
    {
        $sql = 'SELECT o.*, p.name AS product_name FROM orders o JOIN products p ON p.id = o.product_id WHERE 1=1';
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= ' AND o.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['product_id'])) {
            $sql .= ' AND o.product_id = :product_id';
            $params['product_id'] = (int) $filters['product_id'];
        }

        if (!empty($filters['public_id'])) {
            $sql .= ' AND o.public_id = :public_id';
            $params['public_id'] = $filters['public_id'];
        }

        $sql .= ' ORDER BY o.created_at DESC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countByStatus(string $status): int
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) AS total FROM orders WHERE status = :status');
        $stmt->execute(['status' => $status]);
        return (int) $stmt->fetchColumn();
    }

    public function truncateAll(): void
    {
        $pdo = Database::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $pdo->exec('TRUNCATE TABLE order_logs');
        $pdo->exec('TRUNCATE TABLE payments');
        $pdo->exec('TRUNCATE TABLE orders');
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    private function generatePublicId(): string
    {
        return strtoupper(bin2hex(random_bytes(16)));
    }

    private function ordersHaveUserId(): bool
    {
        static $resolved = null;
        if ($resolved !== null) {
            return $resolved;
        }

        $stmt = Database::pdo()->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'user_id'");
        $resolved = ((int) $stmt->fetchColumn()) > 0;

        return $resolved;
    }
}