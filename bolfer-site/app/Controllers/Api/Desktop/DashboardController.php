<?php

declare(strict_types=1);

namespace App\Controllers\Api\Desktop;

use App\Repositories\AdminLogRepository;
use App\Repositories\DiscordActivityLogRepository;
use App\Repositories\OrderRepository;
use App\Repositories\UserRepository;
use App\Support\DesktopApiPresenter;

final class DashboardController
{
    public function index(): void
    {
        require_admin_api();

        $orderRepository = new OrderRepository();
        $userRepository = new UserRepository();
        $logRepository = new AdminLogRepository();
        $discordLogRepository = new DiscordActivityLogRepository();
        $users = $userRepository->allWithBans();
        $bannedUsers = count(array_filter(
            $users,
            static fn(array $user): bool => !empty($user['is_banned'])
        ));

        $recentOrders = array_slice($orderRepository->list(), 0, 6);

        json_response([
            'ok' => true,
            'data' => [
                'stats' => [
                    'orders' => [
                        'created' => $orderRepository->countByStatus('created'),
                        'pendingPayment' => $orderRepository->countByStatus('pending_payment'),
                        'paidWaitingContact' => $orderRepository->countByStatus('paid_waiting_contact'),
                        'inDelivery' => $orderRepository->countByStatus('in_delivery'),
                        'delivered' => $orderRepository->countByStatus('delivered'),
                        'cancelled' => $orderRepository->countByStatus('cancelled'),
                        'rejected' => $orderRepository->countByStatus('rejected'),
                    ],
                    'users' => [
                        'total' => count($users),
                        'active' => max(0, count($users) - $bannedUsers),
                        'banned' => $bannedUsers,
                    ],
                    'security' => $logRepository->summary(),
                ],
                'recentOrders' => array_map(
                    static fn(array $order): array => DesktopApiPresenter::order($order),
                    $recentOrders
                ),
                'recentDiscordActivity' => array_map(
                    static fn(array $log): array => DesktopApiPresenter::discordActivity($log),
                    $discordLogRepository->listRecent(6)
                ),
                'features' => [
                    'tickets' => [
                        'available' => false,
                        'message' => 'O backend atual ainda nao possui tickets estruturados para a API desktop.',
                    ],
                ],
            ],
        ]);
    }
}
