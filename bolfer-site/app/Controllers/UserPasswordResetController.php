<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\UserAccessLogRepository;
use App\Repositories\UserRepository;
use App\Services\UserPasswordResetService;
use App\Support\ClientContext;
use App\Support\RateLimiter;
use App\Support\View;

final class UserPasswordResetController
{
    public function showRequest(): void
    {
        View::render('auth/forgot_password', [
            'ttlMinutes' => (new UserPasswordResetService())->ttlMinutes(),
        ]);
    }

    public function send(): void
    {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', 'Informe um e-mail válido para recuperar a senha.');
            redirect('/forgot-password');
        }

        $limiter = new RateLimiter();
        $context = ClientContext::summary();
        $ipKey = $this->rateLimitKey('user-password-reset:ip', (string) ($context['ip_address'] ?? 'unknown'));
        $emailKey = $this->rateLimitKey('user-password-reset:email', $email);
        $retryAfter = max((int) ($limiter->isBlocked($ipKey) ?? 0), (int) ($limiter->isBlocked($emailKey) ?? 0));

        if ($retryAfter > 0) {
            flash_set('error', 'Aguarde ' . $retryAfter . ' segundo(s) antes de solicitar uma nova recuperação.');
            redirect('/forgot-password');
        }

        $limiter->hit($ipKey, 3, 900, 1800);
        $limiter->hit($emailKey, 3, 900, 1800);

        $repo = new UserRepository();
        $user = $repo->findByEmail($email);
        $dispatch = ['ok' => true, 'delivery' => 'none'];

        if ($user) {
            $dispatch = (new UserPasswordResetService())->issueForUser($user);

            (new UserAccessLogRepository())->create([
                'user_id' => (int) ($user['id'] ?? 0),
                'username_snapshot' => (string) ($user['username'] ?? ''),
                'email_snapshot' => (string) ($user['email'] ?? ''),
                'ip_address' => (string) ($context['ip_address'] ?? ''),
                'fingerprint_hash' => (string) ($context['fingerprint_hash'] ?? ''),
                'user_agent' => (string) ($context['user_agent'] ?? ''),
                'route' => (string) ($context['route'] ?? ''),
                'action' => !empty($dispatch['ok']) ? 'password_reset_requested' : 'password_reset_request_failed',
            ]);
        }

        $message = 'Se existir uma conta vinculada a este e-mail, você vai receber uma mensagem com o link para redefinir a senha.';
        if (($dispatch['delivery'] ?? '') === 'log' && !empty($dispatch['log_path'])) {
            $message .= ' Ambiente local detectado: o link foi salvo em ' . $dispatch['log_path'] . '.';
        }

        flash_set('success', $message);
        redirect('/forgot-password');
    }

    public function showReset(): void
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        $service = new UserPasswordResetService();
        $validation = $token !== '' ? $service->validateToken($token) : null;

        View::render('auth/reset_password', [
            'resetToken' => $token,
            'resetTokenValid' => !empty($validation['ok']),
            'tokenChecked' => $token !== '',
            'resetTokenMessage' => (string) ($validation['message'] ?? ''),
            'ttlMinutes' => $service->ttlMinutes(),
        ]);
    }

    public function reset(): void
    {
        $token = trim((string) ($_POST['token'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        if ($token === '') {
            flash_set('error', 'Informe o código de recuperação recebido por e-mail.');
            redirect('/forgot-password/reset');
        }

        if (strlen($password) < 8) {
            flash_set('error', 'Senha muito curta. Use pelo menos 8 caracteres.');
            redirect('/forgot-password/reset?token=' . urlencode($token));
        }

        if ($password !== $passwordConfirm) {
            flash_set('error', 'As senhas não conferem.');
            redirect('/forgot-password/reset?token=' . urlencode($token));
        }

        $limiter = new RateLimiter();
        $context = ClientContext::summary();
        $resetKey = $this->rateLimitKey('user-password-reset:confirm', $token . '|' . (string) ($context['ip_address'] ?? 'unknown'));
        $retryAfter = (int) ($limiter->isBlocked($resetKey) ?? 0);

        if ($retryAfter > 0) {
            flash_set('error', 'Aguarde ' . $retryAfter . ' segundo(s) antes de tentar redefinir a senha novamente.');
            redirect('/forgot-password/reset?token=' . urlencode($token));
        }

        $service = new UserPasswordResetService();
        $result = $service->resetPassword($token, $password);
        if (empty($result['ok'])) {
            $limiter->hit($resetKey, 5, 900, 1800);
            flash_set('error', (string) ($result['message'] ?? 'Não foi possível redefinir a senha agora.'));
            redirect('/forgot-password/reset?token=' . urlencode($token));
        }

        $user = (array) ($result['user'] ?? []);
        (new UserAccessLogRepository())->create([
            'user_id' => (int) ($user['id'] ?? 0),
            'username_snapshot' => (string) ($user['username'] ?? ''),
            'email_snapshot' => (string) ($user['email'] ?? ''),
            'ip_address' => (string) ($context['ip_address'] ?? ''),
            'fingerprint_hash' => (string) ($context['fingerprint_hash'] ?? ''),
            'user_agent' => (string) ($context['user_agent'] ?? ''),
            'route' => (string) ($context['route'] ?? ''),
            'action' => 'password_reset_completed',
        ]);

        $limiter->clear($resetKey);

        flash_set('success', 'Senha redefinida com sucesso. Agora você já pode entrar na sua conta.');
        redirect('/login');
    }

    private function rateLimitKey(string $prefix, string $value): string
    {
        return $prefix . ':' . hash('sha256', strtolower(trim($value)));
    }
}

