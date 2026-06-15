<?php

declare(strict_types=1);

namespace App\Services;

final class OrderPendingNotificationService
{
    public function deliver(array $order, array $product, array $user): array
    {
        $email = strtolower(trim((string) ($user['email'] ?? '')));
        $username = trim((string) ($user['username'] ?? 'Cliente'));

        if ($email === '') {
            return [
                'ok' => false,
                'delivery' => 'none',
                'message' => 'Usuário sem e-mail válido para notificação do pedido.',
            ];
        }

        $deliveryMode = strtolower(trim((string) \env(
            'MAIL_DRIVER',
            \env('APP_ENV', 'local') === 'local' ? 'log' : 'mail'
        )));

        return match ($deliveryMode) {
            'log' => $this->logDelivery($email, $username, $order, $product),
            'smtp' => $this->smtpDelivery($email, $username, $order, $product),
            default => $this->phpMailDelivery($email, $username, $order, $product),
        };
    }

    private function logDelivery(string $email, string $username, array $order, array $product): array
    {
        $path = $this->logPath();
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $entry = implode(PHP_EOL, [
            str_repeat('=', 72),
            'Data: ' . date('Y-m-d H:i:s'),
            'Email: ' . $email,
            'Usuário: ' . $username,
            'Pedido: ' . $this->orderCode($order),
            'Produto: ' . $this->productName($order, $product),
            'Continuar pagamento: ' . $this->resumePaymentUrl($order),
            'Acompanhar pedido: ' . $this->orderUrl($order),
            str_repeat('=', 72),
            '',
        ]);

        $written = @file_put_contents($path, $entry, FILE_APPEND | LOCK_EX);
        if ($written === false) {
            return [
                'ok' => false,
                'delivery' => 'log',
                'message' => 'Não foi possível registrar o e-mail do pedido localmente.',
            ];
        }

        return [
            'ok' => true,
            'delivery' => 'log',
            'message' => 'E-mail do pedido salvo no ambiente local.',
        ];
    }

    private function smtpDelivery(string $email, string $username, array $order, array $product): array
    {
        try {
            $payload = $this->buildMessagePayload($email, $username, $order, $product);
            $this->sendViaSmtp(
                $email,
                $payload['subject'],
                $payload['html'],
                $payload['text'],
                $payload['headers']
            );

            return [
                'ok' => true,
                'delivery' => 'smtp',
                'message' => 'E-mail de pedido pendente enviado com sucesso.',
            ];
        } catch (\Throwable $exception) {
            $this->logTransportFailure('smtp', $email, $exception);

            return [
                'ok' => false,
                'delivery' => 'smtp',
                'message' => 'Não foi possível enviar o e-mail do pedido pendente.',
            ];
        }
    }

    private function phpMailDelivery(string $email, string $username, array $order, array $product): array
    {
        $payload = $this->buildMessagePayload($email, $username, $order, $product);
        $boundary = 'bolfer-order-' . bin2hex(random_bytes(12));

        $headers = array_merge($payload['headers'], [
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ]);

        $message = '--' . $boundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $payload['text'] . "\r\n\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $payload['html'] . "\r\n\r\n"
            . '--' . $boundary . "--\r\n";

        $sent = @mail($email, $payload['subject'], $message, implode("\r\n", $headers));
        if ($sent) {
            return [
                'ok' => true,
                'delivery' => 'mail',
                'message' => 'E-mail de pedido pendente enviado com sucesso.',
            ];
        }

        if (\env('APP_ENV', 'local') === 'local') {
            return $this->logDelivery($email, $username, $order, $product);
        }

        return [
            'ok' => false,
            'delivery' => 'mail',
            'message' => 'Não foi possível enviar o e-mail do pedido pendente.',
        ];
    }

