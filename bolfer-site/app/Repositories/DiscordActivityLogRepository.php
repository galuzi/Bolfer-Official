<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;

final class DiscordActivityLogRepository
{
    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO discord_activity_logs (
                admin_id,
                activity_type,
                activity_scope,
                title,
                description,
                fields_json,
                webhook_url,
                status,
                is_manual,
                error_message,
                sent_at
            ) VALUES (
                :admin_id,
                :activity_type,
                :activity_scope,
                :title,
                :description,
                :fields_json,
                :webhook_url,
                :status,
                :is_manual,
                :error_message,
                :sent_at
            )'
        );

        $stmt->execute([
            'admin_id' => !empty($data['admin_id']) ? (int) $data['admin_id'] : null,
            'activity_type' => trim((string) ($data['activity_type'] ?? 'manual')),
            'activity_scope' => trim((string) ($data['activity_scope'] ?? 'admin')),
            'title' => trim((string) ($data['title'] ?? 'Atividade Bolfer')),
            'description' => trim((string) ($data['description'] ?? '')),
            'fields_json' => !empty($data['fields_json']) ? (string) $data['fields_json'] : null,
            'webhook_url' => !empty($data['webhook_url']) ? trim((string) $data['webhook_url']) : null,
            'status' => trim((string) ($data['status'] ?? 'skipped')),
            'is_manual' => !empty($data['is_manual']) ? 1 : 0,
            'error_message' => ($error = trim((string) ($data['error_message'] ?? ''))) !== '' ? $error : null,
            'sent_at' => !empty($data['sent_at']) ? $data['sent_at'] : null,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public function listRecent(int $limit = 12): array
    {
        $limit = max(1, min(40, $limit));

        $stmt = Database::pdo()->query(
            "SELECT l.*, a.username AS admin_username
             FROM discord_activity_logs l
             LEFT JOIN admins a ON a.id = l.admin_id
             ORDER BY l.created_at DESC, l.id DESC
             LIMIT {$limit}"
        );

        return $stmt->fetchAll();
    }
}
