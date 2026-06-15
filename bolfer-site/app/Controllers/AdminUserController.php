<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\UserInventoryRepository;
use App\Repositories\UserMarketRepository;
use App\Repositories\UserRepository;
use App\Services\BanService;
use App\Services\DiscordActivityService;
use App\Services\UserMarketService;
use App\Support\View;

final class AdminUserController
{
    public function index(): void
    {
        require_admin();

        $repo = new UserRepository();
        $inventoryRepo = new UserInventoryRepository();
        $marketRepo = new UserMarketRepository();
        $marketService = new UserMarketService();
        $banService = new BanService();
        $discordService = new DiscordActivityService();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            $userId = (int) ($_POST['user_id'] ?? 0);
            $user = $repo->findById($userId);

            if (!$user) {
                flash_set('error', 'Usuario nao encontrado.');
                redirect('/admin/users');
            }

            $admin = admin_user();
            $adminRole = (string) ($admin['role'] ?? '');
            $userRole = (string) ($user['role'] ?? 'user');

            if (admin_role_level($adminRole) <= user_role_level($userRole)) {
                flash_set('error', 'Sem permissao para gerenciar este usuario.');
                redirect('/admin/users');
            }

            if ($action === 'ban') {
                $reason = trim((string) ($_POST['reason'] ?? ''));
                $banService->applyUserBan($user, (int) ($admin['id'] ?? 0), $reason !== '' ? $reason : null);
                $discordService->notify(
                    admin_user() ?? [],
                    'user_ban',
                    'moderation',
                    'Usuario bloqueado pela equipe',
                    'Uma conta foi banida no sistema reforcado da plataforma.',
                    [
                        ['name' => 'Usuario', 'value' => (string) ($user['username'] ?? 'Conta'), 'inline' => true],
                        ['name' => 'Email', 'value' => (string) ($user['email'] ?? '-'), 'inline' => true],
                        ['name' => 'Motivo', 'value' => $reason !== '' ? $reason : 'Banimento reforcado', 'inline' => false],
                    ]
                );
                flash_set('success', 'Usuario banido e bloqueio tecnico reforcado aplicado.');
                redirect('/admin/users');
            }

            if ($action === 'unban') {
                $banService->revokeUserBan($userId, (int) ($admin['id'] ?? 0));
                $discordService->notify(
                    admin_user() ?? [],
                    'user_unban',
                    'moderation',
                    'Usuario desbloqueado pela equipe',
                    'A conta foi retirada do banimento reforcado no painel administrativo.',
                    [
                        ['name' => 'Usuario', 'value' => (string) ($user['username'] ?? 'Conta'), 'inline' => true],
                        ['name' => 'Email', 'value' => (string) ($user['email'] ?? '-'), 'inline' => true],
                    ]
                );
                flash_set('success', 'Usuario desbloqueado e bloqueios tecnicos revogados.');
                redirect('/admin/users');
            }

            if ($action === 'inventory_add') {
                $itemName = trim((string) ($_POST['item_name'] ?? ''));
                $itemType = trim((string) ($_POST['item_type'] ?? 'jogo'));
                $quantity = (int) ($_POST['quantity'] ?? 1);
                $description = trim((string) ($_POST['description'] ?? ''));
                $unlockCost = (int) ($_POST['unlock_cost'] ?? 0);
                $lockedContent = trim((string) ($_POST['locked_content'] ?? ''));
                $allowedTypes = array_keys(UserInventoryRepository::marketTypeOptions());

                if ($itemName === '' || strlen($itemName) > 140) {
                    flash_set('error', 'Informe um nome de item com ate 140 caracteres.');
                    redirect('/admin/users');
                }

                if (!in_array($itemType, $allowedTypes, true)) {
                    $itemType = 'jogo';
                }

                if ($itemType === 'chave') {
                    $itemName = 'Chave';
                }

                if ($quantity < 1 || $quantity > 999) {
                    flash_set('error', 'A quantidade precisa ficar entre 1 e 999.');
                    redirect('/admin/users');
                }

                if (strlen($description) > 500) {
                    flash_set('error', 'A descricao do item precisa ter no maximo 500 caracteres.');
                    redirect('/admin/users');
                }

                if ($unlockCost < 0 || $unlockCost > 999999) {
                    flash_set('error', 'O custo de desbloqueio precisa ficar entre 0 e 999999.');
                    redirect('/admin/users');
                }

                if ($lockedContent !== '' && strlen($lockedContent) > 5000) {
                    flash_set('error', 'O conteudo bloqueado precisa ter no maximo 5000 caracteres.');
                    redirect('/admin/users');
                }

                if ($itemType === 'chave') {
                    $unlockCost = 0;
                    $description = '';
                    $lockedContent = '';
                }

                if ($itemType === 'jogo' && $unlockCost > 0 && $lockedContent === '') {
                    flash_set('error', 'Preencha o conteudo bloqueado para usar desbloqueio por chaves no jogo.');
                    redirect('/admin/users');
                }

                $inventoryRepo->addItem([
                    'user_id' => $userId,
                    'item_name' => $itemName,
                    'item_type' => $itemType,
                    'quantity' => $quantity,
                    'description' => $description,
                    'unlock_cost' => $unlockCost,
                    'locked_content' => $lockedContent,
                ]);

                flash_set(
                    'success',
                    $unlockCost > 0
                        ? 'Item bloqueado adicionado ao mercado interno do usuario.'
                        : 'Item adicionado ao inventario do usuario.'
                );
                redirect('/admin/users');
            }

            if ($action === 'inventory_remove') {
                $inventoryId = (int) ($_POST['inventory_id'] ?? 0);
                if ($inventoryId <= 0) {
                    flash_set('error', 'Item de inventario invalido.');
                    redirect('/admin/users');
                }

                $inventoryRepo->removeItem($inventoryId, $userId);
                flash_set('success', 'Item removido do inventario do usuario.');
                redirect('/admin/users');
            }

            if ($action === 'market_adjust') {
                $amount = (int) ($_POST['coin_amount'] ?? 0);
                $note = trim((string) ($_POST['coin_note'] ?? ''));

                if ($amount < -999999 || $amount > 999999) {
                    flash_set('error', 'O ajuste de coins precisa ficar entre -999999 e 999999.');
                    redirect('/admin/users');
                }

                if (strlen($note) > 255) {
                    flash_set('error', 'A observacao das coins precisa ter no maximo 255 caracteres.');
                    redirect('/admin/users');
                }

                $result = $marketService->adjustCoins(
                    $userId,
                    $amount,
                    $note !== '' ? $note : 'Ajuste manual de coins',
                    (int) ($admin['id'] ?? 0)
                );

                flash_set($result['ok'] ? 'success' : 'error', (string) ($result['message'] ?? 'Nao foi possivel ajustar as coins.'));
                redirect('/admin/users');
            }
        }

