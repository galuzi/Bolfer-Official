<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\OrderRepository;
use App\Services\PaymentSyncService;
use App\Support\View;

final class StatusController
{
    private function getPublicId(): ?string
    {
        $publicId = trim((string) ($_GET['external_reference'] ?? $_GET['order'] ?? $_GET['public_id'] ?? ''));
        return $publicId !== '' ? $publicId : null;
    }

    private function getType(): ?string
    {
        $type = trim((string) ($_GET['type'] ?? ''));
        return $type !== '' ? $type : null;
    }

    private function syncReturnedPayment(): void
    {
        $paymentId = trim((string) ($_GET['payment_id'] ?? $_GET['collection_id'] ?? ''));
        if ($paymentId === '') {
            return;
        }

        try {
            (new PaymentSyncService())->syncByMpPaymentId($paymentId);
        } catch (\Throwable $e) {
        }
    }

    private function currentOrder(): ?array
    {
        $publicId = $this->getPublicId();
        if ($publicId === null) {
            return null;
        }

        return (new OrderRepository())->findByPublicId($publicId);
    }

    private function purchaseAnalyticsPayload(?array $order, ?string $type): ?array
    {
        if (!$order) {
            return null;
        }

        $paidStatuses = ['paid_waiting_contact', 'in_delivery', 'delivered'];
        if (!in_array((string) ($order['status'] ?? ''), $paidStatuses, true)) {
            return null;
        }

        $itemName = match ($type) {
            'donation' => 'Doacao Bolfer',
            'market_topup' => 'Bolfer Market Coins',
            default => (string) ($order['product_name'] ?? 'Produto Bolfer'),
        };

        return [
            'transaction_id' => (string) ($order['public_id'] ?? ''),
            'currency' => 'BRL',
            'value' => (float) ($order['total_amount_snapshot'] ?? 0),
            'items' => [
                [
                    'item_id' => (string) ($order['product_id'] ?? ''),
                    'item_name' => $itemName,
                    'price' => (float) ($order['unit_price_snapshot'] ?? 0),
                    'quantity' => (int) ($order['quantity'] ?? 1),
                ],
            ],
        ];
    }

    public function success(): void
    {
        $this->syncReturnedPayment();
        $type = $this->getType();
        $order = $this->currentOrder();
        $message = match ($type) {
            'donation' => 'Doacao aprovada. Obrigado pelo seu apoio!',
            'market_topup' => 'Pagamento aprovado. Suas coins serao creditadas automaticamente.',
            default => 'Seu pagamento foi aprovado. Aguarde as instrucoes no pedido.',
        };

        View::render('status', [
            'title' => 'Pagamento aprovado',
            'message' => $message,
            'publicId' => $this->getPublicId(),
            'gaPurchasePayload' => $this->purchaseAnalyticsPayload($order, $type),
        ]);
    }

    public function pending(): void
    {
        $this->syncReturnedPayment();
        $type = $this->getType();
        $message = match ($type) {
            'donation' => 'Sua doacao esta pendente. Assim que aprovar, confirmaremos o apoio.',
            'market_topup' => 'Sua compra de coins esta pendente. Assim que aprovar, a carteira sera atualizada.',
            default => 'Seu pagamento esta pendente. Assim que aprovar, o pedido sera atualizado.',
        };

        View::render('status', [
            'title' => 'Pagamento pendente',
            'message' => $message,
            'publicId' => $this->getPublicId(),
        ]);
    }

    public function failure(): void
    {
        $this->syncReturnedPayment();
        $type = $this->getType();
        $message = match ($type) {
            'donation' => 'Sua doacao nao foi aprovada. Tente novamente.',
            'market_topup' => 'A compra de coins nao foi aprovada. Tente novamente.',
            default => 'Seu pagamento nao foi aprovado. Tente novamente.',
        };

        View::render('status', [
            'title' => 'Pagamento nao aprovado',
            'message' => $message,
            'publicId' => $this->getPublicId(),
        ]);
    }
}
