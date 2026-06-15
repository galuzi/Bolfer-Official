<?php

declare(strict_types=1);

namespace App\Controllers\Api\Desktop;

use App\Repositories\AdminRepository;

final class InfoController
{
    public function index(): void
    {
        json_response([
            'ok' => true,
            'data' => [
                'service' => 'Bolfer Desktop API',
                'auth' => [
                    'type' => 'bearer_token',
                    'tokenTtlDays' => max(1, (int) env('DESKTOP_TOKEN_TTL_DAYS', '30')),
                ],
                'features' => [
                    'dashboard' => true,
                    'orders' => true,
                    'users' => true,
                    'logs' => true,
                    'discordRichPresence' => true,
                    'tickets' => false,
                ],
                'setup' => [
                    'hasAdmins' => (new AdminRepository())->countAll() > 0,
                ],
            ],
        ]);
    }
}
