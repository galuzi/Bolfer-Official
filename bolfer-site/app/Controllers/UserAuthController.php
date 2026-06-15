<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\UserAccessLogRepository;
use App\Repositories\UserRepository;
use App\Services\BanService;
use App\Services\TwoFactorService;
use App\Services\UserEmailVerificationService;
use App\Support\Captcha;
use App\Support\ClientContext;
use App\Support\Database;
use App\Support\RateLimiter;
use App\Support\Session;
use App\Support\View;

final class UserAuthController
{
    private const PENDING_SESSION_KEY = '_user_pending_2fa';
    private const PENDING_SETUP_SECRET_KEY = '_user_pending_user_2fa_secret';
    private const RECOVERY_CODES_SESSION_KEY = '_user_2fa_recovery_codes_once';
    private const PENDING_TTL_SECONDS = 900;

    public function showLogin(): void
    {
        View::render('auth/login');
    }

    public function showRegister(): void
    {
        View::render('auth/register');
    }

    public function showVerificationPending(): void
    {
        $email = strtolower(trim((string) ($_GET['email'] ?? '')));

        View::render('auth/verify_email_pending', [
            'email' => $email,
            'ttlMinutes' => (new UserEmailVerificationService())->ttlMinutes(),
        ]);
    }

    public function showTwoFactorVerify(): void
    {
        $pending = $this->pendingUser(true);
        $user = (new UserRepository())->findById((int) ($pending['id'] ?? 0));
        if (!$user) {
            $this->clearPendingUser();
            flash_set('error', 'Sessao de autenticacao expirada.');
            redirect('/login');
        }

        if (empty($user['two_factor_enabled'])) {
            $this->clearPendingUser();
            flash_set('error', 'O 2FA desta conta nao esta ativo.');
            redirect('/login');
        }

        if (!empty($user['is_banned'])) {
            $this->clearPendingUser();
            flash_set('error', 'Esta conta nao pode concluir o login no momento.');
            redirect('/login');
        }

        View::render('auth/two_factor_verify', [
            'userLogin' => (string) ($user['email'] ?? $user['username'] ?? ''),
        ]);
    }

    public function showTwoFactorSetup(): void
    {
        require_user();

        $sessionUser = user_session() ?? [];
        $user = (new UserRepository())->findById((int) ($sessionUser['id'] ?? 0));
        if (!$user) {
            Session::destroy();
            redirect('/login');
        }

        $twoFactorEnabled = !empty($user['two_factor_enabled']);
        $recoveryCodes = $this->consumeRecoveryCodesForDisplay();
        $formattedSecret = null;
        $secretRaw = null;
        $otpAuthUri = null;
        $qrCodeUrl = null;

        if (!$twoFactorEnabled) {
            $secretRaw = $this->pendingSetupSecret();
            $twoFactor = new TwoFactorService();
            $otpIssuer = trim((string) env('APP_NAME', 'Bolfer Official'));
            if ($otpIssuer === '') {
                $otpIssuer = 'Bolfer Official';
            }
            $otpAuthUri = $twoFactor->otpAuthUri((string) ($user['email'] ?? $user['username'] ?? 'usuario'), $secretRaw, $otpIssuer . ' Usuario');
            $formattedSecret = $twoFactor->formatSecret($secretRaw);
            $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' . rawurlencode($otpAuthUri);
        } else {
            unset($_SESSION[self::PENDING_SETUP_SECRET_KEY]);
        }

        View::render('user/two_factor', [
            'user' => $sessionUser,
            'currentUserProfile' => $user,
            'twoFactorEnabled' => $twoFactorEnabled,
            'recoveryCodes' => $recoveryCodes,
            'secret' => $formattedSecret,
            'secretRaw' => $secretRaw,
            'otpAuthUri' => $otpAuthUri,
            'qrCodeUrl' => $qrCodeUrl,
        ]);
    }

