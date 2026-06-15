<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;

final class UserEmailVerificationService
{
    private const VERIFICATION_CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private UserRepository $users;

    public function __construct()
    {
        $this->users = new UserRepository();
    }

    public function issueForUser(array $user): array
    {
        $userId = (int) ($user['id'] ?? 0);
        $email = strtolower(trim((string) ($user['email'] ?? '')));
        $username = trim((string) ($user['username'] ?? 'Usuario'));

        if ($userId <= 0 || $email === '') {
            return [
                'ok' => false,
                'delivery' => 'none',
                'message' => 'Conta invalida para envio da verificacao.',
            ];
        }

        $plainToken = $this->generateVerificationCode();
        $tokenHash = hash('sha256', $plainToken);
        $this->users->setEmailVerificationToken($userId, $tokenHash);

        return $this->deliver($email, $username, $plainToken);
    }

    public function verifyToken(string $plainToken): array
    {
        $candidates = $this->tokenCandidates($plainToken);
        if ($candidates === []) {
            return [
                'ok' => false,
                'reason' => 'invalid',
                'message' => 'Codigo ou link de verificacao invalido.',
            ];
        }

        $user = null;
        foreach ($candidates as $candidate) {
            $user = $this->users->findByEmailVerificationTokenHash(hash('sha256', $candidate));
            if ($user) {
                break;
            }
        }

        if (!$user) {
            return [
                'ok' => false,
                'reason' => 'invalid',
                'message' => 'Codigo ou link de verificacao invalido ou ja utilizado.',
            ];
        }

        if (!$this->isWithinTtl((string) ($user['email_verification_sent_at'] ?? ''))) {
            return [
                'ok' => false,
                'reason' => 'expired',
                'user' => $user,
                'message' => 'Este codigo de verificacao expirou. Solicite um novo envio.',
            ];
        }

        $this->users->markEmailVerified((int) ($user['id'] ?? 0));
        $verifiedUser = $this->users->findById((int) ($user['id'] ?? 0)) ?? $user;

        return [
            'ok' => true,
            'user' => $verifiedUser,
            'message' => 'E-mail verificado com sucesso. Agora voce ja pode entrar na sua conta.',
        ];
    }

    public function isPendingVerification(array $user): bool
    {
        return empty($user['email_verified_at']);
    }

    public function ttlMinutes(): int
    {
        return max(10, (int) \env('USER_EMAIL_VERIFICATION_TTL_MINUTES', '1440'));
    }

