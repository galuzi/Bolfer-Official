<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\OrderLogRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use App\Services\OrderPendingNotificationService;
use App\Services\PaymentService;
use App\Support\ClientContext;
use App\Support\RateLimiter;

final class CheckoutController
{
    public function create(): void
    {
        if (!user_session()) {
            flash_set('error', 'Faca login para comprar este produto.');
            redirect('/login');
        }

        $limiter = new RateLimiter();
        $rateKey = 'checkout-create:' . hash('sha256', (string) (ClientContext::summary()['ip_address'] ?? 'unknown'));
        $retryAfter = (int) ($limiter->isBlocked($rateKey) ?? 0);
        if ($retryAfter > 0) {
            http_response_code(429);
            echo 'Muitas tentativas de checkout. Aguarde alguns instantes e tente novamente.';
            return;
        }
        $limiter->hit($rateKey, 12, 300, 600);

        $productId = (int) ($_POST['product_id'] ?? 0);
        $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
        $nick = trim((string) ($_POST['in_game_nick'] ?? ''));
        $contactChannel = trim((string) ($_POST['contact_channel'] ?? ''));
        $contactValue = trim((string) ($_POST['contact_value'] ?? ''));
        $deliveryNotes = trim((string) ($_POST['delivery_notes'] ?? ''));

        if ($nick === '') {
            http_response_code(422);
            echo 'Nick obrigatorio.';
            return;
        }

        if (strlen($nick) > 60) {
            http_response_code(422);
            echo 'Nick muito longo.';
            return;
        }

        $allowedChannels = ['whatsapp', 'discord'];
        if ($contactChannel === '' || !in_array($contactChannel, $allowedChannels, true)) {
            http_response_code(422);
            echo 'Canal de contato obrigatorio.';
            return;
        }

        if ($contactValue === '') {
            http_response_code(422);
            echo 'Contato obrigatorio.';
            return;
        }

        if (strlen($contactValue) > 120) {
            http_response_code(422);
            echo 'Contato muito longo.';
            return;
        }

        if (strlen($deliveryNotes) > 1000) {
            http_response_code(422);
            echo 'Observacoes muito longas.';
            return;
        }

        $product = (new ProductRepository())->find($productId);
        if (!$product || (int) $product['is_active'] !== 1) {
            http_response_code(404);
            echo 'Produto nao encontrado.';
            return;
        }

        $minimumQuantity = max(1, (int) ($product['minimum_quantity'] ?? 1));
        if ($quantity < $minimumQuantity) {
            http_response_code(422);
            echo 'A compra minima deste produto e de ' . $minimumQuantity . ' unidade(s).';
            return;
        }

        if ($product['stock'] !== null && (int) $product['stock'] > 0 && (int) $product['stock'] < $minimumQuantity) {
            http_response_code(422);
            echo 'Este produto nao tem estoque suficiente para atender a compra minima configurada no momento.';
            return;
        }

        if ($product['stock'] !== null && (int) $product['stock'] < $quantity) {
            http_response_code(422);
            echo 'Estoque insuficiente.';
            return;
        }

        $unitPrice = (float) $product['unit_price'];
        $total = $unitPrice * $quantity;
        $loggedUser = user_session() ?? [];

        $orderRepo = new OrderRepository();
        $order = $orderRepo->create([
            'product_id' => $productId,
            'user_id' => (int) ($loggedUser['id'] ?? 0),
            'unit_price_snapshot' => $unitPrice,
            'quantity' => $quantity,
            'total_amount_snapshot' => $total,
            'status' => 'pending_payment',
            'contact_channel' => $contactChannel,
            'contact_value' => $contactValue,
            'in_game_nick' => $nick,
            'in_game_server' => 'LDMO Omegamon',
            'delivery_notes' => $deliveryNotes !== '' ? $deliveryNotes : null,
        ]);

        (new OrderLogRepository())->create([
            'order_id' => $order['id'],
            'admin_id' => null,
            'action' => 'status_change',
            'message' => 'Pedido criado e aguardando pagamento.',
        ]);

        try {
            $mp = new PaymentService();
            $pref = $mp->createPreference($order, $product);
            $env = env('MP_ENV', 'production');
            $redirectUrl = ($env === 'sandbox' && !empty($pref['sandbox_init_point']))
                ? $pref['sandbox_init_point']
                : $pref['init_point'];

            if (!empty($loggedUser['email'])) {
                $mailResult = (new OrderPendingNotificationService())->deliver($order, $product, $loggedUser);
                (new OrderLogRepository())->create([
                    'order_id' => $order['id'],
                    'admin_id' => null,
                    'action' => $mailResult['ok'] ? 'pending_email_sent' : 'pending_email_failed',
                    'message' => $mailResult['ok']
                        ? 'E-mail de pedido pendente enviado para o usuario.'
                        : 'Pedido salvo, mas o e-mail de pedido pendente nao foi enviado.',
                ]);
            }

            redirect($redirectUrl);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Erro ao iniciar pagamento. Tente novamente em alguns instantes.';
        }
    }
}