<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;

final class PaymentRepository
{
    public function findByMpPaymentId(string $mpPaymentId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM payments WHERE mp_payment_id = :mp_payment_id LIMIT 1');
        $stmt->execute(['mp_payment_id' => $mpPaymentId]);
        $payment = $stmt->fetch();

        return $payment ?: null;
    }

    public function create(array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO payments (order_id, provider, mp_payment_id, status, raw_payload)
             VALUES (:order_id, :provider, :mp_payment_id, :status, :raw_payload)'
        );
        $stmt->execute([
            'order_id' => (int) $data['order_id'],
            'provider' => $data['provider'] ?? 'mercadopago',
            'mp_payment_id' => $data['mp_payment_id'] ?? null,
            'status' => $data['status'],
            'raw_payload' => $data['raw_payload'],
        ]);
    }

    public function findLatestByOrderId(int $orderId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM payments WHERE order_id = :order_id ORDER BY created_at DESC, id DESC LIMIT 1');
        $stmt->execute(['order_id' => $orderId]);
        $payment = $stmt->fetch();

        return $payment ?: null;
    }

    public function updateByMpPaymentId(string $mpPaymentId, string $status, string $rawPayload): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE payments
             SET status = :status,
                 raw_payload = :raw_payload
             WHERE mp_payment_id = :mp_payment_id'
        );
        $stmt->execute([
            'status' => $status,
            'raw_payload' => $rawPayload,
            'mp_payment_id' => $mpPaymentId,
        ]);
    }

    public function updateStatusByMpPaymentId(string $mpPaymentId, string $status): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE payments
             SET status = :status
             WHERE mp_payment_id = :mp_payment_id'
        );
        $stmt->execute([
            'status' => $status,
            'mp_payment_id' => $mpPaymentId,
        ]);
    }
}
