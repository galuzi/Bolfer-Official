<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;

final class BanAttemptRepository
{
    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO ban_attempts (
                matched_ban_id,
                matched_user_id,
                login_input,
                username_input,
                email_input,
                ip_address,
                fingerprint_hash,
                user_agent,
                route,
                action,
                note
            ) VALUES (
                :matched_ban_id,
                :matched_user_id,
                :login_input,
                :username_input,
                :email_input,
                :ip_address,
                :fingerprint_hash,
                :user_agent,
                :route,
                :action,
                :note
            )'
        );

        $stmt->execute([
            'matched_ban_id' => !empty($data['matched_ban_id']) ? (int) $data['matched_ban_id'] : null,
            'matched_user_id' => !empty($data['matched_user_id']) ? (int) $data['matched_user_id'] : null,
            'login_input' => ($loginInput = trim((string) ($data['login_input'] ?? ''))) !== '' ? $loginInput : null,
            'username_input' => ($usernameInput = strtolower(trim((string) ($data['username_input'] ?? '')))) !== '' ? $usernameInput : null,
            'email_input' => ($emailInput = strtolower(trim((string) ($data['email_input'] ?? '')))) !== '' ? $emailInput : null,
            'ip_address' => ($ipAddress = trim((string) ($data['ip_address'] ?? ''))) !== '' ? $ipAddress : null,
            'fingerprint_hash' => ($fingerprintHash = strtolower(trim((string) ($data['fingerprint_hash'] ?? '')))) !== '' ? $fingerprintHash : null,
            'user_agent' => ($userAgent = trim((string) ($data['user_agent'] ?? ''))) !== '' ? $userAgent : null,
            'route' => ($route = trim((string) ($data['route'] ?? ''))) !== '' ? $route : null,
            'action' => trim((string) ($data['action'] ?? 'unknown')),
            'note' => ($note = trim((string) ($data['note'] ?? ''))) !== '' ? $note : null,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public function countRecentAuthFailures(?string $ipAddress, ?string $fingerprintHash, int $minutes = 15): int
    {
        $conditions = [];
        $threshold = (new \DateTimeImmutable())->modify('-' . max(1, $minutes) . ' minutes')->format('Y-m-d H:i:s');
        $params = [
            'login_invalid' => 'login_invalid',
            'ban_blocked' => 'ban_blocked',
            'rate_limited' => 'rate_limited',
            'threshold' => $threshold,
        ];

        $ipAddress = trim((string) $ipAddress);
        if ($ipAddress !== '') {
            $conditions[] = 'ip_address = :ip_address';
            $params['ip_address'] = $ipAddress;
        }

        $fingerprintHash = strtolower(trim((string) $fingerprintHash));
        if ($fingerprintHash !== '') {
            $conditions[] = 'fingerprint_hash = :fingerprint_hash';
            $params['fingerprint_hash'] = $fingerprintHash;
        }

        if ($conditions === []) {
            return 0;
        }

        $sql = 'SELECT COUNT(*)
                FROM ban_attempts
                WHERE action IN (:login_invalid, :ban_blocked, :rate_limited)
                  AND created_at >= :threshold
                  AND (' . implode(' OR ', $conditions) . ')';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }
}
