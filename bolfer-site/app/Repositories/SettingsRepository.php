<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
final class SettingsRepository
{
    public function get(string $key, ?string $default = null): ?string
    {
        $stmt = Database::pdo()->prepare('SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1');
        $stmt->execute(['key' => $key]);
        $value = $stmt->fetchColumn();
        if ($value === false) {
            return $default;
        }
        return $value;
    }

    public function set(string $key, ?string $value): void
    {
        $stmt = Database::pdo()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        $stmt->execute([
            'key' => $key,
            'value' => $value,
        ]);
    }
}
