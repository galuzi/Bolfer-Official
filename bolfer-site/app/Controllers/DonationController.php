<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CategoryRepository;
use App\Repositories\OrderLogRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use App\Services\PaymentService;
use App\Support\ClientContext;

final class DonationController
{
    public function create(): void
    {
        $amountRaw = trim((string) ($_POST['amount'] ?? ''));
        $amountRaw = str_replace(',', '.', $amountRaw);
        $amount = (float) $amountRaw;
        $minAmount = (float) env('DONATION_MIN_AMOUNT', '1');
        if ($minAmount < 1) {
            $minAmount = 1;
        }

        $maxAmount = (float) env('DONATION_MAX_AMOUNT', '1000');
        if ($maxAmount < $minAmount) {
            $maxAmount = $minAmount;
        }

        if ($amount < $minAmount || $amount > $maxAmount) {
            $minLabel = number_format($minAmount, 0, ',', '.');
            $maxLabel = number_format($maxAmount, 0, ',', '.');
            flash_set('error', 'Informe um valor valido para doar (entre R$ ' . $minLabel . ' e R$ ' . $maxLabel . ').');
            redirect('/doacoes');
        }

        $product = $this->ensureDonationProduct();
        $loggedUser = user_session() ?? [];

        $orderRepo = new OrderRepository();
        $order = $orderRepo->create([
            'product_id' => (int) $product['id'],
            'user_id' => (int) ($loggedUser['id'] ?? 0),
            'unit_price_snapshot' => $amount,
            'quantity' => 1,
            'total_amount_snapshot' => $amount,
            'status' => 'pending_payment',
            'contact_channel' => null,
            'contact_value' => null,
            'in_game_nick' => 'DOACAO',
            'in_game_server' => 'LDMO Omegamon',
            'delivery_notes' => 'Doacao',
        ]);

        (new OrderLogRepository())->create([
            'order_id' => $order['id'],
            'admin_id' => null,
            'action' => 'status_change',
            'message' => 'Doacao criada e aguardando pagamento.',
        ]);

        try {
            $mp = new PaymentService();
            $pref = $mp->createDonationPreference($order, $amount);
            $env = env('MP_ENV', 'production');
            $redirectUrl = ($env === 'sandbox' && !empty($pref['sandbox_init_point']))
                ? $pref['sandbox_init_point']
                : $pref['init_point'];

            redirect($redirectUrl);
        } catch (\Throwable $e) {
            $orderRepo->updateStatus((int) $order['id'], 'cancelled');
            (new OrderLogRepository())->create([
                'order_id' => $order['id'],
                'admin_id' => null,
                'action' => 'note_added',
                'message' => 'Falha ao iniciar checkout do Mercado Pago: ' . $this->paymentErrorMessage($e),
            ]);
            $this->logPaymentFailure($amount, $e);
            flash_set('error', 'Nao foi possivel iniciar o pagamento agora. Verifique a configuracao do Mercado Pago na hospedagem e tente novamente.');
            redirect('/doacoes');
        }
    }

    private function ensureDonationProduct(): array
    {
        $categoryRepo = new CategoryRepository();
        $productRepo = new ProductRepository();

        $category = $categoryRepo->findBySlug('doacoes');
        if (!$category) {
            $categoryRepo->create([
                'name' => 'Doacoes',
                'slug' => 'doacoes',
                'is_active' => 0,
                'sort_order' => 999,
            ]);
            $category = $categoryRepo->findBySlug('doacoes');
        }

        $product = $productRepo->findBySlugOrId('doacao-bolfer');
        if (!$product && $category) {
            $productRepo->create([
                'category_id' => (int) $category['id'],
                'name' => 'Doacao Bolfer',
                'slug' => 'doacao-bolfer',
                'unit_price' => 1,
                'stock' => null,
                'server_label' => 'LDMO Omegamon',
                'delivery_eta' => 'Instantaneo',
                'delivery_method' => 'Doacao',
                'description' => 'Doacao para apoiar a comunidade.',
                'notes' => null,
                'is_active' => 0,
            ]);
            $product = $productRepo->findBySlugOrId('doacao-bolfer');
        }

        if (!$product) {
            throw new \RuntimeException('Produto de doacao nao encontrado.');
        }

        return $product;
    }

    private function logPaymentFailure(float $amount, \Throwable $exception): void
    {
        $path = dirname(__DIR__, 2) . '/storage/logs/donation_payment_errors.log';
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $context = ClientContext::summary();
        $entry = implode(PHP_EOL, [
            str_repeat('=', 72),
            'Data: ' . date('Y-m-d H:i:s'),
            'Valor: ' . number_format($amount, 2, '.', ''),
            'IP: ' . (string) ($context['ip_address'] ?? ''),
            'User-Agent: ' . (string) ($context['user_agent'] ?? ''),
            'Erro: ' . $exception->getMessage(),
            str_repeat('=', 72),
            '',
        ]);

        @file_put_contents($path, $entry, FILE_APPEND | LOCK_EX);
    }

    private function paymentErrorMessage(\Throwable $exception): string
    {
        $message = trim(preg_replace('/\s+/', ' ', $exception->getMessage()) ?? '');
        if ($message === '') {
            $message = 'Erro tecnico sem mensagem retornada.';
        }

        return mb_substr($message, 0, 800);
    }
}