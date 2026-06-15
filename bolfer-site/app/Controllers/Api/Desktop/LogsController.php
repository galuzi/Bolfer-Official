<?php

declare(strict_types=1);

namespace App\Controllers\Api\Desktop;

use App\Repositories\AdminLogRepository;
use App\Repositories\UserMarketLogRepository;
use App\Support\DesktopApiPresenter;

final class LogsController
{
    public function index(): void
    {
        $context = require_admin_api();
        if (\admin_role_level((string) (($context['admin']['role'] ?? ''))) < 30) {
            json_response([
                'ok' => false,
                'message' => 'Sem permissao para acessar os logs.',
            ], 403);
        }

        $filters = $this->filters();
        $scope = $filters['scope'];
        $logRepository = new AdminLogRepository();
        $showBan = in_array($scope, ['all', 'ban', 'security'], true);
        $showMarket = in_array($scope, ['all', 'market'], true);
        $showAccess = in_array($scope, ['all', 'access', 'security'], true);

        json_response([
            'ok' => true,
            'data' => [
                'summary' => $logRepository->summary(),
                'filters' => $filters,
                'scopeOptions' => [
                    'all' => 'Tudo',
                    'security' => 'Banimentos e IPs',
                    'ban' => 'Banimentos',
                    'market' => 'Mercado',
                    'access' => 'IPs e acessos',
                ],
                'marketEventLabels' => UserMarketLogRepository::eventLabels(),
                'showBan' => $showBan,
                'showMarket' => $showMarket,
                'showAccess' => $showAccess,
                'banLogs' => $showBan ? array_map(
                    static fn(array $entry): array => DesktopApiPresenter::banLog($entry),
                    $logRepository->listBans($filters, 48)
                ) : [],
                'banAttempts' => $showBan ? array_map(
                    static fn(array $entry): array => DesktopApiPresenter::banAttempt($entry),
                    $logRepository->listBanAttempts($filters, 48)
                ) : [],
                'marketLogs' => $showMarket ? array_map(
                    static fn(array $entry): array => DesktopApiPresenter::marketLog($entry),
                    $logRepository->listMarketLogs($filters, 72)
                ) : [],
                'accessLogs' => $showAccess ? array_map(
                    static fn(array $entry): array => DesktopApiPresenter::accessLog($entry),
                    $logRepository->listAccessLogs($filters, 72)
                ) : [],
                'accessIpSummary' => $showAccess ? array_map(
                    static fn(array $entry): array => DesktopApiPresenter::accessIpSummary($entry),
                    $logRepository->listAccessIpSummary($filters, 72)
                ) : [],
            ],
        ]);
    }

    private function filters(): array
    {
        $allowedScopes = ['all', 'security', 'ban', 'market', 'access'];
        $scope = trim((string) ($_GET['scope'] ?? 'all'));
        if (!in_array($scope, $allowedScopes, true)) {
            $scope = 'all';
        }

        $banStatus = trim((string) ($_GET['ban_status'] ?? ''));
        if (!in_array($banStatus, ['', 'active', 'revoked'], true)) {
            $banStatus = '';
        }

        $marketEvent = trim((string) ($_GET['market_event'] ?? ''));
        if ($marketEvent !== '' && !array_key_exists($marketEvent, UserMarketLogRepository::eventLabels())) {
            $marketEvent = '';
        }

        return [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'ip' => trim((string) ($_GET['ip'] ?? '')),
            'scope' => $scope,
            'ban_status' => $banStatus,
            'market_event' => $marketEvent,
        ];
    }
}
