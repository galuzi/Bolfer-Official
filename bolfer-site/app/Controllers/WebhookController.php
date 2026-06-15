<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PaymentSyncService;

final class WebhookController
{
    public function handle(): void
    {
        $raw = file_get_contents('php://input') ?: '';
        $payload = json_decode($raw, true) ?: [];

        $secret = env('MP_WEBHOOK_SECRET', '');
        if ($secret === '') {
            http_response_code(500);
            echo 'Webhook secret nao configurado.';
            return;
        }

        $signatureHeader = $_SERVER['HTTP_X_SIGNATURE']
            ?? $_SERVER['HTTP_X_MERCADOPAGO_SIGNATURE']
            ?? $_SERVER['HTTP_X_HUB_SIGNATURE']
            ?? '';

        if (!$this->validateSignature($signatureHeader, $raw, $secret)) {
            http_response_code(401);
            echo 'Assinatura invalida.';
            return;
        }

        $paymentId = $payload['data']['id'] ?? $payload['id'] ?? $_GET['id'] ?? null;
        if (!$paymentId) {
            http_response_code(422);
            echo 'Pagamento nao informado.';
            return;
        }

        try {
            $result = (new PaymentSyncService())->syncByMpPaymentId((string) $paymentId);
            http_response_code((int) ($result['http_code'] ?? 200));
            echo (string) ($result['message'] ?? 'OK');
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Falha ao sincronizar pagamento.';
        }
    }

    private function validateSignature(string $header, string $raw, string $secret): bool
    {
        if ($header === '') {
            return false;
        }

        $parts = preg_split('/[;,]/', $header);
        $data = [];
        foreach ($parts as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) === 2) {
                $data[$pair[0]] = $pair[1];
            }
        }

        $ts = $data['ts'] ?? null;
        $sig = $data['v1'] ?? $data['signature'] ?? $header;
        $payloadToSign = $ts ? ($ts . '.' . $raw) : $raw;
        $expected = hash_hmac('sha256', $payloadToSign, $secret);

        return hash_equals($expected, $sig);
    }
}
