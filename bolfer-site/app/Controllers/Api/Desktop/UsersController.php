<?php

declare(strict_types=1);

namespace App\Controllers\Api\Desktop;

use App\Repositories\UserInventoryRepository;
use App\Repositories\UserMarketRepository;
use App\Repositories\UserRepository;
use App\Services\BanService;
use App\Services\DiscordActivityService;
use App\Services\UserMarketService;
use App\Support\DesktopApiPresenter;

final class UsersController
{
    public function index(): void
    {
        require_admin_api();

        $repo = new UserRepository();
        $inventoryRepo = new UserInventoryRepository();
        $marketRepo = new UserMarketRepository();

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'role' => trim((string) ($_GET['role'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
        ];

        $allowedRoles = ['user', 'vip', 'moderador', 'doador'];
        if (!in_array($filters['role'], $allowedRoles, true)) {
            $filters['role'] = '';
        }

        $allowedStatuses = ['active', 'banned'];
        if (!in_array($filters['status'], $allowedStatuses, true)) {
            $filters['status'] = '';
        }

        $users = $repo->searchWithBans($filters);
        $userIds = array_map(static fn(array $user): int => (int) ($user['id'] ?? 0), $users);
        $inventoryItems = $inventoryRepo->listByUserIds($userIds);
        $inventorySummary = $this->buildInventorySummary($inventoryItems);
        $coinBalances = $marketRepo->getBalancesByUserIds($userIds);

        json_response([
            'ok' => true,
            'data' => [
                'filters' => $filters,
                'users' => array_map(
                    static fn(array $user): array => DesktopApiPresenter::user(
                        $user,
                        $inventorySummary[(int) ($user['id'] ?? 0)] ?? [],
                        $coinBalances[(int) ($user['id'] ?? 0)] ?? null
                    ),
                    $users
                ),
            ],
        ]);
    }

    public function show(string $id): void
    {
        require_admin_api();

        $userId = (int) $id;
        if ($userId <= 0) {
            $this->notFound();
        }

        $repo = new UserRepository();
        $inventoryRepo = new UserInventoryRepository();
        $marketRepo = new UserMarketRepository();
        $user = $repo->findById($userId);

        if (!$user) {
            $this->notFound();
        }

        $inventoryItems = $inventoryRepo->listByUserId($userId);
        $inventorySummary = $this->buildInventorySummary($inventoryItems);

        json_response([
            'ok' => true,
            'data' => [
                'user' => DesktopApiPresenter::user(
                    $user,
                    $inventorySummary[$userId] ?? [],
                    $marketRepo->getBalance($userId)
                ),
                'inventory' => array_map(
                    static fn(array $item): array => DesktopApiPresenter::inventoryItem($item),
                    $inventoryItems
                ),
                'recentTransactions' => array_map(
                    static fn(array $transaction): array => DesktopApiPresenter::marketTransaction($transaction),
                    $marketRepo->listRecentByUserId($userId, 12)
                ),
            ],
        ]);
    }

    public function ban(string $id): void
    {
        $context = require_admin_api();
        $admin = $context['admin'] ?? [];
        $user = $this->requireManageableUser((int) $id, $admin);
        $payload = request_data();
        $reason = trim((string) ($payload['reason'] ?? ''));

        (new BanService())->applyUserBan(
            $user,
            (int) ($admin['id'] ?? 0),
            $reason !== '' ? $reason : null
        );

        (new DiscordActivityService())->notify(
            $admin,
            'user_ban',
            'moderation',
            'Usuario bloqueado pela equipe',
            'Uma conta foi banida pelo app desktop.',
            [
                ['name' => 'Usuario', 'value' => (string) ($user['username'] ?? 'Conta'), 'inline' => true],
                ['name' => 'Email', 'value' => (string) ($user['email'] ?? '-'), 'inline' => true],
                ['name' => 'Motivo', 'value' => $reason !== '' ? $reason : 'Banimento reforcado', 'inline' => false],
            ]
        );

        $updatedUser = (new UserRepository())->findById((int) ($user['id'] ?? 0)) ?? $user;

        json_response([
            'ok' => true,
            'message' => 'Usuario banido com sucesso.',
            'data' => [
                'user' => DesktopApiPresenter::user($updatedUser),
            ],
        ]);
    }

    public function unban(string $id): void
    {
        $context = require_admin_api();
        $admin = $context['admin'] ?? [];
        $user = $this->requireManageableUser((int) $id, $admin);

        (new BanService())->revokeUserBan((int) ($user['id'] ?? 0), (int) ($admin['id'] ?? 0));

        (new DiscordActivityService())->notify(
            $admin,
            'user_unban',
            'moderation',
            'Usuario desbloqueado pela equipe',
            'Uma conta foi retirada do banimento pelo app desktop.',
            [
                ['name' => 'Usuario', 'value' => (string) ($user['username'] ?? 'Conta'), 'inline' => true],
                ['name' => 'Email', 'value' => (string) ($user['email'] ?? '-'), 'inline' => true],
            ]
        );

        $updatedUser = (new UserRepository())->findById((int) ($user['id'] ?? 0)) ?? $user;

        json_response([
            'ok' => true,
            'message' => 'Usuario desbloqueado com sucesso.',
            'data' => [
                'user' => DesktopApiPresenter::user($updatedUser),
            ],
        ]);
    }

    public function adjustCoins(string $id): void
    {
        $context = require_admin_api();
        $admin = $context['admin'] ?? [];
        $user = $this->requireManageableUser((int) $id, $admin);
        $payload = request_data();
        $amount = (int) ($payload['amount'] ?? 0);
        $note = trim((string) ($payload['note'] ?? ''));

        if ($amount < -999999 || $amount > 999999 || $amount === 0) {
            json_response([
                'ok' => false,
                'message' => 'O ajuste de coins deve ficar entre -999999 e 999999, sem usar zero.',
            ], 422);
        }

        if (strlen($note) > 255) {
            json_response([
                'ok' => false,
                'message' => 'A observacao das coins deve ter no maximo 255 caracteres.',
            ], 422);
        }

        $result = (new UserMarketService())->adjustCoins(
            (int) ($user['id'] ?? 0),
            $amount,
            $note !== '' ? $note : 'Ajuste manual de coins',
            (int) ($admin['id'] ?? 0)
        );

        $statusCode = !empty($result['ok']) ? 200 : 422;
        $updatedUser = (new UserRepository())->findById((int) ($user['id'] ?? 0)) ?? $user;

        json_response([
            'ok' => !empty($result['ok']),
            'message' => (string) ($result['message'] ?? 'Nao foi possivel ajustar as coins.'),
            'data' => [
                'user' => DesktopApiPresenter::user($updatedUser),
                'balance' => $result['balance'] ?? null,
            ],
        ], $statusCode);
    }

    private function requireManageableUser(int $userId, array $admin): array
    {
        if ($userId <= 0) {
            $this->notFound();
        }

        $repo = new UserRepository();
        $user = $repo->findById($userId);
        if (!$user) {
            $this->notFound();
        }

        $adminRole = (string) ($admin['role'] ?? '');
        $userRole = (string) ($user['role'] ?? 'user');

        if (\admin_role_level($adminRole) <= \user_role_level($userRole)) {
            json_response([
                'ok' => false,
                'message' => 'Sem permissao para gerenciar este usuario.',
            ], 403);
        }

        return $user;
    }

    private function buildInventorySummary(array $inventoryItems): array
    {
        $summary = [];

        foreach ($inventoryItems as $inventoryItem) {
            $userId = (int) ($inventoryItem['user_id'] ?? 0);
            if (!isset($summary[$userId])) {
                $summary[$userId] = [
                    'entries' => 0,
                    'units' => 0,
                    'types' => [],
                    'locked' => 0,
                ];
            }

            $summary[$userId]['entries']++;
            $summary[$userId]['units'] += (int) ($inventoryItem['quantity'] ?? 0);
            $summary[$userId]['types'][(string) ($inventoryItem['item_type'] ?? 'outro')] = true;

            $hasLockedContent = (int) ($inventoryItem['unlock_cost'] ?? 0) > 0
                && trim((string) ($inventoryItem['locked_content'] ?? '')) !== '';

            if ($hasLockedContent && empty($inventoryItem['is_unlocked'])) {
                $summary[$userId]['locked']++;
            }
        }

        foreach ($summary as $userId => $row) {
            $summary[$userId]['types'] = count($row['types']);
        }

        return $summary;
    }

    private function notFound(): void
    {
        json_response([
            'ok' => false,
            'message' => 'Usuario nao encontrado.',
        ], 404);
    }
}
