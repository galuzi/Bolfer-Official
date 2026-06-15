<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\OrderLogRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\ProductRepository;
use App\Repositories\UserMarketTopupRepository;

final class PaymentSyncService
{
    public function syncByMpPaymentId(string $mpPaymentId): array
    {
        $payment = (new PaymentService())->fetchPayment($mpPaymentId);

        return $this->syncPaymentPayload($payment);
    }

    public function syncPaymentPayload(array $payment): array
    {
        $externalReference = trim((string) ($payment['external_reference'] ?? ''));
        if ($externalReference === '') {
            return [
                'ok' => false,
                'http_code' => 200,
                'message' => 'Sem referencia externa.',
            ];
        }

        $orderRepository = new OrderRepository();
        $order = $orderRepository->findByPublicId($externalReference);
        if (!$order) {
            return [
                'ok' => false,
                'http_code' => 200,
                'message' => 'Pedido nao encontrado.',
            ];
        }

        $paymentId = trim((string) ($payment['id'] ?? ''));
        if ($paymentId !== '') {
            $rawPayload = (string) json_encode($payment, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $paymentRepository = new PaymentRepository();
            $storedPayment = $paymentRepository->findByMpPaymentId($paymentId);

            if ($storedPayment) {
                $paymentRepository->updateByMpPaymentId($paymentId, (string) ($payment['status'] ?? ''), $rawPayload);
            } else {
                $paymentRepository->create([
                    'order_id' => $order['id'],
                    'provider' => 'mercadopago',
                    'mp_payment_id' => $paymentId,
                    'status' => (string) ($payment['status'] ?? ''),
                    'raw_payload' => $rawPayload,
                ]);
            }
        }

        $amount = (float) ($payment['transaction_amount'] ?? 0);
        $expected = (float) ($order['total_amount_snapshot'] ?? 0);
        if (round($amount, 2) < round($expected, 2)) {
            return [
                'ok' => false,
                'http_code' => 200,
                'order' => $order,
                'message' => 'Valor divergente.',
            ];
        }

        $status = (string) ($payment['status'] ?? '');
        if ($status !== 'approved') {
            return [
                'ok' => true,
                'http_code' => 200,
                'order' => $order,
                'approved' => false,
                'message' => 'Pagamento atualizado.',
            ];
        }

        $topup = (new UserMarketTopupRepository())->findByOrderId((int) $order['id']);
        if ($topup) {
            return $this->syncTopup($order);
        }

        $deliveryNotes = strtolower(trim((string) ($order['delivery_notes'] ?? '')));
        if ($deliveryNotes === 'doacao') {
            return $this->syncDonation($order);
        }

        return $this->syncProductOrder($order);
    }

    private function syncTopup(array $order): array
    {
        $alreadyDelivered = (string) ($order['status'] ?? '') === 'delivered';
        $result = (new UserMarketService())->creditPaidTopup((int) $order['id']);

        if (!$result['ok']) {
            return [
                'ok' => false,
                'http_code' => 200,
                'order' => $order,
                'message' => (string) ($result['message'] ?? 'Nao foi possivel creditar a recarga.'),
            ];
        }

        if (!$alreadyDelivered) {
            $orderRepository = new OrderRepository();
            $orderRepository->updateStatus((int) $order['id'], 'delivered');
            (new OrderLogRepository())->create([
                'order_id' => $order['id'],
                'admin_id' => null,
                'action' => 'webhook_update',
                'message' => 'Recarga de coins aprovada via confirmacao de pagamento.',
            ]);
        }

        return [
            'ok' => true,
            'http_code' => 200,
            'order' => $order,
            'approved' => true,
            'message' => 'OK',
        ];
    }

    private function syncDonation(array $order): array
    {
        if ((string) ($order['status'] ?? '') !== 'delivered') {
            $orderRepository = new OrderRepository();
            $orderRepository->updateStatus((int) $order['id'], 'delivered');
            (new OrderLogRepository())->create([
                'order_id' => $order['id'],
                'admin_id' => null,
                'action' => 'webhook_update',
                'message' => 'Doacao aprovada via confirmacao de pagamento.',
            ]);
        }

        return [
            'ok' => true,
            'http_code' => 200,
            'order' => $order,
            'approved' => true,
            'message' => 'OK',
        ];
    }

    private function syncProductOrder(array $order): array
    {
        $paidStatuses = ['paid_waiting_contact', 'in_delivery', 'delivered'];
        if (!in_array((string) ($order['status'] ?? ''), $paidStatuses, true)) {
            $orderRepository = new OrderRepository();
            $orderRepository->updateStatus((int) $order['id'], 'paid_waiting_contact');

            $productRepository = new ProductRepository();
            $product = $productRepository->find((int) ($order['product_id'] ?? 0));
            if ($product && $product['stock'] !== null) {
                $productRepository->decrementStock((int) $product['id'], (int) ($order['quantity'] ?? 1));
            }

            (new OrderLogRepository())->create([
                'order_id' => $order['id'],
                'admin_id' => null,
                'action' => 'webhook_update',
                'message' => 'Pagamento aprovado via confirmacao de pagamento.',
            ]);
        }

        return [
            'ok' => true,
            'http_code' => 200,
            'order' => $order,
            'approved' => true,
            'message' => 'OK',
        ];
    }
}
