<?php

declare(strict_types=1);

namespace App\Support;

final class Captcha
{
    private const SESSION_KEY = '_captcha_challenges';
    private const TTL_SECONDS = 600;

    public static function challenge(string $scope): array
    {
        self::cleanup();

        if (isset($_SESSION[self::SESSION_KEY][$scope]) && is_array($_SESSION[self::SESSION_KEY][$scope])) {
            foreach ($_SESSION[self::SESSION_KEY][$scope] as $challengeId => $challenge) {
                if (self::isValidChallenge($challenge)) {
                    return [
                        'id' => (string) $challengeId,
                        'question' => (string) ($challenge['question'] ?? ''),
                    ];
                }
            }
        }

        $challengeId = bin2hex(random_bytes(12));
        [$question, $answer] = self::generateMathChallenge();

        $_SESSION[self::SESSION_KEY][$scope][$challengeId] = [
            'question' => $question,
            'answer_hash' => hash('sha256', (string) $answer),
            'expires_at' => time() + self::TTL_SECONDS,
        ];

        return [
            'id' => $challengeId,
            'question' => $question,
        ];
    }

    public static function validate(string $scope, ?string $challengeId, ?string $answer): bool
    {
        self::cleanup();

        $challengeId = trim((string) $challengeId);
        $answer = trim((string) $answer);
        if ($challengeId === '' || $answer === '') {
            return false;
        }

        $challenge = $_SESSION[self::SESSION_KEY][$scope][$challengeId] ?? null;
        unset($_SESSION[self::SESSION_KEY][$scope][$challengeId]);

        if (!self::isValidChallenge($challenge)) {
            return false;
        }

        $normalizedAnswer = preg_replace('/\s+/', '', $answer) ?? '';

        return hash_equals((string) ($challenge['answer_hash'] ?? ''), hash('sha256', $normalizedAnswer));
    }

    private static function generateMathChallenge(): array
    {
        $left = random_int(3, 18);
        $right = random_int(2, 9);

        if (random_int(0, 1) === 1) {
            return ['Quanto é ' . $left . ' + ' . $right . '?', (string) ($left + $right)];
        }

        if ($right > $left) {
            [$left, $right] = [$right + 5, $left];
        }

        return ['Quanto é ' . $left . ' - ' . $right . '?', (string) ($left - $right)];
    }

    private static function cleanup(): void
    {
        if (empty($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            return;
        }

        $now = time();
        foreach ($_SESSION[self::SESSION_KEY] as $scope => $challenges) {
            if (!is_array($challenges)) {
                unset($_SESSION[self::SESSION_KEY][$scope]);
                continue;
            }

            foreach ($challenges as $challengeId => $challenge) {
                $expiresAt = (int) ($challenge['expires_at'] ?? 0);
                if ($expiresAt <= $now) {
                    unset($_SESSION[self::SESSION_KEY][$scope][$challengeId]);
                }
            }

            if (empty($_SESSION[self::SESSION_KEY][$scope])) {
                unset($_SESSION[self::SESSION_KEY][$scope]);
            }
        }

        if (empty($_SESSION[self::SESSION_KEY])) {
            unset($_SESSION[self::SESSION_KEY]);
        }
    }

    private static function isValidChallenge(mixed $challenge): bool
    {
        if (!is_array($challenge)) {
            return false;
        }

        $question = trim((string) ($challenge['question'] ?? ''));
        $answerHash = trim((string) ($challenge['answer_hash'] ?? ''));
        $expiresAt = (int) ($challenge['expires_at'] ?? 0);

        return $question !== ''
            && $answerHash !== ''
            && $expiresAt > time();
    }
}
