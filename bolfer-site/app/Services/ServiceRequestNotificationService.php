<?php

declare(strict_types=1);

namespace App\Services;

final class ServiceRequestNotificationService
{
    public function deliver(array $request): array
    {
        $recipient = trim((string) \env('SERVICE_REQUEST_TO_ADDRESS', 'admin@example.com'));
        if ($recipient === '') {
            return [
                'ok' => false,
                'delivery' => 'none',
                'message' => 'SERVICE_REQUEST_TO_ADDRESS nao configurado.',
            ];
        }

        $deliveryMode = strtolower(trim((string) \env(
            'MAIL_DRIVER',
            \env('APP_ENV', 'local') === 'local' ? 'log' : 'mail'
        )));

        return match ($deliveryMode) {
            'log' => $this->logDelivery($recipient, $request),
            'smtp' => $this->smtpDelivery($recipient, $request),
            default => $this->phpMailDelivery($recipient, $request),
        };
    }

    private function logDelivery(string $recipient, array $request): array
    {
        $path = $this->logPath();
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $entry = implode(PHP_EOL, [
            str_repeat('=', 72),
            'Data: ' . date('Y-m-d H:i:s'),
            'Destino: ' . $recipient,
            'Nome: ' . $this->field($request, 'name'),
            'Canal: ' . $this->channelLabel($this->field($request, 'channel')),
            'Contato: ' . $this->field($request, 'contact'),
            'Servico: ' . $this->serviceLabel($this->field($request, 'service')),
            'Detalhes: ' . $this->field($request, 'details', 'Nao informado.'),
            'IP: ' . $this->field($request, 'ip', 'Nao informado'),
            'User-Agent: ' . $this->field($request, 'user_agent', 'Nao informado'),
            str_repeat('=', 72),
            '',
        ]);

        $written = @file_put_contents($path, $entry, FILE_APPEND | LOCK_EX);
        if ($written === false) {
            return [
                'ok' => false,
                'delivery' => 'log',
                'message' => 'Nao foi possivel registrar o pedido de servico localmente.',
            ];
        }

        return [
            'ok' => true,
            'delivery' => 'log',
            'message' => 'Pedido de servico salvo no ambiente local.',
            'log_path' => str_replace('\\', '/', $path),
        ];
    }

    private function smtpDelivery(string $recipient, array $request): array
    {
        try {
            $payload = $this->buildMessagePayload($recipient, $request);
            $this->sendViaSmtp(
                $recipient,
                $payload['subject'],
                $payload['html'],
                $payload['text'],
                $payload['headers']
            );

            return [
                'ok' => true,
                'delivery' => 'smtp',
                'message' => 'Pedido de servico enviado para o e-mail da equipe.',
            ];
        } catch (\Throwable $exception) {
            $this->logTransportFailure('smtp', $recipient, $exception);

            return [
                'ok' => false,
                'delivery' => 'smtp',
                'message' => 'Nao foi possivel enviar o pedido de servico por e-mail.',
            ];
        }
    }

    private function phpMailDelivery(string $recipient, array $request): array
    {
        $payload = $this->buildMessagePayload($recipient, $request);
        $boundary = 'bolfer-service-' . bin2hex(random_bytes(12));

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

        $sent = @mail($recipient, $payload['subject'], $message, implode("\r\n", $headers));
        if ($sent) {
            return [
                'ok' => true,
                'delivery' => 'mail',
                'message' => 'Pedido de servico enviado para o e-mail da equipe.',
            ];
        }

        if (\env('APP_ENV', 'local') === 'local') {
            return $this->logDelivery($recipient, $request);
        }

        return [
            'ok' => false,
            'delivery' => 'mail',
            'message' => 'Nao foi possivel enviar o pedido de servico por e-mail.',
        ];
    }