        if (!empty($_GET['clear'])) {
            redirect('/admin/users');
        }

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
        $inventoryByUserId = [];
        $inventorySummary = [];
        $userIds = array_map(static fn(array $user): int => (int) ($user['id'] ?? 0), $users);
        $inventoryItems = $inventoryRepo->listByUserIds($userIds);
        $coinBalances = $marketRepo->getBalancesByUserIds($userIds);

        foreach ($inventoryItems as $inventoryItem) {
            $inventoryUserId = (int) ($inventoryItem['user_id'] ?? 0);
            if (!isset($inventoryByUserId[$inventoryUserId])) {
                $inventoryByUserId[$inventoryUserId] = [];
                $inventorySummary[$inventoryUserId] = [
                    'entries' => 0,
                    'units' => 0,
                    'types' => [],
                    'locked' => 0,
                ];
            }

            $inventoryByUserId[$inventoryUserId][] = $inventoryItem;
            $inventorySummary[$inventoryUserId]['entries']++;
            $inventorySummary[$inventoryUserId]['units'] += (int) ($inventoryItem['quantity'] ?? 0);
            $inventorySummary[$inventoryUserId]['types'][(string) ($inventoryItem['item_type'] ?? 'outro')] = true;

            $hasLockedContent = (int) ($inventoryItem['unlock_cost'] ?? 0) > 0
                && trim((string) ($inventoryItem['locked_content'] ?? '')) !== '';

            if ($hasLockedContent && empty($inventoryItem['is_unlocked'])) {
                $inventorySummary[$inventoryUserId]['locked']++;
            }
        }

        foreach ($inventorySummary as $inventoryUserId => $summary) {
            $inventorySummary[$inventoryUserId]['types'] = count($summary['types']);
        }

        View::render('admin/users', [
            'users' => $users,
            'filters' => $filters,
            'inventoryByUserId' => $inventoryByUserId,
            'inventorySummary' => $inventorySummary,
            'inventoryTypeOptions' => UserInventoryRepository::typeOptions(),
            'marketTypeOptions' => UserInventoryRepository::marketTypeOptions(),
            'coinBalances' => $coinBalances,
        ]);
    }
}
