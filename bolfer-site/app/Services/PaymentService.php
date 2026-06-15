<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\ClientContext;

final class PaymentService
{
    private string $accessToken;

    public function __construct()
    {
        $this->accessToken = env('MP_ACCESS_TOKEN', '');
    }

    public function createPreference(array $order, array $product): array
    {
        if ($this->accessToken === '') {
            throw new \RuntimeException('MP_ACCESS_TOKEN nao configurado.');
        }

        $baseUrl = $this->baseUrl();
        $publicId = urlencode((string) ($order['public_id'] ?? ''));
        $payload = [
            'items' => [
                [
                    'title' => $product['name'],
                    'quantity' => (int) $order['quantity'],
                    'unit_price' => (float) $order['unit_price_snapshot'],
                    'currency_id' => 'BRL',
                ],
            ],
            'external_reference' => $order['public_id'],
            'back_urls' => [
                'success' => $baseUrl . '/success?order=' . $publicId,
                'pending' => $baseUrl . '/pending?order=' . $publicId,
                'failure' => $baseUrl . '/failure?order=' . $publicId,
            ],
            'auto_return' => 'approved',
        ];

        $notificationUrl = env('MP_NOTIFICATION_URL', '');
        if ($notificationUrl === '') {
            $lower = strtolower($baseUrl);
            if (!str_contains($lower, 'localhost') && !str_contains($lower, '127.0.0.1')) {
                $notificationUrl = $baseUrl . '/webhook/mercadopago';
            }
        }
        if ($notificationUrl !== '') {
            $payload['notification_url'] = $notificationUrl;
        }

        return $this->request('POST', 'https://api.mercadopago.com/checkout/preferences', $payload);
    }

    public function createDonationPreference(array $order, float $amount): array
    {
        if ($this->accessToken === '') {
            throw new \RuntimeException('MP_ACCESS_TOKEN nao configurado.');
        }

        $baseUrl = $this->baseUrl();
        $publicId = urlencode((string) ($order['public_id'] ?? ''));
        $payload = [
            'items' => [
                [
                    'title' => 'Doacao Bolfer',
                    'quantity' => 1,
                    'unit_price' => (float) $amount,
                    'currency_id' => 'BRL',
                ],
            ],
            'external_reference' => $order['public_id'],
            'back_urls' => [
                'success' => $baseUrl . '/success?order=' . $publicId . '&type=donation',
                'pending' => $baseUrl . '/pending?order=' . $publicId . '&type=donation',
                'failure' => $baseUrl . '/failure?order=' . $publicId . '&type=donation',
            ],
            'auto_return' => 'approved',
            'metadata' => [
                'type' => 'donation',
            ],
        ];

        $notificationUrl = env('MP_NOTIFICATION_URL', '');
        if ($notificationUrl === '') {
            $lower = strtolower($baseUrl);
            if (!str_contains($lower, 'localhost') && !str_contains($lower, '127.0.0.1')) {
                $notificationUrl = $baseUrl . '/webhook/mercadopago';
            }
        }
        if ($notificationUrl !== '') {
            $payload['notification_url'] = $notificationUrl;
        }

        return $this->request('POST', 'https://api.mercadopago.com/checkout/preferences', $payload);
    }

    public function createMarketTopupPreference(array $order, float $amount, int $coinsAmount): array
    {
        if ($this->accessToken === '') {
            throw new \RuntimeException('MP_ACCESS_TOKEN nao configurado.');
        }

        $baseUrl = $this->baseUrl();
        $publicId = urlencode((string) ($order['public_id'] ?? ''));
        $payload = [
            'items' => [
                [
                    'title' => 'Bolfer Market Coins',
                    'description' => $coinsAmount . ' coins do mercado interno',
                    'quantity' => 1,
                    'unit_price' => (float) $amount,
                    'currency_id' => 'BRL',
                ],
            ],
            'external_reference' => $order['public_id'],
            'back_urls' => [
                'success' => $baseUrl . '/success?order=' . $publicId . '&type=market_topup',
                'pending' => $baseUrl . '/pending?order=' . $publicId . '&type=market_topup',
                'failure' => $baseUrl . '/failure?order=' . $publicId . '&type=market_topup',
            ],
            'auto_return' => 'approved',
            'metadata' => [
                'type' => 'market_topup',
                'coins_amount' => $coinsAmount,
            ],
        ];

        $notificationUrl = env('MP_NOTIFICATION_URL', '');
        if ($notificationUrl === '') {
            $lower = strtolower($baseUrl);
            if (!str_contains($lower, 'localhost') && !str_contains($lower, '127.0.0.1')) {
                $notificationUrl = $baseUrl . '/webhook/mercadopago';
            }
        }
        if ($notificationUrl !== '') {
            $payload['notification_url'] = $notificationUrl;
        }

        return $this->request('POST', 'https://api.mercadopago.com/checkout/preferences', $payload);
    }

    public function fetchPayment(string $paymentId): array
    {
        if ($this->accessToken === '') {
            throw new \RuntimeException('MP_ACCESS_TOKEN nao configurado.');
        }

        return $this->request('GET', 'https://api.mercadopago.com/v1/payments/' . urlencode($paymentId));
    }

    public function refundPayment(string $paymentId, ?float $amount = null): array
    {
        if ($this->accessToken === '') {
            throw new \RuntimeException('MP_ACCESS_TOKEN nao configurado.');
        }

        $payload = null;
        if ($amount !== null) {
            $payload = [
                'amount' => $amount,
            ];
        }

        $idempotencyKey = bin2hex(random_bytes(16));
        return $this->request(
            'POST',
            'https://api.mercadopago.com/v1/payments/' . urlencode($paymentId) . '/refunds',
            $payload,
            ['X-Idempotency-Key: ' . $idempotencyKey]
        );
    }

    private function request(string $method, string $url, ?array $payload = null, array $extraHeaders = []): array
    {
        $ch = curl_init($url);
        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
        ];

        if ($extraHeaders !== []) {
            $headers = array_merge($headers, $extraHeaders);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('Erro na requisicao MP: ' . $error);
        }

        curl_close($ch);

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Resposta invalida do Mercado Pago.');
        }

        if ($status >= 400) {
            $message = $data['message'] ?? 'Erro do Mercado Pago.';
            throw new \RuntimeException($message);
        }

        return $data;
    }

    private function baseUrl(): string
    {
        $baseUrl = rtrim(env('APP_URL', ''), '/');
        if ($baseUrl === '' && env('APP_ENV', 'local') === 'local') {
            $scheme = ClientContext::isSecureRequest() ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? '';
            if ($host !== '') {
                $baseUrl = $scheme . '://' . $host;
            }
        }

        if ($baseUrl === '') {
            throw new \RuntimeException('APP_URL nao configurado.');
        }

        return $baseUrl;
    }
}
