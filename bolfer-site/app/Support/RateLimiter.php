<?php

declare(strict_types=1);

namespace App\Support;

final class RateLimiter
{
    public function isBlocked(string $key): ?int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT blocked_until
             FROM rate_limits
             WHERE limiter_key = :limiter_key
             LIMIT 1'
        );
        $stmt->execute(['limiter_key' => $key]);
        $blockedUntil = $stmt->fetchColumn();
        if ($blockedUntil === false || $blockedUntil === null) {
            return null;
        }

        $blockedAt = strtotime((string) $blockedUntil);
        if ($blockedAt === false || $blockedAt <= time()) {
            return null;
        }

        return max(1, $blockedAt - time());
    }

    public function hit(string $key, int $maxAttempts, int $windowSeconds, ?int $blockSeconds = null): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'SELECT attempts, window_started_at, blocked_until
                 FROM rate_limits
                 WHERE limiter_key = :limiter_key
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->execute(['limiter_key' => $key]);
            $row = $stmt->fetch();

            $now = time();
            $windowStartedAt = $now;
            $attempts = 1;
            $blockedUntil = null;

            if ($row) {
                $currentWindow = strtotime((string) ($row['window_started_at'] ?? '')) ?: $now;
                $currentBlockedUntil = !empty($row['blocked_until'])
                    ? (strtotime((string) $row['blocked_until']) ?: null)
                    : null;

                if ($currentBlockedUntil !== null && $currentBlockedUntil > $now) {
                    $pdo->commit();
                    return max(1, $currentBlockedUntil - $now);
                }

                if (($currentWindow + max(1, $windowSeconds)) > $now) {
                    $windowStartedAt = $currentWindow;
                    $attempts = (int) ($row['attempts'] ?? 0) + 1;
                }
            }

            if ($attempts >= max(1, $maxAttempts)) {
                $blockedUntil = date('Y-m-d H:i:s', $now + max(1, $blockSeconds ?? $windowSeconds));
            }

            $persist = $pdo->prepare(
                'INSERT INTO rate_limits (limiter_key, attempts, window_started_at, blocked_until)
                 VALUES (:limiter_key, :attempts, :window_started_at, :blocked_until)
                 ON DUPLICATE KEY UPDATE
                    attempts = VALUES(attempts),
                    window_started_at = VALUES(window_started_at),
                    blocked_until = VALUES(blocked_until),
                    updated_at = CURRENT_TIMESTAMP'
            );
            $persist->execute([
                'limiter_key' => $key,
                'attempts' => $attempts,
                'window_started_at' => date('Y-m-d H:i:s', $windowStartedAt),
                'blocked_until' => $blockedUntil,
            ]);

            $pdo->commit();

            if ($blockedUntil === null) {
                return 0;
            }

            return max(1, (strtotime($blockedUntil) ?: $now) - $now);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return 0;
        }
    }

    public function clear(string $key): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM rate_limits WHERE limiter_key = :limiter_key');
        $stmt->execute(['limiter_key' => $key]);
    }
}
