<?php

declare(strict_types=1);

namespace App\Controllers\Api\Desktop;

use App\Repositories\AdminRepository;
use App\Services\AdminApiAuthService;
use App\Services\TwoFactorService;
use App\Support\ClientContext;
use App\Support\DesktopApiPresenter;
use App\Support\RateLimiter;

final class AuthController
{
    public function login(): void
    {
        $payload = request_data();
        $username = strtolower(trim((string) ($payload['username'] ?? '')));
        $password = (string) ($payload['password'] ?? '');
        $twoFactorCode = trim((string) ($payload['two_factor_code'] ?? ''));
        $deviceName = trim((string) ($payload['device_name'] ?? 'Bolfer Desktop'));
        $limiter = new RateLimiter();
        $ipKey = $this->rateLimitKey('desktop-admin-login:ip', (string) (ClientContext::summary()['ip_address'] ?? 'unknown'));
        $userKey = $this->rateLimitKey('desktop-admin-login:user', $username !== '' ? $username : 'unknown');
        $retryAfter = max((int) ($limiter->isBlocked($ipKey) ?? 0), (int) ($limiter->isBlocked($userKey) ?? 0));

        if ($retryAfter > 0) {
            json_response([
                'ok' => false,
                'message' => 'Muitas tentativas. Aguarde ' . $retryAfter . ' segundo(s) e tente novamente.',
            ], 429);
        }

        if ($username === '' || $password === '') {
            json_response([
                'ok' => false,
                'message' => 'Informe usuario e senha.',
            ], 422);
        }

        $admin = (new AdminRepository())->findByUsername($username);
        if (!$admin || !password_verify($password, (string) ($admin['password_hash'] ?? ''))) {
            $limiter->hit($ipKey, 5, 900, 1800);
            $limiter->hit($userKey, 5, 900, 1800);
            json_response([
                'ok' => false,
                'message' => 'Credenciais invalidas.',
            ], 401);
        }

        if ($this->requiresTwoFactorSetup($admin)) {
            $this->twoFactorChallenge(
                $admin,
                'Esta conta admin precisa ativar o 2FA no painel web antes de usar o desktop.',
                403,
                true
            );
        }

        $requiresTwoFactor = $this->requiresTwoFactor($admin);
        if ($requiresTwoFactor && $twoFactorCode === '') {
            $this->twoFactorChallenge(
                $admin,
                'Senha validada. Agora informe o codigo 2FA ou um codigo de recuperacao para entrar no desktop.',
                428
            );
        }

        $verified = true;
        if ($requiresTwoFactor) {
            $twoFactor = new TwoFactorService();
            $verified = $twoFactor->verifyCode((string) ($admin['two_factor_secret'] ?? ''), $twoFactorCode);

            if (!$verified) {
                $storedRecoveryCodes = json_decode((string) ($admin['two_factor_recovery_codes'] ?? '[]'), true);
                $storedRecoveryCodes = is_array($storedRecoveryCodes) ? $storedRecoveryCodes : [];
                $recoveryResult = $twoFactor->consumeRecoveryCode($twoFactorCode, $storedRecoveryCodes);

                if ($recoveryResult['ok']) {
                    (new AdminRepository())->updateTwoFactorRecoveryCodes((int) ($admin['id'] ?? 0), $recoveryResult['remaining_hashes']);
                    $verified = true;
                }
            }
        }

        if (!$verified) {
            $limiter->hit($ipKey, 5, 900, 1800);
            $limiter->hit($userKey, 5, 900, 1800);
            $this->twoFactorChallenge(
                $admin,
                'Codigo 2FA ou codigo de recuperacao invalido.',
                401
            );
        }

        $limiter->clear($ipKey);
        $limiter->clear($userKey);
        (new AdminRepository())->updateLastLogin((int) ($admin['id'] ?? 0));
        $issued = (new AdminApiAuthService())->issueToken($admin, $deviceName);
        $admin['last_login_at'] = date('Y-m-d H:i:s');

        json_response([
            'ok' => true,
            'data' => [
                'token' => $issued['plain_text_token'],
                'tokenName' => $issued['token_name'],
                'expiresAt' => $issued['expires_at'],
                'admin' => DesktopApiPresenter::admin($admin),
                'permissions' => DesktopApiPresenter::permissions($admin),
                'twoFactorVerified' => $requiresTwoFactor,
            ],
        ]);
    }

    public function me(): void
    {
        $context = require_admin_api();
        $admin = $context['admin'] ?? [];

        json_response([
            'ok' => true,
            'data' => [
                'admin' => DesktopApiPresenter::admin($admin),
                'permissions' => DesktopApiPresenter::permissions($admin),
                'token' => [
                    'name' => (string) (($context['token']['name'] ?? 'Bolfer Desktop')),
                    'expiresAt' => $context['token']['expires_at'] ?? null,
                    'lastUsedAt' => $context['token']['last_used_at'] ?? null,
                ],
            ],
        ]);
    }

    public function logout(): void
    {
        (new AdminApiAuthService())->revoke(bearer_token());

        json_response([
            'ok' => true,
            'message' => 'Sessao encerrada com sucesso.',
        ]);
    }

    private function rateLimitKey(string $prefix, string $value): string
    {
        return $prefix . ':' . hash('sha256', strtolower(trim($value)));
    }

    private function requiresTwoFactor(array $admin): bool
    {
        return !empty($admin['two_factor_enabled']) || \admin_role_level((string) ($admin['role'] ?? 'staff')) >= 30;
    }

    private function requiresTwoFactorSetup(array $admin): bool
    {
        return \admin_role_level((string) ($admin['role'] ?? 'staff')) >= 30 && empty($admin['two_factor_enabled']);
    }

    private function twoFactorChallenge(array $admin, string $message, int $status, bool $setupRequired = false): void
    {
        json_response([
            'ok' => false,
            'message' => $message,
            'code' => $setupRequired ? 'two_factor_setup_required' : 'two_factor_required',
            'data' => [
                'twoFactorRequired' => !$setupRequired,
                'twoFactorSetupRequired' => $setupRequired,
                'twoFactorMethod' => 'authenticator_or_recovery',
                'role' => (string) ($admin['role'] ?? 'staff'),
                'admin' => DesktopApiPresenter::admin($admin),
            ],
        ], $status);
    }
}