    private function buildMessagePayload(string $email, string $username, array $order, array $product): array
    {
        $fromAddress = trim((string) \env('MAIL_FROM_ADDRESS', 'no-reply@example.com'));
        $fromName = trim((string) \env('MAIL_FROM_NAME', 'Bolfer Official'));
        $replyToAddress = trim((string) \env('MAIL_REPLY_TO_ADDRESS', 'suporte@example.com'));
        $replyToName = trim((string) \env('MAIL_REPLY_TO_NAME', 'Suporte Bolfer Official'));
        $appName = trim((string) \env('APP_NAME', 'Bolfer Official'));

        $orderCode = $this->orderCode($order);
        $productName = $this->productName($order, $product);
        $quantity = max(1, (int) ($order['quantity'] ?? 1));
        $quantityLabel = $quantity === 1 ? '1 unidade' : $quantity . ' unidades';
        $total = 'R$ ' . number_format((float) ($order['total_amount_snapshot'] ?? 0), 2, ',', '.');
        $orderUrl = $this->orderUrl($order);
        $resumeUrl = $this->resumePaymentUrl($order);
        $subject = 'Seu pedido está aguardando pagamento - ' . $orderCode;

        $htmlMessage = '<!doctype html><html><body style="margin:0;padding:32px;background:#080706;color:#f5eee3;font-family:Arial,sans-serif;">'
            . '<div style="max-width:720px;margin:0 auto;background:linear-gradient(180deg,#11100e 0%,#0b0a09 100%);border:1px solid rgba(255,255,255,0.08);border-radius:24px;overflow:hidden;box-shadow:0 30px 70px rgba(0,0,0,0.45);">'
            . '<div style="padding:18px 28px;background:linear-gradient(135deg,#0f120e 0%,#102017 100%);border-bottom:1px solid rgba(255,255,255,0.08);">'
            . '<span style="display:inline-block;font-size:12px;letter-spacing:3px;text-transform:uppercase;color:#a9c9a1;">Bolfer Official</span>'
            . '</div>'
            . '<div style="padding:34px 28px 20px;">'
            . '<p style="margin:0 0 10px;color:#96aa8d;font-size:12px;letter-spacing:3px;text-transform:uppercase;">Pedido criado</p>'
            . '<h1 style="margin:0 0 16px;font-size:34px;line-height:1.05;color:#fff4e8;text-transform:uppercase;">Seu pedido está aguardando pagamento</h1>'
            . '<p style="margin:0 0 12px;font-size:16px;line-height:1.7;color:#e7dccd;">Olá, <strong style="color:#ffffff;">' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
            . '<p style="margin:0 0 24px;font-size:16px;line-height:1.7;color:#e7dccd;">Seu pedido foi criado com sucesso, mas a compra ainda não foi concluída porque o pagamento não foi confirmado. Se você saiu do checkout ou quer retomar depois, use os links abaixo.</p>'
            . '<div style="display:grid;gap:14px;margin:0 0 24px;">'
            . $this->htmlRow('Código do pedido', $orderCode)
            . $this->htmlRow('Produto', $productName)
            . $this->htmlRow('Quantidade', $quantityLabel)
            . $this->htmlRow('Total', $total)
            . '</div>'
            . '<div style="margin:0 0 24px;padding:22px;border-radius:20px;background:#121614;border:1px solid rgba(57,255,20,0.18);">'
            . '<p style="margin:0 0 10px;font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#96aa8d;">Status atual</p>'
            . '<div style="font-size:28px;line-height:1.2;font-weight:700;color:#39ff14;text-align:center;">Aguardando pagamento</div>'
            . '<p style="margin:12px 0 0;text-align:center;font-size:13px;color:#cdbfae;">Guarde o código do pedido para acompanhar tudo com facilidade.</p>'
            . '</div>'
            . '<div style="margin:0 0 18px;text-align:center;">'
            . '<a href="' . htmlspecialchars($resumeUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:16px 28px;border-radius:14px;background:#39ff14;color:#071006;text-decoration:none;font-weight:700;letter-spacing:1px;text-transform:uppercase;">Continuar pagamento</a>'
            . '</div>'
            . '<div style="margin:0 0 26px;text-align:center;">'
            . '<a href="' . htmlspecialchars($orderUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:14px 24px;border-radius:14px;background:#171513;color:#f5eee3;text-decoration:none;font-weight:700;letter-spacing:1px;text-transform:uppercase;border:1px solid rgba(255,255,255,0.08);">Acompanhar pedido</a>'
            . '</div>'
            . '<div style="margin:0 0 20px;padding:18px 20px;border-radius:18px;background:#0e0d0c;border:1px solid rgba(255,255,255,0.06);">'
            . '<p style="margin:0 0 8px;font-size:13px;color:#a8a093;text-transform:uppercase;letter-spacing:2px;">Links diretos</p>'
            . '<p style="margin:0 0 10px;font-size:14px;line-height:1.7;word-break:break-all;"><strong style="color:#fff4e8;">Continuar pagamento:</strong><br><a href="' . htmlspecialchars($resumeUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#8fd3ff;text-decoration:none;">' . htmlspecialchars($resumeUrl, ENT_QUOTES, 'UTF-8') . '</a></p>'
            . '<p style="margin:0;font-size:14px;line-height:1.7;word-break:break-all;"><strong style="color:#fff4e8;">Página do pedido:</strong><br><a href="' . htmlspecialchars($orderUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#8fd3ff;text-decoration:none;">' . htmlspecialchars($orderUrl, ENT_QUOTES, 'UTF-8') . '</a></p>'
            . '</div>'
            . '<p style="margin:0 0 10px;font-size:14px;line-height:1.7;color:#cdbfae;">Se você já concluiu o pagamento, pode ignorar este aviso. Assim que a plataforma confirmar a compra, o status do pedido será atualizado automaticamente.</p>'
            . '</div>'
            . '<div style="padding:18px 28px 24px;border-top:1px solid rgba(255,255,255,0.06);color:#968c7c;font-size:13px;line-height:1.7;">'
            . '<strong style="color:#f0e2cf;">' . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . '</strong><br>'
            . 'E-mail enviado por ' . htmlspecialchars($fromAddress, ENT_QUOTES, 'UTF-8')
            . ($replyToAddress !== '' ? '<br>Suporte: ' . htmlspecialchars($replyToAddress, ENT_QUOTES, 'UTF-8') : '')
            . '</div>'
            . '</div></body></html>';

        $textMessage = implode("\n", [
            $appName . ' - Pedido aguardando pagamento',
            '',
            'Olá, ' . $username . '.',
            'Seu pedido foi criado, mas o pagamento ainda não foi confirmado.',
            '',
            'Código do pedido: ' . $orderCode,
            'Produto: ' . $productName,
            'Quantidade: ' . $quantityLabel,
            'Total: ' . $total,
            'Status: Aguardando pagamento',
            '',
            'Continuar pagamento:',
            $resumeUrl,
            '',
            'Acompanhar pedido:',
            $orderUrl,
        ]);

        $headers = [
            'MIME-Version: 1.0',
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $this->mailDomain() . '>',
            'X-Mailer: Bolfer Official Mailer',
            'From: ' . $this->formatAddressHeader($fromName, $fromAddress),
        ];
        if ($replyToAddress !== '') {
            $headers[] = 'Reply-To: ' . $this->formatAddressHeader($replyToName, $replyToAddress);
        }

        return [
            'subject' => $subject,
            'html' => $htmlMessage,
            'text' => $textMessage,
            'headers' => $headers,
        ];
    }

    private function htmlRow(string $label, string $value): string
    {
        return '<div style="padding:16px 18px;border-radius:18px;background:#0e0d0c;border:1px solid rgba(255,255,255,0.06);">'
            . '<p style="margin:0 0 6px;font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#96aa8d;">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<div style="font-size:16px;line-height:1.6;color:#fff4e8;">' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</div>'
            . '</div>';
    }

    private function sendViaSmtp(string $to, string $subject, string $htmlMessage, string $textMessage, array $headers): void
    {
        $host = trim((string) \env('SMTP_HOST', 'smtp.kinghost.net'));
        $port = max(1, (int) \env('SMTP_PORT', '465'));
        $secure = strtolower(trim((string) \env('SMTP_SECURE', 'ssl')));
        $username = trim((string) \env('SMTP_USER', ''));
        $password = (string) \env('SMTP_PASS', '');
        $timeout = max(5, (int) \env('SMTP_TIMEOUT', '15'));
        $fromAddress = trim((string) \env('MAIL_FROM_ADDRESS', 'no-reply@example.com'));

        if ($host === '' || $username === '' || $password === '') {
            throw new \RuntimeException('SMTP_HOST, SMTP_USER ou SMTP_PASS não configurados.');
        }

        $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $stream = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
        if (!is_resource($stream)) {
            throw new \RuntimeException('Falha ao conectar no SMTP: ' . $errstr . ' (' . $errno . ').');
        }

        stream_set_timeout($stream, $timeout);

        try {
            $this->smtpExpect($stream, [220]);

            $helloHost = parse_url((string) \env('APP_URL', ''), PHP_URL_HOST);
            if (!is_string($helloHost) || $helloHost === '') {
                $helloHost = 'localhost';
            }

            $this->smtpCommand($stream, 'EHLO ' . $helloHost, [250]);

            if ($secure === 'tls') {
                $this->smtpCommand($stream, 'STARTTLS', [220]);
                $cryptoEnabled = @stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($cryptoEnabled !== true) {
                    throw new \RuntimeException('Não foi possível iniciar TLS no SMTP.');
                }
                $this->smtpCommand($stream, 'EHLO ' . $helloHost, [250]);
            }

            $this->smtpCommand($stream, 'AUTH LOGIN', [334]);
            $this->smtpCommand($stream, base64_encode($username), [334]);
            $this->smtpCommand($stream, base64_encode($password), [235]);
            $this->smtpCommand($stream, 'MAIL FROM:<' . $fromAddress . '>', [250]);
            $this->smtpCommand($stream, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->smtpCommand($stream, 'DATA', [354]);

            $boundary = 'bolfer-order-smtp-' . bin2hex(random_bytes(12));
            $smtpHeaders = array_merge($headers, [
                'To: <' . $to . '>',
                'Subject: ' . $this->mimeHeader($subject),
                'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            ]);

            $message = implode("\r\n", $smtpHeaders) . "\r\n\r\n"
                . '--' . $boundary . "\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode($textMessage))
                . '--' . $boundary . "\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode($htmlMessage))
                . '--' . $boundary . "--\r\n";

            $message = preg_replace('/(?m)^\./', '..', $message) ?? $message;
            fwrite($stream, $message . "\r\n.\r\n");
            $this->smtpExpect($stream, [250]);
            $this->smtpCommand($stream, 'QUIT', [221]);
        } finally {
            fclose($stream);
        }
    }

    private function smtpCommand($stream, string $command, array $expectedCodes): string
    {
        fwrite($stream, $command . "\r\n");
        return $this->smtpExpect($stream, $expectedCodes);
    }

    private function smtpExpect($stream, array $expectedCodes): string
    {
        $response = '';

        while (($line = fgets($stream, 515)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }

        if ($response === '') {
            throw new \RuntimeException('Servidor SMTP não respondeu.');
        }

        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new \RuntimeException('Resposta SMTP inesperada: ' . trim($response));
        }

        return $response;
    }

    private function mimeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function formatAddressHeader(string $name, string $address): string
    {
        $address = trim($address);
        $name = trim($name);

        if ($name === '') {
            return $address;
        }

        return $this->mimeHeader($name) . ' <' . $address . '>';
    }

    private function mailDomain(): string
    {
        $host = parse_url((string) \env('APP_URL', ''), PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            $host = 'example.com';
        }

        $host = preg_replace('/[^A-Za-z0-9.-]/', '', $host) ?? '';

        return $host !== '' ? $host : 'example.com';
    }

    private function logTransportFailure(string $driver, string $email, \Throwable $exception): void
    {
        $path = dirname(__DIR__, 2) . '/storage/logs/email_transport.log';
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $entry = implode(PHP_EOL, [
            str_repeat('=', 72),
            'Data: ' . date('Y-m-d H:i:s'),
            'Driver: ' . strtoupper($driver),
            'Destino: ' . $email,
            'Erro: ' . $exception->getMessage(),
            str_repeat('=', 72),
            '',
        ]);

        @file_put_contents($path, $entry, FILE_APPEND | LOCK_EX);
    }

    private function logPath(): string
    {
        return dirname(__DIR__, 2) . '/storage/logs/order_pending_mail.log';
    }

    private function orderCode(array $order): string
    {
        return strtoupper(trim((string) ($order['public_id'] ?? '---')));
    }

    private function productName(array $order, array $product): string
    {
        $name = trim((string) ($product['name'] ?? $order['product_name'] ?? 'Produto Bolfer'));
        return $name !== '' ? $name : 'Produto Bolfer';
    }

    private function orderUrl(array $order): string
    {
        return \url('/pedido/' . rawurlencode($this->orderCode($order)));
    }

    private function resumePaymentUrl(array $order): string
    {
        return \url('/pedido/' . rawurlencode($this->orderCode($order)) . '/continuar');
    }
}