    public function login(): void
    {
        $login = trim((string) ($_POST['login'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $banService = new BanService();
        $limiter = new RateLimiter();
        $context = ClientContext::summary();
        $ipKey = $this->rateLimitKey('user-login:ip', (string) ($context['ip_address'] ?? 'unknown'));
        $loginKey = $this->rateLimitKey('user-login:login', $login !== '' ? strtolower($login) : 'unknown');
        $retryAfter = max((int) ($limiter->isBlocked($ipKey) ?? 0), (int) ($limiter->isBlocked($loginKey) ?? 0));

        if ($retryAfter > 0) {
            flash_set('error', 'Muitas tentativas de login. Aguarde ' . $retryAfter . ' segundo(s) e tente novamente.');
            redirect('/login');
        }

        if (!Captcha::validate('user-login', (string) ($_POST['captcha_id'] ?? ''), (string) ($_POST['captcha_answer'] ?? ''))) {
            $limiter->hit($ipKey, 8, 900, 1800);
            flash_set('error', 'CAPTCHA inválido ou expirado. Tente novamente.');
            redirect('/login');
        }

        $blockedMessage = $banService->canProceedWithAuth('login', $login);
        if ($blockedMessage !== null) {
            flash_set('error', $blockedMessage);
            redirect('/login');
        }

        $repo = new UserRepository();
        $user = $repo->findByLogin($login);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $limiter->hit($ipKey, 8, 900, 1800);
            $limiter->hit($loginKey, 8, 900, 1800);
            $banService->recordInvalidLogin($login);
            flash_set('error', 'Credenciais inválidas.');
            redirect('/login');
        }

        if ((new UserEmailVerificationService())->isPendingVerification($user)) {
            flash_set('error', 'Você precisa verificar seu e-mail antes de entrar na conta.');
            redirect('/verify-email/pending?email=' . urlencode((string) ($user['email'] ?? '')));
        }

        if (!empty($user['is_banned'])) {
            $banService->recordBannedAccountAttempt($user, $login);
            $reason = trim((string) ($user['banned_reason'] ?? ''));
            $message = $reason !== '' ? 'Conta banida: ' . $reason : 'Conta banida.';
            flash_set('error', $message);
            redirect('/login');
        }

        $limiter->clear($ipKey);
        $limiter->clear($loginKey);

        if (!empty($user['two_factor_enabled'])) {
            $this->storePendingUser($user);
            redirect('/2fa/verify');
        }

        $this->finalizeUserLogin($user);
        redirect('/usuario');
    }

    public function verifyTwoFactor(): void
    {
        $pending = $this->pendingUser(true);
        $userId = (int) ($pending['id'] ?? 0);
        $user = (new UserRepository())->findById($userId);
        if (!$user) {
            $this->clearPendingUser();
            flash_set('error', 'Sessao de autenticacao expirada.');
            redirect('/login');
        }

        if (empty($user['two_factor_enabled'])) {
            $this->clearPendingUser();
            flash_set('error', 'O 2FA desta conta nao esta ativo.');
            redirect('/login');
        }

        if (!empty($user['is_banned'])) {
            $this->clearPendingUser();
            flash_set('error', 'Esta conta nao pode concluir o login no momento.');
            redirect('/login');
        }

        $limiter = new RateLimiter();
        $key = $this->rateLimitKey('user-2fa:verify', (string) $userId);
        $retryAfter = (int) ($limiter->isBlocked($key) ?? 0);
        if ($retryAfter > 0) {
            flash_set('error', 'Muitas tentativas no 2FA. Aguarde ' . $retryAfter . ' segundo(s) e tente novamente.');
            redirect('/2fa/verify');
        }

        $input = trim((string) ($_POST['two_factor_code'] ?? ''));
        $twoFactor = new TwoFactorService();
        $verified = $twoFactor->verifyCode((string) ($user['two_factor_secret'] ?? ''), $input);

        if (!$verified) {
            $storedRecoveryCodes = $this->decodeRecoveryCodes((string) ($user['two_factor_recovery_codes'] ?? ''));
            $recoveryResult = $twoFactor->consumeRecoveryCode($input, $storedRecoveryCodes);
            if ($recoveryResult['ok']) {
                (new UserRepository())->updateTwoFactorRecoveryCodes($userId, $recoveryResult['remaining_hashes']);
                $limiter->clear($key);
                $this->finalizeUserLogin($user);
                flash_set('success', 'Login concluido com codigo de recuperacao. Guarde novos codigos em local seguro.');
                redirect('/usuario');
            }
        }

        if (!$verified) {
            $limiter->hit($key, 5, 600, 900);
            flash_set('error', 'Codigo 2FA ou codigo de recuperacao invalido.');
            redirect('/2fa/verify');
        }

        $limiter->clear($key);
        $this->finalizeUserLogin($user);
        redirect('/usuario');
    }

    public function setupTwoFactor(): void
    {
        require_user();

        $sessionUser = user_session() ?? [];
        $userId = (int) ($sessionUser['id'] ?? 0);
        $repo = new UserRepository();
        $user = $repo->findById($userId);
        if (!$user) {
            Session::destroy();
            redirect('/login');
        }

        if (!empty($user['two_factor_enabled'])) {
            redirect('/usuario/2fa');
        }

        $secret = $this->pendingSetupSecret();
        $verificationCode = trim((string) ($_POST['verification_code'] ?? ''));
        $twoFactor = new TwoFactorService();
        $limiter = new RateLimiter();
        $key = $this->rateLimitKey('user-2fa:setup', (string) $userId);
        $retryAfter = (int) ($limiter->isBlocked($key) ?? 0);

        if ($retryAfter > 0) {
            flash_set('error', 'Muitas tentativas de ativacao do 2FA. Aguarde ' . $retryAfter . ' segundo(s) e tente novamente.');
            redirect('/usuario/2fa');
        }

        if (!$twoFactor->verifyCode($secret, $verificationCode)) {
            $limiter->hit($key, 5, 600, 900);
            flash_set('error', 'Codigo do aplicativo autenticador invalido.');
            redirect('/usuario/2fa');
        }

        $recoveryCodes = $twoFactor->generateRecoveryCodes();
        $repo->activateTwoFactor($userId, $secret, $twoFactor->hashRecoveryCodes($recoveryCodes));
        $limiter->clear($key);
        $_SESSION[self::RECOVERY_CODES_SESSION_KEY] = $recoveryCodes;
        unset($_SESSION[self::PENDING_SETUP_SECRET_KEY]);

        $_SESSION['user']['two_factor_enabled'] = 1;
        $_SESSION['user']['two_factor_verified_at'] = time();

        flash_set('success', '2FA ativado com sucesso. Guarde os codigos de recuperacao exibidos abaixo.');
        redirect('/usuario/2fa');
    }

    public function register(): void
    {
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
        $banService = new BanService();
        $limiter = new RateLimiter();
        $registerKey = $this->rateLimitKey('user-register:ip', (string) (ClientContext::summary()['ip_address'] ?? 'unknown'));
        $registerRetryAfter = (int) ($limiter->isBlocked($registerKey) ?? 0);

        if ($registerRetryAfter > 0) {
            flash_set('error', 'Muitas tentativas de cadastro. Aguarde ' . $registerRetryAfter . ' segundo(s) e tente novamente.');
            redirect('/register');
        }

        if (!Captcha::validate('user-register', (string) ($_POST['captcha_id'] ?? ''), (string) ($_POST['captcha_answer'] ?? ''))) {
            $limiter->hit($registerKey, 5, 3600, 7200);
            flash_set('error', 'CAPTCHA inválido ou expirado. Tente novamente.');
            redirect('/register');
        }

        $blockedMessage = $banService->canProceedWithAuth('register', null, $username, $email);
        if ($blockedMessage !== null) {
            flash_set('error', $blockedMessage);
            redirect('/register');
        }

        $limiter->hit($registerKey, 5, 3600, 7200);

        if ($username === '' || $email === '' || $password === '' || $passwordConfirm === '') {
            flash_set('error', 'Preencha todos os campos.');
            redirect('/register');
        }

        if (!preg_match('/^[a-zA-Z0-9._]{3,30}$/', $username)) {
            flash_set('error', 'Usuário inválido. Use de 3 a 30 caracteres com letras, números, ponto ou _.');
            redirect('/register');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', 'E-mail invalido.');
            redirect('/register');
        }

        if (strlen($password) < 8) {
            flash_set('error', 'Senha muito curta. Use pelo menos 8 caracteres.');
            redirect('/register');
        }

        if ($password !== $passwordConfirm) {
            flash_set('error', 'As senhas nao conferem.');
            redirect('/register');
        }

        $repo = new UserRepository();
        if ($repo->existsByUsername($username)) {
            flash_set('error', 'Este usuário já existe.');
            redirect('/register');
        }

        if ($repo->existsByEmail($email)) {
            flash_set('error', 'Este e-mail ja esta em uso. Se a conta ainda nao foi verificada, reenvie o e-mail de confirmacao.');
            redirect('/register');
        }

        $pdo = Database::pdo();
        $verificationService = new UserEmailVerificationService();
        $context = ClientContext::summary();

        $pdo->beginTransaction();

        try {
            $userId = $repo->create([
                'username' => $username,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'user',
                'email_verified_at' => null,
                'email_verification_token_hash' => null,
                'email_verification_sent_at' => null,
            ]);

            $repo->updateSecurityContext($userId, $context['ip_address'], $context['fingerprint_hash'], false);
            $user = $repo->findById($userId);
            if (!$user) {
                throw new \RuntimeException('Conta não encontrada após o cadastro.');
            }

            $dispatch = $verificationService->issueForUser($user);
            if (!$dispatch['ok']) {
                throw new \RuntimeException((string) ($dispatch['message'] ?? 'Não foi possível enviar o e-mail de verificação.'));
            }

            $pdo->commit();

            (new UserAccessLogRepository())->create([
                'user_id' => $userId,
                'username_snapshot' => $username,
                'email_snapshot' => $email,
                'ip_address' => (string) ($context['ip_address'] ?? ''),
                'fingerprint_hash' => (string) ($context['fingerprint_hash'] ?? ''),
                'user_agent' => (string) ($context['user_agent'] ?? ''),
                'route' => (string) ($context['route'] ?? ''),
                'action' => 'register_pending_email_verification',
            ]);

            $message = 'Cadastro realizado. Verifique seu e-mail para concluir a criação da conta.';
            if (($dispatch['delivery'] ?? '') === 'log' && !empty($dispatch['log_path'])) {
                $message .= ' Ambiente local detectado: o link foi salvo em ' . $dispatch['log_path'] . '.';
            }

            flash_set('success', $message);
            redirect('/verify-email/pending?email=' . urlencode($email));
        } catch (\PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $sqlState = (string) $exception->getCode();
            $message = strtolower($exception->getMessage());
            $isDuplicate = $sqlState === '23000' || str_contains($message, 'duplicate') || str_contains($message, 'unique');

            if ($isDuplicate) {
                flash_set('error', 'Usuário ou e-mail já está em uso. Cada conta precisa ter um usuário e um e-mail únicos.');
                redirect('/register');
            }

            flash_set('error', 'Não foi possível criar a conta agora.');
            redirect('/register');
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            flash_set('error', $exception->getMessage() !== '' ? $exception->getMessage() : 'Não foi possível criar a conta agora.');
            redirect('/register');
        }
    }

    public function verifyEmail(): void
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        $verificationService = new UserEmailVerificationService();
        $result = $verificationService->verifyToken($token);

        if (!empty($result['ok'])) {
            $user = (array) ($result['user'] ?? []);
            $context = ClientContext::summary();
            (new UserAccessLogRepository())->create([
                'user_id' => (int) ($user['id'] ?? 0),
                'username_snapshot' => (string) ($user['username'] ?? ''),
                'email_snapshot' => (string) ($user['email'] ?? ''),
                'ip_address' => (string) ($context['ip_address'] ?? ''),
                'fingerprint_hash' => (string) ($context['fingerprint_hash'] ?? ''),
                'user_agent' => (string) ($context['user_agent'] ?? ''),
                'route' => (string) ($context['route'] ?? ''),
                'action' => 'email_verified',
            ]);
        }

        View::render('auth/verify_email_result', [
            'verificationOk' => !empty($result['ok']),
            'verificationMessage' => (string) ($result['message'] ?? 'Não foi possível validar este link.'),
            'verificationReason' => (string) ($result['reason'] ?? ''),
            'verificationEmail' => (string) (($result['user']['email'] ?? $_GET['email'] ?? '')),
        ]);
    }

    public function resendVerification(): void
    {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', 'Informe um e-mail válido para reenviar a verificação.');
            redirect('/verify-email/pending');
        }

        $limiter = new RateLimiter();
        $context = ClientContext::summary();
        $ipKey = $this->rateLimitKey('user-verify-resend:ip', (string) ($context['ip_address'] ?? 'unknown'));
        $emailKey = $this->rateLimitKey('user-verify-resend:email', $email);
        $retryAfter = max((int) ($limiter->isBlocked($ipKey) ?? 0), (int) ($limiter->isBlocked($emailKey) ?? 0));

        if ($retryAfter > 0) {
            flash_set('error', 'Aguarde ' . $retryAfter . ' segundo(s) antes de pedir um novo envio.');
            redirect('/verify-email/pending?email=' . urlencode($email));
        }

        $repo = new UserRepository();
        $user = $repo->findByEmail($email);
        if (!$user) {
            flash_set('error', 'Nenhuma conta encontrada para este e-mail.');
            redirect('/verify-email/pending?email=' . urlencode($email));
        }

        if (!(new UserEmailVerificationService())->isPendingVerification($user)) {
            flash_set('success', 'Este e-mail já está verificado. Você pode entrar normalmente.');
            redirect('/login');
        }

        $limiter->hit($ipKey, 3, 900, 1800);
        $limiter->hit($emailKey, 3, 900, 1800);

        $dispatch = (new UserEmailVerificationService())->issueForUser($user);
        if (!$dispatch['ok']) {
            flash_set('error', (string) ($dispatch['message'] ?? 'Não foi possível reenviar o e-mail de verificação.'));
            redirect('/verify-email/pending?email=' . urlencode($email));
        }

        (new UserAccessLogRepository())->create([
            'user_id' => (int) ($user['id'] ?? 0),
            'username_snapshot' => (string) ($user['username'] ?? ''),
            'email_snapshot' => (string) ($user['email'] ?? ''),
            'ip_address' => (string) ($context['ip_address'] ?? ''),
            'fingerprint_hash' => (string) ($context['fingerprint_hash'] ?? ''),
            'user_agent' => (string) ($context['user_agent'] ?? ''),
            'route' => (string) ($context['route'] ?? ''),
            'action' => 'email_verification_resent',
        ]);

        $limiter->clear($ipKey);
        $limiter->clear($emailKey);

        $message = 'Novo e-mail de verificação enviado com sucesso.';
        if (($dispatch['delivery'] ?? '') === 'log' && !empty($dispatch['log_path'])) {
            $message .= ' Ambiente local detectado: o link foi salvo em ' . $dispatch['log_path'] . '.';
        }

        flash_set('success', $message);
        redirect('/verify-email/pending?email=' . urlencode($email));
    }

