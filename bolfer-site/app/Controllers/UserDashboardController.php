<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\UserInventoryRepository;
use App\Repositories\UserMarketRepository;
use App\Repositories\UserRepository;
use App\Services\UserMarketService;
use App\Support\RateLimiter;
use App\Support\View;

final class UserDashboardController
{
    public function index(): void
    {
        require_user();

        $user = $this->refreshSessionUser();

        View::render('user/dashboard', [
            'user' => $user,
            'canAccessVipArea' => $this->canAccessVipArea($user),
        ]);
    }

    public function vipArea(): void
    {
        require_user();

        $user = $this->refreshSessionUser();
        if (!$this->canAccessVipArea($user)) {
            flash_set('error', 'Esta area VIP e liberada apenas para contas com cargo VIP ou Moderador.');
            redirect('/usuario');
        }

        View::render('user/vip', [
            'user' => $user,
        ]);
    }

    public function inventory(): void
    {
        require_user();

        $user = $this->refreshSessionUser();

        View::render('user/inventory', [
            'user' => $user,
            ...$this->buildInventoryPayload($user),
        ]);
    }

    public function unlockInventoryItem(): void
    {
        require_user();

        $user = $this->refreshSessionUser();
        $this->enforceUnlockRateLimit((int) ($user['id'] ?? 0));
        $inventoryId = (int) ($_POST['inventory_id'] ?? 0);

        if ($inventoryId <= 0) {
            flash_set('error', 'Item invalido para desbloqueio.');
            redirect('/usuario/inventario');
        }

        $marketService = new UserMarketService();
        $result = $marketService->unlockInventoryItem((int) ($user['id'] ?? 0), $inventoryId);

        flash_set($result['ok'] ? 'success' : 'error', (string) ($result['message'] ?? 'Nao foi possivel concluir a acao.'));
        redirect('/usuario/inventario');
    }

    private function buildInventoryPayload(array $user): array
    {
        $inventoryRepo = new UserInventoryRepository();
        $marketRepo = new UserMarketRepository();
        $inventoryItems = $inventoryRepo->listByUserId((int) ($user['id'] ?? 0));
        $inventorySummary = [
            'entries' => count($inventoryItems),
            'units' => 0,
            'types' => 0,
            'locked' => 0,
            'unlocked' => 0,
        ];

        $itemTypes = [];
        foreach ($inventoryItems as $inventoryItem) {
            $inventorySummary['units'] += (int) ($inventoryItem['quantity'] ?? 0);
            $itemTypes[(string) ($inventoryItem['item_type'] ?? 'outro')] = true;

            $hasLockedContent = (int) ($inventoryItem['unlock_cost'] ?? 0) > 0
                && trim((string) ($inventoryItem['locked_content'] ?? '')) !== '';

            if ($hasLockedContent && empty($inventoryItem['is_unlocked'])) {
                $inventorySummary['locked']++;
            } else {
                $inventorySummary['unlocked']++;
            }
        }

        $inventorySummary['types'] = count($itemTypes);

        return [
            'inventoryItems' => $inventoryItems,
            'inventorySummary' => $inventorySummary,
            'inventoryTypeOptions' => UserInventoryRepository::typeOptions(),
            'marketCoins' => $marketRepo->getBalance((int) ($user['id'] ?? 0)),
            'marketKeys' => $inventoryRepo->getAvailableKeyCount((int) ($user['id'] ?? 0)),
        ];
    }

    private function enforceUnlockRateLimit(int $userId): void
    {
        $limiter = new RateLimiter();
        $key = 'inventory-unlock:' . hash('sha256', (string) max(1, $userId));
        $retryAfter = (int) ($limiter->isBlocked($key) ?? 0);
        if ($retryAfter > 0) {
            flash_set('error', 'Muitas tentativas de desbloqueio. Aguarde ' . $retryAfter . ' segundo(s) e tente novamente.');
            redirect('/usuario/inventario');
        }

        $limiter->hit($key, 15, 60, 120);
    }

    private function refreshSessionUser(): array
    {
        $user = user_session() ?? [];
        $currentUser = (new UserRepository())->findById((int) ($user['id'] ?? 0));
        if ($currentUser) {
            $user['role'] = (string) ($currentUser['role'] ?? ($user['role'] ?? 'user'));
            $user['two_factor_enabled'] = !empty($currentUser['two_factor_enabled']) ? 1 : 0;
            $_SESSION['user']['role'] = $user['role'];
            $_SESSION['user']['two_factor_enabled'] = $user['two_factor_enabled'];
        }

        return $user;
    }

    private function canAccessVipArea(array $user): bool
    {
        $role = strtolower(trim((string) ($user['role'] ?? 'user')));

        return in_array($role, ['vip', 'moderador'], true);
    }
}
