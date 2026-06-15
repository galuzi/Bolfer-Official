<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;

final class UserAccessLogRepository
{
    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO user_access_logs (
                user_id,
                username_snapshot,
                email_snapshot,
                ip_address,
                fingerprint_hash,
                user_agent,
                route,
                action
            ) VALUES (
                :user_id,
                :username_snapshot,
                :email_snapshot,
                :ip_address,
                :fingerprint_hash,
                :user_agent,
                :route,
                :action
            )'
        );

        $stmt->execute([
            'user_id' => !empty($data['user_id']) ? (int) $data['user_id'] : null,
            'username_snapshot' => ($username = trim((string) ($data['username_snapshot'] ?? ''))) !== '' ? $username : null,
            'email_snapshot' => ($email = strtolower(trim((string) ($data['email_snapshot'] ?? '')))) !== '' ? $email : null,
            'ip_address' => ($ipAddress = trim((string) ($data['ip_address'] ?? ''))) !== '' ? $ipAddress : null,
            'fingerprint_hash' => ($fingerprintHash = strtolower(trim((string) ($data['fingerprint_hash'] ?? '')))) !== '' ? $fingerprintHash : null,
            'user_agent' => ($userAgent = trim((string) ($data['user_agent'] ?? ''))) !== '' ? $userAgent : null,
            'route' => ($route = trim((string) ($data['route'] ?? ''))) !== '' ? $route : null,
            'action' => trim((string) ($data['action'] ?? 'unknown')),
        ]);

        return (int) Database::pdo()->lastInsertId();
    }
}
