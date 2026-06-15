<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\BanAttemptRepository;
use App\Repositories\BanRepository;
use App\Repositories\UserRepository;
use App\Support\ClientContext;
use App\Support\Session;

final class BanService
{
    private const AUTH_FAILURE_LIMIT = 6;
    private const AUTH_FAILURE_WINDOW_MINUTES = 15;

    private BanRepository $banRepository;
    private BanAttemptRepository $banAttemptRepository;
    private UserRepository $userRepository;

    public function __construct()
    {
        $this->banRepository = new BanRepository();
        $this->banAttemptRepository = new BanAttemptRepository();
        $this->userRepository = new UserRepository();
    }

    public function applyUserBan(array $user, int $adminId, ?string $reason = null): void
    {
        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        $this->userRepository->setBan($userId, true, $adminId, $reason);

        $this->banRepository->create([
            'user_id' => $userId,
            'username_snapshot' => (string) ($user['username'] ?? ''),
            'email_snapshot' => (string) ($user['email'] ?? ''),
            'ip_address' => (string) ($user['last_ip_address'] ?? ''),
            'fingerprint_hash' => (string) ($user['last_fingerprint_hash'] ?? ''),
            'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : 'Banimento reforcado',
            'severity' => 'manual',
            'note' => 'Banimento aplicado via painel administrativo.',
            'status' => 'active',
            'banned_by_admin_id' => $adminId,
        ]);
    }

    public function revokeUserBan(int $userId, ?int $adminId = null): void
    {
        $this->userRepository->setBan($userId, false, null, null);
        $this->banRepository->revokeByUserId($userId, $adminId);
    }

    public function canProceedWithAuth(string $route, ?string $loginInput = null, ?string $username = null, ?string $email = null, ?int $userId = null): ?string
    {
        $context = ClientContext::summary();

        if ($this->isRateLimited($context)) {
            $this->recordAttempt([
                'matched_user_id' => $userId,
                'login_input' => $loginInput,
                'username_input' => $username,
                'email_input' => $email,
                ...$context,
                'action' => 'rate_limited',
                'note' => 'Muitas tentativas no mesmo contexto. Bloqueio progressivo ativo.',
            ]);

            return 'Muitas tentativas detectadas. Aguarde alguns minutos antes de tentar novamente.';
        }

        $matchedBan = $this->findMatchingBan($context, $loginInput, $username, $email, $userId);
        if ($matchedBan) {
            $this->recordAttempt([
                'matched_ban_id' => (int) ($matchedBan['id'] ?? 0),
                'matched_user_id' => (int) ($matchedBan['user_id'] ?? 0),
                'login_input' => $loginInput,
                'username_input' => $username,
                'email_input' => $email,
                ...$context,
                'action' => 'ban_blocked',
                'note' => 'Tentativa bloqueada pela camada reforcada em ' . $route . '.',
            ]);

            return 'Acesso bloqueado pelo sistema de seguranca da plataforma.';
        }

        return null;
    }

    public function recordInvalidLogin(?string $loginInput = null): void
    {
        [$username, $email] = $this->splitLoginInput($loginInput);

        $this->recordAttempt([
            'login_input' => $loginInput,
            'username_input' => $username,
            'email_input' => $email,
            ...ClientContext::summary(),
            'action' => 'login_invalid',
            'note' => 'Falha de autenticacao registrada.',
        ]);
    }

    public function recordBannedAccountAttempt(array $user, ?string $loginInput = null): void
    {
        $context = ClientContext::summary();
        $matchedBan = $this->findMatchingBan(
            $context,
            $loginInput,
            (string) ($user['username'] ?? ''),
            (string) ($user['email'] ?? ''),
            (int) ($user['id'] ?? 0)
        );

        $this->recordAttempt([
            'matched_ban_id' => (int) ($matchedBan['id'] ?? 0),
            'matched_user_id' => (int) ($user['id'] ?? 0),
            'login_input' => $loginInput,
            'username_input' => (string) ($user['username'] ?? ''),
            'email_input' => (string) ($user['email'] ?? ''),
            ...$context,
            'action' => 'ban_blocked',
            'note' => 'Conta banida tentou autenticar novamente.',
        ]);
    }

