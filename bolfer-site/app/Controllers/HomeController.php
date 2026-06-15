<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CategoryRepository;
use App\Repositories\ModeratorRepository;
use App\Repositories\ProductRepository;
use App\Repositories\SettingsRepository;
use App\Services\RankingService;
use App\Support\View;

final class HomeController
{
    public function index(): void
    {
        $categories = (new CategoryRepository())->allActive();
        $products = (new ProductRepository())->allActive();
        $moderators = (new ModeratorRepository())->all();
        $settings = new SettingsRepository();
        $leaderboardsPayload = (new RankingService())->payload(10);

        View::render('home', [
            'categories' => $categories,
            'products' => $products,
            'moderators' => $moderators,
            'leaderboardsPayload' => $leaderboardsPayload,
            'whatsapp' => $settings->get('whatsapp_link', env('WHATSAPP_LINK')),
            'discord' => $settings->get('discord_link', env('DISCORD_LINK')),
            'supportHours' => $settings->get('support_hours', env('SUPPORT_HOURS')),
        ]);
    }
}