    public function logout(): void
    {
        Session::destroy();
        redirect('/login');
    }

    public function consumeRecoveryCodesForDisplay(): array
    {
        $codes = $_SESSION[self::RECOVERY_CODES_SESSION_KEY] ?? [];
        unset($_SESSION[self::RECOVERY_CODES_SESSION_KEY]);

        return is_array($codes) ? array_values(array_filter(array_map('strval', $codes))) : [];
    }

    private function storePendingUser(array $user): void
    {
        $_SESSION[self::PENDING_SESSION_KEY] = [
            'id' => (int) ($user['id'] ?? 0),
            'username' => (string) ($user['username'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'role' => (string) ($user['role'] ?? 'user'),
            'issued_at' => time(),
        ];
    }

    private function pendingUser(bool $redirectOnFailure = false): ?array
    {
        $pending = $_SESSION[self::PENDING_SESSION_KEY] ?? null;
        if (!is_array($pending)) {
            if ($redirectOnFailure) {
                flash_set('error', 'Sua sessao de autenticacao expirou. Faca login novamente.');
                redirect('/login');
            }

            return null;
        }

        $issuedAt = (int) ($pending['issued_at'] ?? 0);
        if ($issuedAt <= 0 || (time() - $issuedAt) > self::PENDING_TTL_SECONDS) {
            $this->clearPendingUser();
            if ($redirectOnFailure) {
                flash_set('error', 'Sua sessao de autenticacao expirou. Faca login novamente.');
                redirect('/login');
            }

            return null;
        }

        return $pending;
    }

    private function pendingSetupSecret(): string
    {
        $secret = strtoupper(trim((string) ($_SESSION[self::PENDING_SETUP_SECRET_KEY] ?? '')));
        if ($secret === '') {
            $secret = (new TwoFactorService())->generateSecret();
            $_SESSION[self::PENDING_SETUP_SECRET_KEY] = $secret;
        }

        return $secret;
    }

    private function clearPendingUser(): void
    {
        unset($_SESSION[self::PENDING_SESSION_KEY]);
    }

    private function finalizeUserLogin(array $user): void
    {
        $this->clearPendingUser();
        Session::regenerate();
        unset($_SESSION['admin']);
        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'issued_at' => time(),
            'two_factor_enabled' => !empty($user['two_factor_enabled']) ? 1 : 0,
            'two_factor_verified_at' => !empty($user['two_factor_enabled']) ? time() : null,
        ];

        $banService = new BanService();
        $context = $banService->updateUserSecurityContext((int) ($user['id'] ?? 0));
        (new UserAccessLogRepository())->create([
            'user_id' => (int) ($user['id'] ?? 0),
            'username_snapshot' => (string) ($user['username'] ?? ''),
            'email_snapshot' => (string) ($user['email'] ?? ''),
            'ip_address' => (string) ($context['ip_address'] ?? ''),
            'fingerprint_hash' => (string) ($context['fingerprint_hash'] ?? ''),
            'user_agent' => (string) ($context['user_agent'] ?? ''),
            'route' => (string) ($context['route'] ?? ''),
            'action' => 'login_success',
        ]);
    }

    private function decodeRecoveryCodes(string $payload): array
    {
        $decoded = json_decode($payload, true);

        return is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded))) : [];
    }

    private function rateLimitKey(string $prefix, string $value): string
    {
        return $prefix . ':' . hash('sha256', strtolower(trim($value)));
    }
}