    public function updateUserSecurityContext(int $userId): array
    {
        $context = ClientContext::summary();
        $this->userRepository->updateSecurityContext($userId, $context['ip_address'], $context['fingerprint_hash']);

        return $context;
    }

    public function validateCurrentUserSession(array $sessionUser): bool
    {
        $userId = (int) ($sessionUser['id'] ?? 0);
        if ($userId <= 0) {
            $this->terminateCurrentUserSession();
            return false;
        }

        $user = $this->userRepository->findById($userId);
        if (!$user) {
            $this->terminateCurrentUserSession();
            return false;
        }

        if (!empty($user['is_banned'])) {
            $this->terminateCurrentUserSession('Sua sessao foi encerrada porque esta conta foi banida.');
            return false;
        }

        $issuedAt = (int) ($sessionUser['issued_at'] ?? 0);
        $revokedAt = !empty($user['session_revoked_at']) ? strtotime((string) $user['session_revoked_at']) : 0;
        if ($revokedAt > 0 && $issuedAt > 0 && $revokedAt >= $issuedAt) {
            $this->terminateCurrentUserSession('Sua sessao foi encerrada por uma acao de seguranca.');
            return false;
        }

        $matchedBan = $this->findMatchingBan(
            ClientContext::summary(),
            (string) ($user['email'] ?? ''),
            (string) ($user['username'] ?? ''),
            (string) ($user['email'] ?? ''),
            $userId
        );
        if ($matchedBan) {
            $this->recordAttempt([
                'matched_ban_id' => (int) ($matchedBan['id'] ?? 0),
                'matched_user_id' => $userId,
                'login_input' => (string) ($user['email'] ?? ''),
                'username_input' => (string) ($user['username'] ?? ''),
                'email_input' => (string) ($user['email'] ?? ''),
                ...ClientContext::summary(),
                'action' => 'session_blocked',
                'note' => 'Sessao encerrada por coincidencia com banimento reforcado.',
            ]);
            $this->terminateCurrentUserSession('Acesso encerrado pelo sistema de seguranca.');
            return false;
        }

        return true;
    }

    private function findMatchingBan(array $context, ?string $loginInput = null, ?string $username = null, ?string $email = null, ?int $userId = null): ?array
    {
        [$derivedUsername, $derivedEmail] = $this->splitLoginInput($loginInput);

        return $this->banRepository->findActiveMatch($context, [
            'user_id' => $userId,
            'username' => $username !== null && $username !== '' ? $username : $derivedUsername,
            'email' => $email !== null && $email !== '' ? $email : $derivedEmail,
        ]);
    }

    private function splitLoginInput(?string $loginInput): array
    {
        $loginInput = trim((string) $loginInput);
        if ($loginInput === '') {
            return ['', ''];
        }

        if (str_contains($loginInput, '@')) {
            return ['', strtolower($loginInput)];
        }

        return [strtolower($loginInput), ''];
    }

    private function isRateLimited(array $context): bool
    {
        $failures = $this->banAttemptRepository->countRecentAuthFailures(
            (string) ($context['ip_address'] ?? ''),
            (string) ($context['fingerprint_hash'] ?? ''),
            self::AUTH_FAILURE_WINDOW_MINUTES
        );

        return $failures >= self::AUTH_FAILURE_LIMIT;
    }

    private function recordAttempt(array $data): void
    {
        $this->banAttemptRepository->create($data);
    }

    private function terminateCurrentUserSession(?string $message = null): void
    {
        Session::forget('user');
        if ($message !== null && $message !== '') {
            flash_set('error', $message);
        }
    }
}