    public function formatTokenForDisplay(string $token): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9]/', '', strtoupper(trim($token))) ?? '';
        if ($normalized === '') {
            return '';
        }

        return trim(chunk_split($normalized, 4, '-'), '-');
    }

    private function deliver(string $email, string $username, string $plainToken): array
    {
        $deliveryMode = strtolower(trim((string) \env(
            'MAIL_DRIVER',
            \env('APP_ENV', 'local') === 'local' ? 'log' : 'mail'
        )));

        return match ($deliveryMode) {
            'log' => $this->logDelivery($email, $username, $plainToken),
            'smtp' => $this->smtpDelivery($email, $username, $plainToken),
            default => $this->phpMailDelivery($email, $username, $plainToken),
        };
    }

    private function logDelivery(string $email, string $username, string $plainToken): array
    {
        $logPath = $this->logPath();
        $directory = dirname($logPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $entry = implode(PHP_EOL, [
            str_repeat('=', 72),
            'Data: ' . date('Y-m-d H:i:s'),
            'Email: ' . $email,
            'Usuario: ' . $username,
            'Codigo: ' . $this->formatTokenForDisplay($plainToken),
            'Link: ' . $this->verificationUrl($plainToken),
            str_repeat('=', 72),
            '',
        ]);

        $written = @file_put_contents($logPath, $entry, FILE_APPEND | LOCK_EX);
        if ($written === false) {
            return [
                'ok' => false,
                'delivery' => 'log',
                'message' => 'Nao foi possivel registrar a verificacao localmente.',
            ];
        }

        return [
            'ok' => true,
            'delivery' => 'log',
            'message' => 'Codigo de verificacao salvo no ambiente local.',
            'log_path' => str_replace('\\', '/', $logPath),
        ];
    }

    private function smtpDelivery(string $email, string $username, string $plainToken): array
    {
        try {
            $payload = $this->buildMessagePayload($email, $username, $plainToken);
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
                'message' => 'E-mail de verificacao enviado com sucesso.',
            ];
        } catch (\Throwable $exception) {
            $this->logTransportFailure('smtp', $email, $exception);

            return [
                'ok' => false,
                'delivery' => 'smtp',
                'message' => 'Nao foi possivel enviar o e-mail de verificacao. Revise o SMTP da conta no-reply@example.com.',
            ];
        }
    }

    private function phpMailDelivery(string $email, string $username, string $plainToken): array
    {
        $payload = $this->buildMessagePayload($email, $username, $plainToken);
        $boundary = 'bolfer-mail-' . bin2hex(random_bytes(12));

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
                'message' => 'E-mail de verificacao enviado com sucesso.',
            ];
        }

        if (\env('APP_ENV', 'local') === 'local') {
            return $this->logDelivery($email, $username, $plainToken);
        }

        return [
            'ok' => false,
            'delivery' => 'mail',
            'message' => 'Nao foi possivel enviar o e-mail de verificacao.',
        ];
    }

    private function buildMessagePayload(string $email, string $username, string $plainToken): array
    {
        $fromAddress = trim((string) \env('MAIL_FROM_ADDRESS', 'no-reply@example.com'));
        $fromName = trim((string) \env('MAIL_FROM_NAME', 'Bolfer Official'));
        $replyToAddress = trim((string) \env('MAIL_REPLY_TO_ADDRESS', 'suporte@example.com'));
        $replyToName = trim((string) \env('MAIL_REPLY_TO_NAME', 'Suporte Bolfer Official'));
        $subject = 'Confirme seu e-mail - Bolfer Official';
        $verificationUrl = $this->verificationUrl($plainToken);
        $displayCode = $this->formatTokenForDisplay($plainToken);
        $appName = trim((string) \env('APP_NAME', 'Bolfer Official'));

        $htmlMessage = '<!doctype html><html><body style="margin:0;padding:32px;background:#080706;color:#f5eee3;font-family:Arial,sans-serif;">'
            . '<div style="max-width:680px;margin:0 auto;background:linear-gradient(180deg,#11100e 0%,#0b0a09 100%);border:1px solid rgba(255,255,255,0.08);border-radius:24px;overflow:hidden;box-shadow:0 30px 70px rgba(0,0,0,0.45);">'
            . '<div style="padding:18px 28px;background:linear-gradient(135deg,#0f120e 0%,#102017 100%);border-bottom:1px solid rgba(255,255,255,0.08);">'
            . '<span style="display:inline-block;font-size:12px;letter-spacing:3px;text-transform:uppercase;color:#a9c9a1;">Bolfer Official</span>'
            . '</div>'
            . '<div style="padding:34px 28px 20px;">'
            . '<p style="margin:0 0 10px;color:#96aa8d;font-size:12px;letter-spacing:3px;text-transform:uppercase;">Liberacao da conta</p>'
            . '<h1 style="margin:0 0 16px;font-size:36px;line-height:1.05;color:#fff4e8;text-transform:uppercase;">Seu acesso esta quase pronto</h1>'
            . '<p style="margin:0 0 12px;font-size:16px;line-height:1.7;color:#e7dccd;">Ola, <strong style="color:#ffffff;">' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
            . '<p style="margin:0 0 24px;font-size:16px;line-height:1.7;color:#e7dccd;">Use o botao abaixo para confirmar seu e-mail ou copie o codigo de verificacao se preferir confirmar manualmente na tela do site.</p>'
            . '<div style="margin:0 0 24px;padding:22px;border-radius:20px;background:#121614;border:1px solid rgba(57,255,20,0.18);">'
            . '<p style="margin:0 0 10px;font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#96aa8d;">Seu codigo de verificacao</p>'
            . '<div style="font-size:30px;line-height:1.2;font-weight:700;letter-spacing:6px;color:#39ff14;text-align:center;">' . htmlspecialchars($displayCode, ENT_QUOTES, 'UTF-8') . '</div>'
            . '<p style="margin:12px 0 0;text-align:center;font-size:13px;color:#cdbfae;">Este codigo expira em ' . $this->ttlMinutes() . ' minuto(s).</p>'
            . '</div>'
            . '<div style="margin:0 0 26px;text-align:center;">'
            . '<a href="' . htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:16px 28px;border-radius:14px;background:#39ff14;color:#071006;text-decoration:none;font-weight:700;letter-spacing:1px;text-transform:uppercase;">Confirmar meu e-mail</a>'
            . '</div>'
            . '<div style="margin:0 0 20px;padding:18px 20px;border-radius:18px;background:#0e0d0c;border:1px solid rgba(255,255,255,0.06);">'
            . '<p style="margin:0 0 8px;font-size:13px;color:#a8a093;text-transform:uppercase;letter-spacing:2px;">Link direto</p>'
            . '<p style="margin:0;font-size:14px;line-height:1.7;word-break:break-all;"><a href="' . htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#8fd3ff;text-decoration:none;">' . htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8') . '</a></p>'
            . '</div>'
            . '<p style="margin:0 0 10px;font-size:14px;line-height:1.7;color:#cdbfae;">Se voce nao reconhece este cadastro, ignore esta mensagem. Nenhuma alteracao sera concluida sem a confirmacao.</p>'
            . '</div>'
            . '<div style="padding:18px 28px 24px;border-top:1px solid rgba(255,255,255,0.06);color:#968c7c;font-size:13px;line-height:1.7;">'
            . '<strong style="color:#f0e2cf;">' . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . '</strong><br>'
            . 'E-mail enviado por ' . htmlspecialchars($fromAddress, ENT_QUOTES, 'UTF-8')
            . ($replyToAddress !== '' ? '<br>Suporte: ' . htmlspecialchars($replyToAddress, ENT_QUOTES, 'UTF-8') : '')
            . '</div>'
            . '</div></body></html>';

        $textMessage = implode("\n", [
            $appName . ' - Confirmacao de e-mail',
            '',
            'Ola, ' . $username . '.',
            'Seu acesso esta quase pronto.',
            '',
            'Seu codigo de verificacao e: ' . $displayCode,
            'Este codigo expira em ' . $this->ttlMinutes() . ' minuto(s).',
            '',
            'Link direto para confirmar:',
            $verificationUrl,
            '',
            'Se voce nao reconhece este cadastro, ignore esta mensagem.',
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

    private function tokenCandidates(string $input): array
    {
        $input = trim($input);
        if ($input === '') {
            return [];
        }

        $collapsed = preg_replace('/[^A-Za-z0-9]/', '', $input) ?? '';
        $candidates = [
            $input,
            $collapsed,
            strtoupper($collapsed),
            strtolower($collapsed),
        ];

        $result = [];
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '' || in_array($candidate, $result, true)) {
                continue;
            }

            $result[] = $candidate;
        }

        return $result;
    }

    private function generateVerificationCode(int $length = 12): string
    {
        $length = max(8, $length);
        $code = '';
        $maxIndex = strlen(self::VERIFICATION_CODE_ALPHABET) - 1;

        for ($i = 0; $i < $length; $i++) {
            $code .= self::VERIFICATION_CODE_ALPHABET[random_int(0, $maxIndex)];
        }

        return $code;
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

    private function verificationUrl(string $plainToken): string
    {
        return \url('/verify-email/confirm?token=' . rawurlencode($plainToken));
    }

    private function isWithinTtl(string $sentAt): bool
    {
        $timestamp = strtotime($sentAt);
        if ($timestamp === false || $timestamp <= 0) {
            return false;
        }

        return $timestamp >= (time() - ($this->ttlMinutes() * 60));
    }

    private function logPath(): string
    {
        return dirname(__DIR__, 2) . '/storage/logs/email_verification.log';
    }
}
