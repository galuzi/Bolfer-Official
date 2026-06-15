<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\OrderLogRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\ProductRepository;
use App\Services\DiscordActivityService;
use App\Services\PaymentService;
use App\Support\View;
final class AdminOrderController
{
    public function index(): void
    {
        require_admin();

        $orderRepo = new OrderRepository();
        $logRepo = new OrderLogRepository();
        $discordService = new DiscordActivityService();
        $statuses = [
            'created',
            'pending_payment',
            'paid_waiting_contact',
            'in_delivery',
            'delivered',
            'cancelled',
            'rejected',
        ];
        $statusLabels = [
            'created' => 'Criado',
            'pending_payment' => 'Aguardando pagamento',
            'paid_waiting_contact' => 'Pago aguardando contato',
            'in_delivery' => 'Em entrega',
            'delivered' => 'Entregue',
            'cancelled' => 'Cancelado',
            'rejected' => 'Recusado',
        ];

        $clearFilters = isset($_GET['clear']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? 'update_status';
            if ($action === 'delete_all') {
                $orderRepo->truncateAll();
                flash_set('success', 'Todos os pedidos foram apagados.');
                redirect('/admin/orders');
            }

            $orderId = (int) ($_POST['order_id'] ?? 0);
            $order = $orderRepo->findById($orderId);

            if (!$order) {
                flash_set('error', 'Pedido nao encontrado.');
                redirect('/admin/orders');
            }

            if ($action === 'update_status') {
                $newStatus = $_POST['status'] ?? '';
                if ($newStatus === 'in_delivery' && trim((string) $order['in_game_nick']) === '') {
                    flash_set('error', 'Nick obrigatorio para iniciar entrega.');
                    redirect('/admin/orders');
                }

                $orderRepo->updateStatus($orderId, $newStatus);
                $paidStatuses = ['paid_waiting_contact', 'in_delivery', 'delivered'];
                $shouldDecrement = in_array($newStatus, $paidStatuses, true)
                    && !in_array((string) $order['status'], $paidStatuses, true);
                $logMessage = 'Status atualizado para ' . $newStatus;

                if ($shouldDecrement) {
                    $productRepo = new ProductRepository();
                    $product = $productRepo->find((int) $order['product_id']);
                    if ($product && $product['stock'] !== null) {
                        $productRepo->decrementStock((int) $product['id'], (int) $order['quantity']);
                        $logMessage .= ' | Estoque -' . (int) $order['quantity'];
                    }
                }

                $logRepo->create([
                    'order_id' => $orderId,
                    'admin_id' => admin_user()['id'] ?? null,
                    'action' => 'status_change',
                    'message' => $logMessage,
                ]);
                $product = (new ProductRepository())->find((int) ($order['product_id'] ?? 0));
                $discordService->notify(
                    admin_user() ?? [],
                    'order_status',
                    'orders',
                    'Pedido ' . (string) ($order['public_id'] ?? '#' . $orderId) . ' atualizado',
                    'O status do pedido foi alterado no painel administrativo.',
                    [
                        ['name' => 'Produto', 'value' => (string) ($product['name'] ?? ('#' . (int) ($order['product_id'] ?? 0))), 'inline' => true],
                        ['name' => 'Novo status', 'value' => (string) ($statusLabels[$newStatus] ?? $newStatus), 'inline' => true],
                        ['name' => 'Quantidade', 'value' => (string) ((int) ($order['quantity'] ?? 1)), 'inline' => true],
                    ]
                );

                flash_set('success', 'Status atualizado.');
                redirect('/admin/orders');
            }

            if ($action === 'refund_payment') {
                if (in_array((string) $order['status'], ['cancelled', 'rejected'], true)) {
                    flash_set('error', 'Pedido ja esta cancelado.');
                    redirect('/admin/orders');
                }

                $allowedRefundStatuses = ['paid_waiting_contact', 'in_delivery', 'delivered'];
                if (!in_array((string) $order['status'], $allowedRefundStatuses, true)) {
                    flash_set('error', 'Reembolso disponivel apenas para pedidos pagos.');
                    redirect('/admin/orders');
                }

                $paymentRepo = new PaymentRepository();
                $payment = $paymentRepo->findLatestByOrderId($orderId);
                if (!$payment || empty($payment['mp_payment_id'])) {
                    flash_set('error', 'Pagamento do Mercado Pago nao encontrado para este pedido.');
                    redirect('/admin/orders');
                }

                try {
                    $refund = (new PaymentService())->refundPayment((string) $payment['mp_payment_id']);
                } catch (\RuntimeException $e) {
                    flash_set('error', 'Reembolso nao realizado: ' . $e->getMessage());
                    redirect('/admin/orders');
                }

                $orderRepo->updateStatus($orderId, 'cancelled');
                $paymentRepo->updateStatusByMpPaymentId((string) $payment['mp_payment_id'], 'refunded');

                $refundId = $refund['id'] ?? null;
                $logMessage = 'Reembolso solicitado no Mercado Pago';
                if ($refundId) {
                    $logMessage .= ' (refund_id ' . $refundId . ')';
                }

                $logRepo->create([
                    'order_id' => $orderId,
                    'admin_id' => admin_user()['id'] ?? null,
                    'action' => 'status_change',
                    'message' => $logMessage,
                ]);
                $discordService->notify(
                    admin_user() ?? [],
                    'order_refund',
                    'orders',
                    'Pedido ' . (string) ($order['public_id'] ?? '#' . $orderId) . ' reembolsado',
                    'Um reembolso foi solicitado no Mercado Pago pelo painel administrativo.',
                    [
                        ['name' => 'Pedido', 'value' => (string) ($order['public_id'] ?? '#' . $orderId), 'inline' => true],
                        ['name' => 'Status final', 'value' => 'Cancelado', 'inline' => true],
                        ['name' => 'Valor', 'value' => 'R$ ' . number_format((float) ($order['total_amount_snapshot'] ?? 0), 2, ',', '.'), 'inline' => true],
                    ]
                );

                flash_set('success', 'Reembolso solicitado.');
                redirect('/admin/orders?view=' . $orderId);
            }

            if ($action === 'add_note') {
                $note = trim((string) ($_POST['note'] ?? ''));
                if ($note !== '') {
                    $logRepo->create([
                        'order_id' => $orderId,
                        'admin_id' => admin_user()['id'] ?? null,
                        'action' => 'note_added',
                        'message' => $note,
                    ]);
                    $orderRepo->updateNotes($orderId, $note);
                    $discordService->notify(
                        admin_user() ?? [],
                        'order_note',
                        'orders',
                        'Pedido ' . (string) ($order['public_id'] ?? '#' . $orderId) . ' recebeu atualizacao',
                        'A equipe registrou uma nova observacao ou retorno no fluxo do pedido.',
                        [
                            ['name' => 'Pedido', 'value' => (string) ($order['public_id'] ?? '#' . $orderId), 'inline' => true],
                            ['name' => 'Resumo', 'value' => substr($note, 0, 200), 'inline' => false],
                        ]
                    );
                }
                flash_set('success', 'Nota adicionada.');
                redirect('/admin/orders');
            }
        }

        $filters = $clearFilters ? [
            'status' => null,
            'product_id' => null,
            'public_id' => null,
        ] : [
            'status' => $_GET['status'] ?? null,
            'product_id' => $_GET['product_id'] ?? null,
            'public_id' => $_GET['public_id'] ?? null,
        ];

        $orders = $orderRepo->list($filters);
        $products = (new ProductRepository())->all();
        $viewOrderId = (int) ($_GET['view'] ?? 0);
        $logs = $viewOrderId ? $logRepo->listByOrder($viewOrderId) : [];
        $viewOrder = $viewOrderId ? $orderRepo->findById($viewOrderId) : null;

        View::render('admin/orders', [
            'orders' => $orders,
            'products' => $products,
            'viewOrderId' => $viewOrderId,
            'logs' => $logs,
            'viewOrder' => $viewOrder,
            'filters' => $filters,
            'statuses' => $statuses,
            'statusLabels' => $statusLabels,
        ]);
    }
}
