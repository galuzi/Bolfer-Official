<?php

declare(strict_types=1);

namespace App\Controllers\Api\Desktop;

use App\Repositories\OrderLogRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use App\Services\DiscordActivityService;
use App\Support\DesktopApiPresenter;

final class OrdersController
{
    private const STATUS_LABELS = [
        'created' => 'Criado',
        'pending_payment' => 'Aguardando pagamento',
        'paid_waiting_contact' => 'Pago aguardando contato',
        'in_delivery' => 'Em entrega',
        'delivered' => 'Entregue',
        'cancelled' => 'Cancelado',
        'rejected' => 'Recusado',
    ];

    public function index(): void
    {
        require_admin_api();

        $filters = [
            'status' => $this->normalizeStatusFilter($_GET['status'] ?? null),
            'product_id' => $this->normalizeProductFilter($_GET['product_id'] ?? null),
            'public_id' => $this->normalizePublicIdFilter($_GET['public_id'] ?? null),
        ];

        $orders = (new OrderRepository())->list($filters);
        $products = (new ProductRepository())->all();

        json_response([
            'ok' => true,
            'data' => [
                'filters' => [
                    'status' => $filters['status'],
                    'productId' => $filters['product_id'],
                    'publicId' => $filters['public_id'],
                ],
                'statusLabels' => self::STATUS_LABELS,
                'products' => array_map(
                    static fn(array $product): array => [
                        'id' => (int) ($product['id'] ?? 0),
                        'name' => (string) ($product['name'] ?? ''),
                    ],
                    $products
                ),
                'orders' => array_map(
                    static fn(array $order): array => DesktopApiPresenter::order($order),
                    $orders
                ),
            ],
        ]);
    }

    public function show(string $id): void
    {
        require_admin_api();

        $orderId = (int) $id;
        if ($orderId <= 0) {
            $this->notFound();
        }

        $orderRepository = new OrderRepository();
        $order = $orderRepository->findById($orderId);
        if (!$order) {
            $this->notFound();
        }

        json_response([
            'ok' => true,
            'data' => [
                'order' => DesktopApiPresenter::order($order),
                'statusLabels' => self::STATUS_LABELS,
                'logs' => array_map(
                    static fn(array $log): array => DesktopApiPresenter::orderLog($log),
                    (new OrderLogRepository())->listByOrder($orderId)
                ),
            ],
        ]);
    }

    public function updateStatus(string $id): void
    {
        $context = require_admin_api();
        $orderId = (int) $id;
        if ($orderId <= 0) {
            $this->notFound();
        }

        $payload = request_data();
        $newStatus = trim((string) ($payload['status'] ?? ''));
        if (!array_key_exists($newStatus, self::STATUS_LABELS)) {
            json_response([
                'ok' => false,
                'message' => 'Status invalido.',
            ], 422);
        }

        $orderRepository = new OrderRepository();
        $orderLogRepository = new OrderLogRepository();
        $productRepository = new ProductRepository();
        $discordService = new DiscordActivityService();
        $order = $orderRepository->findById($orderId);

        if (!$order) {
            $this->notFound();
        }

        if ($newStatus === 'in_delivery' && trim((string) ($order['in_game_nick'] ?? '')) === '') {
            json_response([
                'ok' => false,
                'message' => 'Nick obrigatorio para iniciar entrega.',
            ], 422);
        }

        $orderRepository->updateStatus($orderId, $newStatus);
        $paidStatuses = ['paid_waiting_contact', 'in_delivery', 'delivered'];
        $previousStatus = (string) ($order['status'] ?? 'created');
        $shouldDecrement = in_array($newStatus, $paidStatuses, true)
            && !in_array($previousStatus, $paidStatuses, true);
        $logMessage = 'Status atualizado para ' . $newStatus;

        if ($shouldDecrement) {
            $product = $productRepository->find((int) ($order['product_id'] ?? 0));
            if ($product && $product['stock'] !== null) {
                $productRepository->decrementStock((int) ($product['id'] ?? 0), (int) ($order['quantity'] ?? 0));
                $logMessage .= ' | Estoque -' . (int) ($order['quantity'] ?? 0);
            }
        }

        $orderLogRepository->create([
            'order_id' => $orderId,
            'admin_id' => (int) ($context['admin']['id'] ?? 0),
            'action' => 'status_change',
            'message' => $logMessage,
        ]);

        $product = $productRepository->find((int) ($order['product_id'] ?? 0));
        $discordService->notify(
            $context['admin'] ?? [],
            'order_status',
            'orders',
            'Pedido ' . (string) ($order['public_id'] ?? '#' . $orderId) . ' atualizado',
            'O status do pedido foi alterado pelo app desktop.',
            [
                ['name' => 'Produto', 'value' => (string) ($product['name'] ?? ('#' . (int) ($order['product_id'] ?? 0))), 'inline' => true],
                ['name' => 'Novo status', 'value' => (string) (self::STATUS_LABELS[$newStatus] ?? $newStatus), 'inline' => true],
                ['name' => 'Quantidade', 'value' => (string) ((int) ($order['quantity'] ?? 1)), 'inline' => true],
            ]
        );

        $updatedOrder = $orderRepository->findById($orderId) ?? $order;

        json_response([
            'ok' => true,
            'message' => 'Status atualizado com sucesso.',
            'data' => [
                'order' => DesktopApiPresenter::order($updatedOrder),
            ],
        ]);
    }

