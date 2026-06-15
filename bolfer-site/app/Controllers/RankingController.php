<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\RankingService;
use App\Support\View;

final class RankingController
{
    public function index(): void
    {
        $payload = (new RankingService())->payload(10);

        View::render('rankings', [
            'leaderboardsPayload' => $payload,
        ]);
    }

    public function api(): void
    {
        $limit = (int) ($_GET['limit'] ?? 10);
        $forceRefresh = isset($_GET['refresh']) && $_GET['refresh'] === '1';
        $payload = (new RankingService())->payload($limit, $forceRefresh);

        json_response([
            'ok' => true,
            ...$payload,
        ]);
    }
}