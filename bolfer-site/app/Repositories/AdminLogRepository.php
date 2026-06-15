<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;

final class AdminLogRepository
{
    public function summary(): array
    {
        $stmt = Database::pdo()->query(
            'SELECT
                (SELECT COUNT(*) FROM bans WHERE status = "active") AS active_bans,
                (SELECT COUNT(*) FROM ban_attempts WHERE created_at >= (CURRENT_DATE + INTERVAL 0 DAY)) AS attempts_today,
                (SELECT COUNT(*) FROM user_market_logs WHERE event_type = "listing_sold") AS market_sales,
                (SELECT COUNT(DISTINCT user_id) FROM user_access_logs WHERE user_id IS NOT NULL) AS tracked_accounts,
                (SELECT COUNT(DISTINCT ip_address) FROM user_access_logs WHERE ip_address IS NOT NULL AND ip_address <> "") AS unique_ips'
        );

        $row = $stmt->fetch() ?: [];

        return [
            'active_bans' => (int) ($row['active_bans'] ?? 0),
            'attempts_today' => (int) ($row['attempts_today'] ?? 0),
            'market_sales' => (int) ($row['market_sales'] ?? 0),
            'tracked_accounts' => (int) ($row['tracked_accounts'] ?? 0),
            'unique_ips' => (int) ($row['unique_ips'] ?? 0),
        ];
    }

    public function listBans(array $filters, int $limit = 40): array
    {
        $params = [];
        $sql = 'SELECT
                    b.*,
                    COALESCE(u.username, b.username_snapshot, "Conta removida") AS target_username,
                    COALESCE(u.email, b.email_snapshot, "-") AS target_email,
                    created_admin.username AS created_by_username,
                    revoked_admin.username AS revoked_by_username
                FROM bans b
                LEFT JOIN users u ON u.id = b.user_id
                LEFT JOIN admins created_admin ON created_admin.id = b.banned_by_admin_id
                LEFT JOIN admins revoked_admin ON revoked_admin.id = b.revoked_by_admin_id
                WHERE 1=1';

        $status = trim((string) ($filters['ban_status'] ?? ''));
        if (in_array($status, ['active', 'revoked'], true)) {
            $sql .= ' AND b.status = :ban_status';
            $params['ban_status'] = $status;
        }

        $ip = trim((string) ($filters['ip'] ?? ''));
        if ($ip !== '') {
            $sql .= ' AND COALESCE(b.ip_address, "") LIKE :ban_ip';
            $params['ban_ip'] = '%' . $ip . '%';
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $sql .= ' AND (
                COALESCE(u.username, b.username_snapshot, "") LIKE :ban_q
                OR COALESCE(u.email, b.email_snapshot, "") LIKE :ban_q
                OR COALESCE(created_admin.username, "") LIKE :ban_q
                OR COALESCE(revoked_admin.username, "") LIKE :ban_q
                OR COALESCE(b.reason, "") LIKE :ban_q
                OR COALESCE(b.note, "") LIKE :ban_q
                OR COALESCE(b.ip_address, "") LIKE :ban_q
            )';
            $params['ban_q'] = '%' . $q . '%';
        }

        $limit = max(1, min(120, $limit));
        $sql .= " ORDER BY b.created_at DESC, b.id DESC LIMIT {$limit}";

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function listBanAttempts(array $filters, int $limit = 60): array
    {
        $params = [];
        $sql = 'SELECT
                    ba.*,
                    matched_user.username AS matched_username,
                    matched_user.email AS matched_email,
                    b.reason AS matched_ban_reason
                FROM ban_attempts ba
                LEFT JOIN users matched_user ON matched_user.id = ba.matched_user_id
                LEFT JOIN bans b ON b.id = ba.matched_ban_id
                WHERE 1=1';

        $ip = trim((string) ($filters['ip'] ?? ''));
        if ($ip !== '') {
            $sql .= ' AND COALESCE(ba.ip_address, "") LIKE :attempt_ip';
            $params['attempt_ip'] = '%' . $ip . '%';
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $sql .= ' AND (
                COALESCE(ba.login_input, "") LIKE :attempt_q
                OR COALESCE(ba.username_input, "") LIKE :attempt_q
                OR COALESCE(ba.email_input, "") LIKE :attempt_q
                OR COALESCE(matched_user.username, "") LIKE :attempt_q
                OR COALESCE(matched_user.email, "") LIKE :attempt_q
                OR COALESCE(ba.note, "") LIKE :attempt_q
                OR COALESCE(ba.action, "") LIKE :attempt_q
                OR COALESCE(ba.ip_address, "") LIKE :attempt_q
            )';
            $params['attempt_q'] = '%' . $q . '%';
        }