    public function addNote(string $id): void
    {
        $context = require_admin_api();
        $orderId = (int) $id;
        if ($orderId <= 0) {
            $this->notFound();
        }

        $payload = request_data();
        $note = trim((string) ($payload['note'] ?? ''));

        if ($note === '') {
            json_response([
                'ok' => false,
                'message' => 'Informe uma nota para o pedido.',
            ], 422);
        }

        if (strlen($note) > 5000) {
            json_response([
                'ok' => false,
                'message' => 'A nota do pedido excede o limite de 5000 caracteres.',
            ], 422);
        }

        $orderRepository = new OrderRepository();
        $orderLogRepository = new OrderLogRepository();
        $discordService = new DiscordActivityService();
        $order = $orderRepository->findById($orderId);

        if (!$order) {
            $this->notFound();
        }

        $orderLogRepository->create([
            'order_id' => $orderId,
            'admin_id' => (int) ($context['admin']['id'] ?? 0),
            'action' => 'note_added',
            'message' => $note,
        ]);
        $orderRepository->updateNotes($orderId, $note);

        $discordService->notify(
            $context['admin'] ?? [],
            'order_note',
            'orders',
            'Pedido ' . (string) ($order['public_id'] ?? '#' . $orderId) . ' recebeu atualizacao',
            'A equipe registrou uma nova observacao pelo app desktop.',
            [
                ['name' => 'Pedido', 'value' => (string) ($order['public_id'] ?? '#' . $orderId), 'inline' => true],
                ['name' => 'Resumo', 'value' => substr($note, 0, 200), 'inline' => false],
            ]
        );

        $updatedOrder = $orderRepository->findById($orderId) ?? $order;

        json_response([
            'ok' => true,
            'message' => 'Nota registrada com sucesso.',
            'data' => [
                'order' => DesktopApiPresenter::order($updatedOrder),
                'logs' => array_map(
                    static fn(array $log): array => DesktopApiPresenter::orderLog($log),
                    $orderLogRepository->listByOrder($orderId)
                ),
            ],
        ]);
    }

    private function normalizeStatusFilter(mixed $value): ?string
    {
        $status = trim((string) $value);

        return array_key_exists($status, self::STATUS_LABELS) ? $status : null;
    }

    private function normalizeProductFilter(mixed $value): ?int
    {
        $productId = (int) $value;

        return $productId > 0 ? $productId : null;
    }

    private function normalizePublicIdFilter(mixed $value): ?string
    {
        $publicId = strtoupper(trim((string) $value));

        return $publicId !== '' ? $publicId : null;
    }

    private function notFound(): void
    {
        json_response([
            'ok' => false,
            'message' => 'Pedido nao encontrado.',
        ], 404);
    }
}
