<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\UserMarketTopupRepository;
use App\Services\PaymentService;
use App\Support\Captcha;
use App\Support\ClientContext;
use App\Support\RateLimiter;
use App\Support\View;

final class OrderController
{
    public function lookup(): void
    {
        View::render('order_lookup');
    }

    public function lookupSubmit(): void
    {
        $limiter = new RateLimiter();
        $rateKey = 'order-lookup:' . hash('sha256', (string) (ClientContext::summary()['ip_address'] ?? 'unknown'));
        $retryAfter = (int) ($limiter->isBlocked($rateKey) ?? 0);
        if ($retryAfter > 0) {
            flash_set('error', 'Muitas consultas de pedido. Aguarde ' . $retryAfter . ' segundo(s) e tente novamente.');
            redirect('/pedido');
        }

        $code = strtoupper(trim((string) ($_POST['public_id'] ?? '')));
        if ($code === '') {
            $limiter->hit($rateKey, 20, 900, 900);
            flash_set('error', 'Informe o codigo do pedido.');
            redirect('/pedido');
        }

        if (!Captcha::validate('order-lookup', (string) ($_POST['captcha_id'] ?? ''), (string) ($_POST['captcha_answer'] ?? ''))) {
            $limiter->hit($rateKey, 20, 900, 900);
            flash_set('error', 'Captcha invalido ou expirado. Tente novamente.');
            redirect('/pedido');
        }

        $limiter->hit($rateKey, 20, 900, 900);
        if (!preg_match('/^[A-F0-9]{8,32}$/', $code)) {
            flash_set('error', 'Codigo de pedido invalido.');
            redirect('/pedido');
        }
        redirect('/pedido/' . $code);
    }

    public function show(string $publicId): void
    {
        $normalizedPublicId = strtoupper(trim($publicId));
        if (!preg_match('/^[A-F0-9]{8,32}$/', $normalizedPublicId)) {
            http_response_code(404);
            echo 'Pedido nao encontrado.';
            return;
        }

        $limiter = new RateLimiter();
        $rateKey = 'order-show:' . hash('sha256', (string) (ClientContext::summary()['ip_address'] ?? 'unknown'));
        $retryAfter = (int) ($limiter->isBlocked($rateKey) ?? 0);
        if ($retryAfter > 0) {
            http_response_code(429);
            echo 'Muitas consultas de pedido. Aguarde alguns instantes e tente novamente.';
            return;
        }

        $limiter->hit($rateKey, 60, 600, 900);
        $order = (new OrderRepository())->findByPublicId($normalizedPublicId);
        if (!$order) {
            http_response_code(404);
            echo 'Pedido nao encontrado.';
            return;
        }

        $settings = new SettingsRepository();
        $whatsapp = $settings->get('whatsapp_link', env('WHATSAPP_LINK'));
        $discord = $settings->get('discord_link', env('DISCORD_LINK'));

        View::render('order', [
            'order' => $order,
            'whatsapp' => $whatsapp,
            'discord' => $discord,
        ]);
    }

    public function resumePayment(string $publicId): void
    {
        $normalizedPublicId = strtoupper(trim($publicId));
        if (!preg_match('/^[A-F0-9]{8,32}$/', $normalizedPublicId)) {
            http_response_code(404);
            echo 'Pedido nao encontrado.';
            return;
        }

        $limiter = new RateLimiter();
        $rateKey = 'order-resume:' . hash('sha256', $normalizedPublicId . '|' . (string) (ClientContext::summary()['ip_address'] ?? 'unknown'));
        $retryAfter = (int) ($limiter->isBlocked($rateKey) ?? 0);
        if ($retryAfter > 0) {
            flash_set('error', 'Muitas tentativas para retomar o pagamento. Aguarde alguns instantes e tente novamente.');
            redirect('/pedido/' . $normalizedPublicId);
        }

        $limiter->hit($rateKey, 8, 300, 600);
        $order = (new OrderRepository())->findByPublicId($normalizedPublicId);
        if (!$order) {
            http_response_code(404);
            echo 'Pedido nao encontrado.';
            return;
        }

        if ((string) ($order['status'] ?? '') !== 'pending_payment') {
            flash_set('error', 'Este pedido nao esta mais aguardando pagamento.');
            redirect('/pedido/' . $normalizedPublicId);
        }

        $topup = (new UserMarketTopupRepository())->findByOrderId((int) ($order['id'] ?? 0));
        $product = (new ProductRepository())->find((int) ($order['product_id'] ?? 0)) ?? [
            'name' => (string) ($order['product_name'] ?? 'Produto Bolfer'),
        ];

        try {
            $mp = new PaymentService();
            if ($topup) {
                $pref = $mp->createMarketTopupPreference(
                    $order,
                    (float) ($topup['amount_brl'] ?? 0),
                    (int) ($topup['coins_amount'] ?? 0)
                );
            } else {
                $pref = $mp->createPreference($order, $product);
            }
            $env = env('MP_ENV', 'production');
            $redirectUrl = ($env === 'sandbox' && !empty($pref['sandbox_init_point']))
                ? $pref['sandbox_init_point']
                : $pref['init_point'];

            redirect($redirectUrl);
        } catch (\Throwable) {
            flash_set('error', 'Nao foi possivel retomar o pagamento agora. Tente novamente em alguns instantes.');
            redirect('/pedido/' . $normalizedPublicId);
        }
    }

    public function updateContact(string $publicId): void
    {
        if (env('ALLOW_PUBLIC_ORDER_CONTACT_EDIT', '0') !== '1') {
            http_response_code(403);
            echo 'Atualizacao publica de contato desativada.';
            return;
        }

        $normalizedPublicId = strtoupper(trim($publicId));
        if (!preg_match('/^[A-F0-9]{8,32}$/', $normalizedPublicId)) {
            http_response_code(404);
            echo 'Pedido nao encontrado.';
            return;
        }

        $nick = trim((string) ($_POST['in_game_nick'] ?? ''));
        $contactChannel = trim((string) ($_POST['contact_channel'] ?? ''));
        $contactValue = trim((string) ($_POST['contact_value'] ?? ''));

        if ($nick === '') {
            http_response_code(422);
            echo 'Nick obrigatorio.';
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

        $repo = new OrderRepository();
        $order = $repo->findByPublicId($normalizedPublicId);
        if (!$order) {
            http_response_code(404);
            echo 'Pedido nao encontrado.';
            return;
        }

        $repo->updateContact($normalizedPublicId, [
            'contact_channel' => $contactChannel,
            'contact_value' => $contactValue,
            'delivery_notes' => $_POST['delivery_notes'] ?? null,
            'in_game_nick' => $nick,
        ]);

        flash_set('success', 'Dados atualizados.');
        redirect('/pedido/' . $normalizedPublicId);
    }
}
