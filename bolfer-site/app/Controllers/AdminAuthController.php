<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AdminInviteKeyRepository;
use App\Repositories\AdminRepository;
use App\Services\TwoFactorService;
use App\Support\Captcha;
use App\Support\ClientContext;
use App\Support\RateLimiter;
use App\Support\Session;
use App\Support\View;

final class AdminAuthController
{
    private const PENDING_SESSION_KEY = '_admin_pending_2fa';
    private const PENDING_SETUP_SECRET_KEY = '_admin_pending_2fa_secret';
    private const RECOVERY_CODES_SESSION_KEY = '_admin_2fa_recovery_codes_once';
    private const PENDING_TTL_SECONDS = 900;

    public function showLogin(): void
    {
        if (admin_user()) {
            redirect('/admin/dashboard');
        }

        $repo = new AdminRepository();
        $hasAdmins = $repo->countAll() > 0;
        $showSetup = !$hasAdmins && $this->isSetupEnabled();

        View::render('admin/login', [
            'showSetup' => $showSetup,
        ]);
    }

    public function showRegister(): void
    {
        $repo = new AdminRepository();
        $hasAdmins = $repo->countAll() > 0;
        if (!$hasAdmins) {
            redirect('/admin/setup');
        }

        $inviteKey = strtoupper(trim((string) ($_GET['invite'] ?? '')));

        View::render('admin/register', [
            'inviteKey' => $inviteKey,
        ]);
    }

    public function showSetup(): void
    {
        $repo = new AdminRepository();
        if ($repo->countAll() > 0) {
            redirect('/admin/login');
        }

        if (!$this->isSetupEnabled()) {
            flash_set('error', 'Setup inicial desativado. Ative ALLOW_ADMIN_SETUP=1 temporariamente para criar o admin fundador.');
            redirect('/admin/login');
        }

        View::render('admin/setup');
    }

    public function showTwoFactorSetup(): void
    {
        $pending = $this->pendingAdmin(true);
        $admin = (new AdminRepository())->findById((int) ($pending['id'] ?? 0));
        if (!$admin) {
            $this->clearPendingAdmin();
            flash_set('error', 'Sessao de autenticacao expirada.');
            redirect('/admin/login');
        }

        if (!empty($admin['two_factor_enabled'])) {
            redirect('/admin/2fa/verify');
        }

        $secret = $this->pendingSetupSecret();
        $twoFactor = new TwoFactorService();
        $otpIssuer = trim((string) env('APP_NAME', 'Bolfer Official'));
        if ($otpIssuer === '') {
            $otpIssuer = 'Bolfer Official';
        }
        $otpAuthUri = $twoFactor->otpAuthUri((string) ($admin['username'] ?? 'admin'), $secret, $otpIssuer . ' Admin');

        View::render('admin/two_factor_setup', [
            'adminEmail' => (string) ($admin['username'] ?? ''),
            'secret' => $twoFactor->formatSecret($secret),
            'secretRaw' => $secret,
            'otpAuthUri' => $otpAuthUri,
            'qrCodeUrl' => 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' . rawurlencode($otpAuthUri),
        ]);
    }

    public function showTwoFactorVerify(): void
    {
        $pending = $this->pendingAdmin(true);
        $admin = (new AdminRepository())->findById((int) ($pending['id'] ?? 0));
        if (!$admin) {
            $this->clearPendingAdmin();
            flash_set('error', 'Sessao de autenticacao expirada.');
            redirect('/admin/login');
        }

        if (empty($admin['two_factor_enabled'])) {
            redirect('/admin/2fa/setup');
        }

        View::render('admin/two_factor_verify', [
            'adminEmail' => (string) ($admin['username'] ?? ''),
        ]);
    }

