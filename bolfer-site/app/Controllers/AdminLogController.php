<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AdminLogRepository;
use App\Repositories\UserMarketLogRepository;
use App\Support\View;

final class AdminLogController
{
    public function index(): void
    {
        require_full_admin();

        if (!empty($_GET['clear'])) {
            redirect('/admin/logs');
        }

        $filters = $this->filters();
        $scope = $filters['scope'];
        $logRepository = new AdminLogRepository();
        $showBan = in_array($scope, ['all', 'ban'], true);
        $showMarket = in_array($scope, ['all', 'market'], true);
        $showAccess = in_array($scope, ['all', 'access'], true);

        View::render('admin/logs', [
            'summary' => $logRepository->summary(),
            'filters' => $filters,
            'scopeOptions' => [
                'all' => 'Tudo',
                'ban' => 'Banimentos',
                'market' => 'Mercado',
                'access' => 'IPs e acessos',
            ],
            'banStatusOptions' => [
                '' => 'Todos',
                'active' => 'Ativos',
                'revoked' => 'Revogados',
            ],
            'marketEventOptions' => ['' => 'Todos'] + UserMarketLogRepository::eventLabels(),
            'accessActionLabels' => [
                'context_backfill' => 'Contexto anterior',
                'login_success' => 'Login aprovado',
                'register_success' => 'Cadastro criado',
            ],
            'marketEventLabels' => UserMarketLogRepository::eventLabels(),
            'showBan' => $showBan,
            'showMarket' => $showMarket,
            'showAccess' => $showAccess,
            'banLogs' => $showBan ? $logRepository->listBans($filters, 48) : [],
            'banAttempts' => $showBan ? $logRepository->listBanAttempts($filters, 48) : [],
            'marketLogs' => $showMarket ? $logRepository->listMarketLogs($filters, 72) : [],
            'accessLogs' => $showAccess ? $logRepository->listAccessLogs($filters, 72) : [],
            'accessIpSummary' => $showAccess ? $logRepository->listAccessIpSummary($filters, 72) : [],
        ]);
    }

    private function filters(): array
    {
        $allowedScopes = ['all', 'ban', 'market', 'access'];
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
