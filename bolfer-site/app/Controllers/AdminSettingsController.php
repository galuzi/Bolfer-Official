<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\SettingsRepository;
use App\Support\View;

final class AdminSettingsController
{
    public function index(): void
    {
        require_full_admin();

        $repo = new SettingsRepository();
        $isFullAdmin = true;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $topupMin = min(50.0, max(5.0, (float) ($_POST['market_topup_min_brl'] ?? 10)));
            $topupMax = min(50.0, max($topupMin, (float) ($_POST['market_topup_max_brl'] ?? 50)));

            $repo->set('whatsapp_link', $this->sanitizePublicUrl((string) ($_POST['whatsapp_link'] ?? '')));
            $repo->set('discord_link', $this->sanitizePublicUrl((string) ($_POST['discord_link'] ?? '')));
            $repo->set('support_hours', $this->limitText((string) ($_POST['support_hours'] ?? ''), 120));
            $repo->set('default_delivery_text', $this->limitText((string) ($_POST['default_delivery_text'] ?? ''), 500));
            $repo->set('market_listing_min_price', (string) max(100, (int) ($_POST['market_listing_min_price'] ?? 100)));
            $repo->set('market_coin_rate', (string) max(1, (float) ($_POST['market_coin_rate'] ?? 10)));
            $repo->set('market_topup_min_brl', (string) $topupMin);
            $repo->set('market_topup_max_brl', (string) $topupMax);
            $repo->set('discord_activity_enabled', !empty($_POST['discord_activity_enabled']) ? '1' : '0');
            $repo->set('discord_activity_webhook_url', $this->sanitizeDiscordWebhookUrl((string) ($_POST['discord_activity_webhook_url'] ?? '')));
            $repo->set('discord_activity_bot_name', $this->limitText((string) ($_POST['discord_activity_bot_name'] ?? 'Bolfer Activity'), 80));
            $repo->set('discord_activity_footer', $this->limitText((string) ($_POST['discord_activity_footer'] ?? 'Bolfer - Painel administrativo'), 180));

            flash_set('success', 'Configuracoes salvas.');
            redirect('/admin/settings');
        }

        View::render('admin/settings', [
            'whatsapp' => $repo->get('whatsapp_link', env('WHATSAPP_LINK')),
            'discord' => $repo->get('discord_link', env('DISCORD_LINK')),
            'supportHours' => $repo->get('support_hours', env('SUPPORT_HOURS')),
            'defaultText' => $repo->get('default_delivery_text', ''),
            'marketListingMinPrice' => $repo->get('market_listing_min_price', '100'),
            'marketCoinRate' => $repo->get('market_coin_rate', '10'),
            'marketTopupMinBrl' => $repo->get('market_topup_min_brl', '10'),
            'marketTopupMaxBrl' => $repo->get('market_topup_max_brl', '50'),
            'discordActivityEnabled' => $repo->get('discord_activity_enabled', '0'),
            'discordActivityWebhookUrl' => $repo->get('discord_activity_webhook_url', ''),
            'discordActivityBotName' => $repo->get('discord_activity_bot_name', 'Bolfer Activity'),
            'discordActivityFooter' => $repo->get('discord_activity_footer', 'Bolfer - Painel administrativo'),
            'isFullAdmin' => $isFullAdmin,
        ]);
    }

    private function sanitizePublicUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return '';
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        if (!in_array($scheme, ['https', 'http'], true)) {
            return '';
        }

        if (env('APP_ENV', 'local') !== 'local' && $scheme !== 'https') {
            return '';
        }

        return $value;
    }

    private function sanitizeDiscordWebhookUrl(string $value): string
    {
        $value = $this->sanitizePublicUrl($value);
        if ($value === '') {
            return '';
        }

        $host = strtolower((string) parse_url($value, PHP_URL_HOST));
        if (!in_array($host, ['discord.com', 'www.discord.com', 'discordapp.com', 'ptb.discord.com', 'canary.discord.com'], true)) {
            return '';
        }

        return $value;
    }

    private function limitText(string $value, int $maxLength): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return strlen($value) > $maxLength ? substr($value, 0, $maxLength) : $value;
    }
}