        $limit = max(1, min(150, $limit));
        $sql .= " ORDER BY ba.created_at DESC, ba.id DESC LIMIT {$limit}";

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function listMarketLogs(array $filters, int $limit = 80): array
    {
        $params = [];
        $sql = 'SELECT
                    ml.*,
                    actor.username AS actor_username,
                    seller.username AS seller_username,
                    buyer.username AS buyer_username,
                    target.username AS target_username,
                    admin.username AS admin_username
                FROM user_market_logs ml
                LEFT JOIN users actor ON actor.id = ml.actor_user_id
                LEFT JOIN users seller ON seller.id = ml.seller_user_id
                LEFT JOIN users buyer ON buyer.id = ml.buyer_user_id
                LEFT JOIN users target ON target.id = ml.target_user_id
                LEFT JOIN admins admin ON admin.id = ml.admin_id
                WHERE 1=1';

        $event = trim((string) ($filters['market_event'] ?? ''));
        if ($event !== '' && array_key_exists($event, UserMarketLogRepository::eventLabels())) {
            $sql .= ' AND ml.event_type = :market_event';
            $params['market_event'] = $event;
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $sql .= ' AND (
                COALESCE(ml.item_name_snapshot, "") LIKE :market_q
                OR COALESCE(ml.item_type_snapshot, "") LIKE :market_q
                OR COALESCE(ml.note, "") LIKE :market_q
                OR COALESCE(actor.username, "") LIKE :market_q
                OR COALESCE(seller.username, "") LIKE :market_q
                OR COALESCE(buyer.username, "") LIKE :market_q
                OR COALESCE(target.username, "") LIKE :market_q
                OR COALESCE(admin.username, "") LIKE :market_q
            )';
            $params['market_q'] = '%' . $q . '%';
        }

        $limit = max(1, min(200, $limit));
        $sql .= " ORDER BY ml.created_at DESC, ml.id DESC LIMIT {$limit}";

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function listAccessLogs(array $filters, int $limit = 80): array
    {
        $params = [];
        $sql = 'SELECT
                    al.*,
                    COALESCE(u.username, al.username_snapshot, "Conta removida") AS target_username,
                    COALESCE(u.email, al.email_snapshot, "-") AS target_email
                FROM user_access_logs al
                LEFT JOIN users u ON u.id = al.user_id
                WHERE 1=1';

        $ip = trim((string) ($filters['ip'] ?? ''));
        if ($ip !== '') {
            $sql .= ' AND COALESCE(al.ip_address, "") LIKE :access_ip';
            $params['access_ip'] = '%' . $ip . '%';
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $sql .= ' AND (
                COALESCE(u.username, al.username_snapshot, "") LIKE :access_q
                OR COALESCE(u.email, al.email_snapshot, "") LIKE :access_q
                OR COALESCE(al.action, "") LIKE :access_q
                OR COALESCE(al.route, "") LIKE :access_q
                OR COALESCE(al.ip_address, "") LIKE :access_q
            )';
            $params['access_q'] = '%' . $q . '%';
        }

        $limit = max(1, min(200, $limit));
        $sql .= " ORDER BY al.created_at DESC, al.id DESC LIMIT {$limit}";

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function listAccessIpSummary(array $filters, int $limit = 80): array
    {
        $params = [];
        $sql = 'SELECT
                    al.user_id,
                    al.username_snapshot,
                    al.email_snapshot,
                    al.ip_address,
                    COALESCE(u.username, al.username_snapshot, "Conta removida") AS target_username,
                    COALESCE(u.email, al.email_snapshot, "-") AS target_email,
                    COUNT(*) AS total_hits,
                    MIN(al.created_at) AS first_seen_at,
                    MAX(al.created_at) AS last_seen_at
                FROM user_access_logs al
                LEFT JOIN users u ON u.id = al.user_id
                WHERE al.ip_address IS NOT NULL
                  AND al.ip_address <> ""';

        $ip = trim((string) ($filters['ip'] ?? ''));
        if ($ip !== '') {
            $sql .= ' AND al.ip_address LIKE :ips_ip';
            $params['ips_ip'] = '%' . $ip . '%';
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $sql .= ' AND (
                COALESCE(u.username, al.username_snapshot, "") LIKE :ips_q
                OR COALESCE(u.email, al.email_snapshot, "") LIKE :ips_q
                OR COALESCE(al.ip_address, "") LIKE :ips_q
            )';
            $params['ips_q'] = '%' . $q . '%';
        }

        $limit = max(1, min(200, $limit));
        $sql .= "
            GROUP BY al.user_id, al.username_snapshot, al.email_snapshot, al.ip_address, u.username, u.email
            ORDER BY last_seen_at DESC
            LIMIT {$limit}";

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
