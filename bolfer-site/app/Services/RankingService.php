<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\RankingRepository;

final class RankingService
{
    private const CACHE_TTL = 120;

    public function payload(int $limit = 10, bool $forceRefresh = false): array
    {
        $limit = max(3, min(25, $limit));
        $cachePath = $this->cachePath($limit);

        if (!$forceRefresh) {
            $cached = $this->readCache($cachePath);
            if ($cached !== null) {
                return $cached;
            }
        }

        $payload = $this->buildPayload($limit);
        $this->writeCache($cachePath, $payload);

        return $payload;
    }

    private function buildPayload(int $limit): array
    {
        $repository = new RankingRepository();

        try {
            $topCoins = $this->decorateCoins($repository->topCoins($limit));
        } catch (\Throwable) {
            $topCoins = [];
        }

        try {
            $topDonates = $this->decorateDonations($repository->topDonates($limit));
        } catch (\Throwable) {
            $topDonates = [];
        }

        return [
            'generated_at' => date('c'),
            'generated_label' => date('d/m H:i'),
            'top_coins' => $topCoins,
            'top_donates' => $topDonates,
            'stats' => [
                'coins_players' => count($topCoins),
                'donates_players' => count($topDonates),
                'coins_best' => $topCoins[0]['value_display'] ?? '0 coins',
                'donates_best' => $topDonates[0]['value_display'] ?? 'R$ 0,00',
            ],
        ];
    }

    private function decorateCoins(array $rows): array
    {
        $entries = [];
        foreach (array_values($rows) as $index => $row) {
            $rank = $index + 1;
            $coins = (int) ($row['market_coins'] ?? 0);
            $topupCount = (int) ($row['topup_count'] ?? 0);
            $entries[] = [
                'rank' => $rank,
                'board' => 'coins',
                'username' => (string) ($row['username'] ?? 'Membro'),
                'display_name' => $this->displayName((string) ($row['username'] ?? 'Membro')),
                'avatar' => $this->initials((string) ($row['username'] ?? 'Membro')),
                'role' => (string) ($row['role'] ?? 'user'),
                'role_label' => $this->roleLabel((string) ($row['role'] ?? 'user')),
                'role_tone' => $this->roleTone((string) ($row['role'] ?? 'user')),
                'medal' => $this->medal($rank),
                'medal_label' => $this->medalLabel($rank),
                'value' => $coins,
                'value_display' => $this->formatCoins($coins),
                'headline' => $rank === 1 ? "L\u{00ED}der do saldo atual" : 'Saldo forte no mercado',
                'subtitle' => $topupCount > 0
                    ? $topupCount . ' recarga' . ($topupCount > 1 ? 's aprovadas' : ' aprovada')
                    : 'Saldo atual na Bolfer',
                'detail_primary' => 'Recargas: ' . $this->formatCurrency((float) ($row['total_topup_brl'] ?? 0)),
                'detail_secondary' => !empty($row['last_topup_at'])
                    ? "\u{00DA}ltima recarga em " . date('d/m', strtotime((string) $row['last_topup_at']))
                    : 'Sem recargas recentes',
            ];
        }

        return $entries;
    }

    private function decorateDonations(array $rows): array
    {
        $entries = [];
        foreach (array_values($rows) as $index => $row) {
            $rank = $index + 1;
            $total = (float) ($row['total_donated'] ?? 0);
            $donationsCount = (int) ($row['donations_count'] ?? 0);
            $entries[] = [
                'rank' => $rank,
                'board' => 'donates',
                'username' => (string) ($row['username'] ?? 'Membro'),
                'display_name' => $this->displayName((string) ($row['username'] ?? 'Membro')),
                'avatar' => $this->initials((string) ($row['username'] ?? 'Membro')),
                'role' => (string) ($row['role'] ?? 'user'),
                'role_label' => $this->roleLabel((string) ($row['role'] ?? 'user')),
                'role_tone' => $this->roleTone((string) ($row['role'] ?? 'user')),
                'medal' => $this->medal($rank),
                'medal_label' => $this->medalLabel($rank),
                'value' => $total,
                'value_display' => $this->formatCurrency($total),
                'headline' => 'Nome forte no apoio Bolfer',
                'subtitle' => $donationsCount . " doa\u{00E7}\u{00E3}o" . ($donationsCount > 1 ? 'es aprovadas' : ' aprovada'),
                'detail_primary' => 'Apoio confirmado dentro do site',
                'detail_secondary' => !empty($row['last_donation_at'])
                    ? "\u{00DA}ltima doa\u{00E7}\u{00E3}o em " . date('d/m', strtotime((string) $row['last_donation_at']))
                    : 'Primeiras entradas no ranking',
            ];
        }

        return $entries;
    }

    private function cachePath(int $limit): string
    {
        return dirname(__DIR__, 2) . '/storage/cache/rankings_' . $limit . '.json';
    }

    private function readCache(string $cachePath): ?array
    {
        if (!is_file($cachePath)) {
            return null;
        }

        $mtime = filemtime($cachePath);
        if ($mtime === false || ($mtime + self::CACHE_TTL) < time()) {
            return null;
        }

        $raw = @file_get_contents($cachePath);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function writeCache(string $cachePath, array $payload): void
    {
        $directory = dirname($cachePath);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (!is_string($json)) {
            return;
        }

        @file_put_contents($cachePath, $json, LOCK_EX);
    }

    private function displayName(string $username): string
    {
        $username = trim($username);
        return $username !== '' ? mb_substr($username, 0, 20) : 'Membro';
    }

    private function initials(string $username): string
    {
        $username = trim($username);
        if ($username === '') {
            return 'BO';
        }

        $parts = preg_split('/\s+/', $username) ?: [];
        if (count($parts) >= 2) {
            $first = mb_substr((string) ($parts[0] ?? ''), 0, 1);
            $second = mb_substr((string) ($parts[1] ?? ''), 0, 1);
            return mb_strtoupper($first . $second);
        }

        return mb_strtoupper(mb_substr($username, 0, 2));
    }

    private function medal(int $rank): string
    {
        return match ($rank) {
            1 => 'gold',
            2 => 'silver',
            3 => 'bronze',
            default => 'neon',
        };
    }

    private function medalLabel(int $rank): string
    {
        return match ($rank) {
            1 => 'Ouro',
            2 => 'Prata',
            3 => 'Bronze',
            default => 'Top',
        };
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'vip' => 'VIP',
            'moderador' => 'Moderador',
            default => 'Membro',
        };
    }

    private function roleTone(string $role): string
    {
        return match ($role) {
            'vip' => 'vip',
            'moderador' => 'mod',
            default => 'member',
        };
    }

    private function formatCurrency(float $amount): string
    {
        return 'R$ ' . number_format($amount, 2, ',', '.');
    }

    private function formatCoins(int $coins): string
    {
        return number_format($coins, 0, ',', '.') . ' coins';
    }
}