    public function login(): void
    {
        $username = strtolower(trim((string) ($_POST['username'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $limiter = new RateLimiter();
        $ipKey = $this->rateLimitKey('admin-login:ip', (string) (ClientContext::summary()['ip_address'] ?? 'unknown'));
        $userKey = $this->rateLimitKey('admin-login:user', $username !== '' ? $username : 'unknown');
        $retryAfter = max((int) ($limiter->isBlocked($ipKey) ?? 0), (int) ($limiter->isBlocked($userKey) ?? 0));

        if ($retryAfter > 0) {
            flash_set('error', 'Muitas tentativas no login admin. Aguarde ' . $retryAfter . ' segundo(s) e tente novamente.');
            redirect('/admin/login');
        }

        if (!$this->validateCaptcha('admin-login', '/admin/login')) {
            $limiter->hit($ipKey, 5, 900, 1800);
            redirect('/admin/login');
        }

        $admin = (new AdminRepository())->findByUsername($username);
        if (!$admin || !password_verify($password, (string) ($admin['password_hash'] ?? ''))) {
            $limiter->hit($ipKey, 5, 900, 1800);
            $limiter->hit($userKey, 5, 900, 1800);
            flash_set('error', 'Credenciais invalidas.');
            redirect('/admin/login');
        }

        $limiter->clear($ipKey);
        $limiter->clear($userKey);
        $this->storePendingAdmin($admin);

        if (!empty($admin['two_factor_enabled'])) {
            redirect('/admin/2fa/verify');
        }

        $_SESSION[self::PENDING_SETUP_SECRET_KEY] = (new TwoFactorService())->generateSecret();
        flash_set('success', 'Senha validada. Agora ative o 2FA para concluir o acesso ao painel.');
        redirect('/admin/2fa/setup');
    }

    public function verifyTwoFactor(): void
    {
        $pending = $this->pendingAdmin(true);
        $adminId = (int) ($pending['id'] ?? 0);
        $admin = (new AdminRepository())->findById($adminId);
        if (!$admin) {
            $this->clearPendingAdmin();
            flash_set('error', 'Sessao de autenticacao expirada.');
            redirect('/admin/login');
        }

        if (empty($admin['two_factor_enabled'])) {
            redirect('/admin/2fa/setup');
        }

        $limiter = new RateLimiter();
        $key = $this->rateLimitKey('admin-2fa:verify', (string) $adminId);
        $retryAfter = (int) ($limiter->isBlocked($key) ?? 0);
        if ($retryAfter > 0) {
            flash_set('error', 'Muitas tentativas no 2FA. Aguarde ' . $retryAfter . ' segundo(s) e tente novamente.');
            redirect('/admin/2fa/verify');
        }

        $input = trim((string) ($_POST['two_factor_code'] ?? ''));
        $twoFactor = new TwoFactorService();
        $verified = $twoFactor->verifyCode((string) ($admin['two_factor_secret'] ?? ''), $input);

        if (!$verified) {
            $storedRecoveryCodes = $this->decodeRecoveryCodes((string) ($admin['two_factor_recovery_codes'] ?? ''));
            $recoveryResult = $twoFactor->consumeRecoveryCode($input, $storedRecoveryCodes);
            if ($recoveryResult['ok']) {
                (new AdminRepository())->updateTwoFactorRecoveryCodes($adminId, $recoveryResult['remaining_hashes']);
                $limiter->clear($key);
                $this->finalizeAdminLogin($admin);
                flash_set('success', 'Login concluido com codigo de recuperacao. Gere novos codigos assim que possivel.');
                redirect('/admin/dashboard');
            }
        }

        if (!$verified) {
            $limiter->hit($key, 5, 600, 900);
            flash_set('error', 'Codigo 2FA ou codigo de recuperacao invalido.');
            redirect('/admin/2fa/verify');
        }

        $limiter->clear($key);
        $this->finalizeAdminLogin($admin);
        redirect('/admin/dashboard');
    }

    public function setupTwoFactor(): void
    {
        $pending = $this->pendingAdmin(true);
        $adminId = (int) ($pending['id'] ?? 0);
        $admin = (new AdminRepository())->findById($adminId);
        if (!$admin) {
            $this->clearPendingAdmin();
            flash_set('error', 'Sessao de autenticacao expirada.');
            redirect('/admin/login');
        }

        if (!empty($admin['two_factor_enabled'])) {
            redirect('/admin/2fa/verify');
        }

        $secret = $this->pendingSetupSecret();
        $verificationCode = trim((string) ($_POST['verification_code'] ?? ''));
        $twoFactor = new TwoFactorService();
        $limiter = new RateLimiter();
        $key = $this->rateLimitKey('admin-2fa:setup', (string) $adminId);
        $retryAfter = (int) ($limiter->isBlocked($key) ?? 0);

        if ($retryAfter > 0) {
            flash_set('error', 'Muitas tentativas de ativacao do 2FA. Aguarde ' . $retryAfter . ' segundo(s) e tente novamente.');
            redirect('/admin/2fa/setup');
        }

        if (!$twoFactor->verifyCode($secret, $verificationCode)) {
            $limiter->hit($key, 5, 600, 900);
            flash_set('error', 'Codigo do aplicativo autenticador invalido.');
            redirect('/admin/2fa/setup');
        }

        $recoveryCodes = $twoFactor->generateRecoveryCodes();
        (new AdminRepository())->activateTwoFactor($adminId, $secret, $twoFactor->hashRecoveryCodes($recoveryCodes));
        $limiter->clear($key);
        $_SESSION[self::RECOVERY_CODES_SESSION_KEY] = $recoveryCodes;

        $admin = (new AdminRepository())->findById($adminId);
        if (!$admin) {
            $this->clearPendingAdmin();
            flash_set('error', 'Nao foi possivel finalizar a ativacao do 2FA.');
            redirect('/admin/login');
        }

        $this->finalizeAdminLogin($admin);
        flash_set('success', '2FA ativado com sucesso. Guarde os codigos de recuperacao exibidos no dashboard.');
        redirect('/admin/dashboard');
    }

    public function register(): void
    {
        $username = strtolower(trim((string) ($_POST['username'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
        $registerKeyInput = trim((string) ($_POST['register_key'] ?? ''));

        $repo = new AdminRepository();
        $inviteRepo = new AdminInviteKeyRepository();
        if ($repo->countAll() === 0) {
            redirect('/admin/setup');
        }

        if (!$this->validateCaptcha('admin-register', '/admin/register')) {
            redirect('/admin/register');
        }

        if ($username === '' || $password === '' || $passwordConfirm === '') {
            flash_set('error', 'Preencha todos os campos.');
            redirect('/admin/register');
        }

        if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', 'E-mail invalido.');
            redirect('/admin/register');
        }

        if (!$this->isStrongAdminPassword($password)) {
            flash_set('error', 'Senha admin fraca. Use no minimo 14 caracteres com maiuscula, minuscula, numero e simbolo.');
            redirect('/admin/register');
        }

        if ($password !== $passwordConfirm) {
            flash_set('error', 'As senhas nao conferem.');
            redirect('/admin/register');
        }

        if ($registerKeyInput === '') {
            flash_set('error', 'Informe a chave de registro.');
            redirect('/admin/register');
        }

        if ($repo->existsByUsername($username)) {
            flash_set('error', 'Este admin ja existe.');
            redirect('/admin/register');
        }

        $pdo = \App\Support\Database::pdo();
        $pdo->beginTransaction();
        try {
            $invite = $inviteRepo->findAvailable($registerKeyInput, true);
            if (!$invite) {
                $pdo->rollBack();
                flash_set('error', 'Chave de registro invalida ou ja utilizada.');
                redirect('/admin/register');
            }

            $adminId = $repo->create([
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'staff',
            ]);

            if (!$inviteRepo->markUsed((int) $invite['id'], $adminId)) {
                $pdo->rollBack();
                flash_set('error', 'Chave de registro ja utilizada.');
                redirect('/admin/register');
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash_set('error', 'Erro ao criar admin.');
            redirect('/admin/register');
        }

        flash_set('success', 'Admin criado com sucesso. Faca login para continuar.');
        redirect('/admin/login');
    }

    public function setup(): void
    {
        $repo = new AdminRepository();
        if ($repo->countAll() > 0) {
            redirect('/admin/login');
        }

        if (!$this->isSetupEnabled()) {
            flash_set('error', 'Setup inicial desativado. Ative ALLOW_ADMIN_SETUP=1 temporariamente para criar o admin fundador.');
            redirect('/admin/login');
        }

        if (!$this->validateCaptcha('admin-setup', '/admin/setup')) {
            redirect('/admin/setup');
        }

        $username = strtolower(trim((string) ($_POST['username'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        if ($username === '' || $password === '' || $passwordConfirm === '') {
            flash_set('error', 'Preencha todos os campos.');
            redirect('/admin/setup');
        }

        if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', 'E-mail invalido.');
            redirect('/admin/setup');
        }

        if (!$this->isStrongAdminPassword($password)) {
            flash_set('error', 'Senha admin fraca. Use no minimo 14 caracteres com maiuscula, minuscula, numero e simbolo.');
            redirect('/admin/setup');
        }

        if ($password !== $passwordConfirm) {
            flash_set('error', 'As senhas nao conferem.');
            redirect('/admin/setup');
        }

        $repo->create([
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'founder',
        ]);

        flash_set('success', 'Conta fundadora criada. Faca login para ativar o 2FA e concluir o acesso.');
        redirect('/admin/login');
    }

    public function logout(): void
    {
        Session::destroy();
        redirect('/admin/login');
    }

    public function consumeRecoveryCodesForDisplay(): array
    {
        $codes = $_SESSION[self::RECOVERY_CODES_SESSION_KEY] ?? [];
        unset($_SESSION[self::RECOVERY_CODES_SESSION_KEY]);

        return is_array($codes) ? array_values(array_filter(array_map('strval', $codes))) : [];
    }

    private function isSetupEnabled(): bool
    {
        return env('ALLOW_ADMIN_SETUP', '0') === '1';
    }

    private function validateCaptcha(string $scope, string $redirectPath): bool
    {
        if (Captcha::validate($scope, (string) ($_POST['captcha_id'] ?? ''), (string) ($_POST['captcha_answer'] ?? ''))) {
            return true;
        }

        flash_set('error', 'Captcha invalido ou expirado. Resolva a conta e tente novamente.');
        redirect($redirectPath);
    }

    private function storePendingAdmin(array $admin): void
    {
        $_SESSION[self::PENDING_SESSION_KEY] = [
            'id' => (int) ($admin['id'] ?? 0),
            'username' => (string) ($admin['username'] ?? ''),
            'role' => (string) ($admin['role'] ?? 'staff'),
            'issued_at' => time(),
        ];
    }

    private function pendingAdmin(bool $redirectOnFailure = false): ?array
    {
        $pending = $_SESSION[self::PENDING_SESSION_KEY] ?? null;
        if (!is_array($pending)) {
            if ($redirectOnFailure) {
                flash_set('error', 'Sua sessao de autenticacao expirou. Faca login novamente.');
                redirect('/admin/login');
            }

            return null;
        }

        $issuedAt = (int) ($pending['issued_at'] ?? 0);
        if ($issuedAt <= 0 || (time() - $issuedAt) > self::PENDING_TTL_SECONDS) {
            $this->clearPendingAdmin();
            if ($redirectOnFailure) {
                flash_set('error', 'Sua sessao de autenticacao expirou. Faca login novamente.');
                redirect('/admin/login');
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

    private function clearPendingAdmin(): void
    {
        unset($_SESSION[self::PENDING_SESSION_KEY], $_SESSION[self::PENDING_SETUP_SECRET_KEY]);
    }

    private function finalizeAdminLogin(array $admin): void
    {
        $this->clearPendingAdmin();
        Session::regenerate();
        unset($_SESSION['user']);
        $_SESSION['admin'] = [
            'id' => $admin['id'],
            'username' => $admin['username'],
            'role' => $admin['role'],
            'discord_activity_enabled' => $admin['discord_activity_enabled'] ?? 1,
            'discord_activity_display_name' => $admin['discord_activity_display_name'] ?? null,
            'two_factor_enabled' => !empty($admin['two_factor_enabled']) ? 1 : 0,
            'two_factor_verified_at' => time(),
        ];

        (new AdminRepository())->updateLastLogin((int) ($admin['id'] ?? 0));
    }

    private function decodeRecoveryCodes(string $payload): array
    {
        $decoded = json_decode($payload, true);

        return is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded))) : [];
    }

    private function isStrongAdminPassword(string $password): bool
    {
        return strlen($password) >= 14
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[0-9]/', $password) === 1
            && preg_match('/[^A-Za-z0-9]/', $password) === 1;
    }

    private function rateLimitKey(string $prefix, string $value): string
    {
        return $prefix . ':' . hash('sha256', strtolower(trim($value)));
    }
}