    private function buildMessagePayload(string $recipient, array $request): array
    {
        $fromAddress = trim((string) \env('MAIL_FROM_ADDRESS', 'no-reply@example.com'));
        $fromName = trim((string) \env('MAIL_FROM_NAME', 'Bolfer Official'));
        $replyToAddress = trim((string) \env('MAIL_REPLY_TO_ADDRESS', 'suporte@example.com'));
        $replyToName = trim((string) \env('MAIL_REPLY_TO_NAME', 'Suporte Bolfer Official'));
        $recipientName = trim((string) \env('SERVICE_REQUEST_TO_NAME', 'Admin Bolfer Official'));
        $appName = trim((string) \env('APP_NAME', 'Bolfer Official'));

        $name = $this->field($request, 'name');
        $channel = $this->channelLabel($this->field($request, 'channel'));
        $contact = $this->field($request, 'contact');
        $service = $this->serviceLabel($this->field($request, 'service'));
        $details = $this->field($request, 'details', 'Nao informado.');
        $createdAt = $this->field($request, 'created_at', date('c'));
        $ip = $this->field($request, 'ip', 'Nao informado');
        $userAgent = $this->field($request, 'user_agent', 'Nao informado');
        $submittedAt = date('d/m/Y H:i', strtotime($createdAt) ?: time());
        $subject = 'Novo pedido de servico - ' . $service;

        $htmlMessage = '<!doctype html><html><body style="margin:0;padding:32px;background:#080706;color:#f5eee3;font-family:Arial,sans-serif;">'
            . '<div style="max-width:720px;margin:0 auto;background:linear-gradient(180deg,#11100e 0%,#0b0a09 100%);border:1px solid rgba(255,255,255,0.08);border-radius:24px;overflow:hidden;box-shadow:0 30px 70px rgba(0,0,0,0.45);">'
            . '<div style="padding:18px 28px;background:linear-gradient(135deg,#0f120e 0%,#102017 100%);border-bottom:1px solid rgba(255,255,255,0.08);">'
            . '<span style="display:inline-block;font-size:12px;letter-spacing:3px;text-transform:uppercase;color:#a9c9a1;">Bolfer Official</span>'
            . '</div>'
            . '<div style="padding:34px 28px 20px;">'
            . '<p style="margin:0 0 10px;color:#96aa8d;font-size:12px;letter-spacing:3px;text-transform:uppercase;">Inbox administrativo</p>'
            . '<h1 style="margin:0 0 16px;font-size:34px;line-height:1.05;color:#fff4e8;text-transform:uppercase;">Novo pedido de servico</h1>'
            . '<p style="margin:0 0 24px;font-size:16px;line-height:1.7;color:#e7dccd;">Ola, <strong style="color:#ffffff;">' . htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8') . '</strong>. Um novo formulario foi enviado em <a href="' . htmlspecialchars(\url('/servicos'), ENT_QUOTES, 'UTF-8') . '" style="color:#8fd3ff;text-decoration:none;">/servicos</a>.</p>'
            . '<div style="display:grid;gap:14px;margin:0 0 24px;">'
            . $this->htmlRow('Nome', $name)
            . $this->htmlRow('Canal', $channel)
            . $this->htmlRow('Contato', $contact)
            . $this->htmlRow('Servico', $service)
            . $this->htmlRow('Enviado em', $submittedAt)
            . '</div>'
            . '<div style="margin:0 0 24px;padding:20px;border-radius:20px;background:#121614;border:1px solid rgba(57,255,20,0.18);">'
            . '<p style="margin:0 0 10px;font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#96aa8d;">Detalhes do pedido</p>'
            . '<div style="font-size:15px;line-height:1.8;color:#f2e7d8;white-space:pre-wrap;">' . nl2br(htmlspecialchars($details, ENT_QUOTES, 'UTF-8')) . '</div>'
            . '</div>'
            . '<div style="margin:0 0 20px;padding:18px 20px;border-radius:18px;background:#0e0d0c;border:1px solid rgba(255,255,255,0.06);">'
            . '<p style="margin:0 0 8px;font-size:13px;color:#a8a093;text-transform:uppercase;letter-spacing:2px;">Contexto tecnico</p>'
            . '<p style="margin:0 0 6px;font-size:14px;line-height:1.7;color:#d8cbb9;"><strong style="color:#fff4e8;">IP:</strong> ' . htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p style="margin:0;font-size:14px;line-height:1.7;color:#d8cbb9;word-break:break-word;"><strong style="color:#fff4e8;">User-Agent:</strong> ' . htmlspecialchars($userAgent, ENT_QUOTES, 'UTF-8') . '</p>'
            . '</div>'
            . '</div>'
            . '<div style="padding:18px 28px 24px;border-top:1px solid rgba(255,255,255,0.06);color:#968c7c;font-size:13px;line-height:1.7;">'
            . '<strong style="color:#f0e2cf;">' . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . '</strong><br>'
            . 'Notificacao enviada por ' . htmlspecialchars($fromAddress, ENT_QUOTES, 'UTF-8')
            . '</div>'
            . '</div></body></html>';

        $textMessage = implode("\n", [
            $appName . ' - Novo pedido de servico',
            '',
            'Nome: ' . $name,
            'Canal: ' . $channel,
            'Contato: ' . $contact,
            'Servico: ' . $service,
            'Enviado em: ' . $submittedAt,
            '',
            'Detalhes:',
            $details,
            '',
            'IP: ' . $ip,
            'User-Agent: ' . $userAgent,
            '',
            'Pagina: ' . \url('/servicos'),
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
            throw new \RuntimeException('SMTP_HOST, SMTP_USER ou SMTP_PASS nao configurados.');
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
                    throw new \RuntimeException('Nao foi possivel iniciar TLS no SMTP.');
                }
                $this->smtpCommand($stream, 'EHLO ' . $helloHost, [250]);
            }

            $this->smtpCommand($stream, 'AUTH LOGIN', [334]);
            $this->smtpCommand($stream, base64_encode($username), [334]);
            $this->smtpCommand($stream, base64_encode($password), [235]);
            $this->smtpCommand($stream, 'MAIL FROM:<' . $fromAddress . '>', [250]);
            $this->smtpCommand($stream, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->smtpCommand($stream, 'DATA', [354]);

            $boundary = 'bolfer-smtp-' . bin2hex(random_bytes(12));
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

            $message = preg_replace('/(?m)^\\./', '..', $message) ?? $message;
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
            throw new \RuntimeException('Servidor SMTP nao respondeu.');
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

    private function field(array $request, string $key, string $fallback = ''): string
    {
        $value = trim((string) ($request[$key] ?? ''));

        return $value !== '' ? $value : $fallback;
    }

    private function channelLabel(string $value): string
    {
        return match (strtolower(trim($value))) {
            'whatsapp' => 'WhatsApp',
            'discord' => 'Discord',
            default => $value !== '' ? $value : 'Nao informado',
        };
    }

    private function serviceLabel(string $value): string
    {
        return match (strtolower(trim($value))) {
            'websites' => 'Criacao de websites',
            'design' => 'Design digital',
            'marketing' => 'Marketing digital',
            'consultoria' => 'Consultoria',
            'games' => 'Servicos para jogos',
            'comunidade' => 'Gestao de comunidade',
            default => $value !== '' ? $value : 'Nao informado',
        };
    }

    private function logTransportFailure(string $driver, string $recipient, \Throwable $exception): void
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
            'Destino: ' . $recipient,
            'Erro: ' . $exception->getMessage(),
            str_repeat('=', 72),
            '',
        ]);

        @file_put_contents($path, $entry, FILE_APPEND | LOCK_EX);
    }

    private function logPath(): string
    {
        return dirname(__DIR__, 2) . '/storage/logs/service_request_mail.log';
    }
}
