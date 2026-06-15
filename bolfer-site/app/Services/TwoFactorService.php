<?php

declare(strict_types=1);

namespace App\Services;

final class TwoFactorService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const RECOVERY_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function generateSecret(int $length = 32): string
    {
        $length = max(16, $length);
        $secret = '';
        $maxIndex = strlen(self::BASE32_ALPHABET) - 1;

        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_ALPHABET[random_int(0, $maxIndex)];
        }

        return $secret;
    }

    public function formatSecret(string $secret): string
    {
        return trim(chunk_split(strtoupper(trim($secret)), 4, ' '));
    }

    public function otpAuthUri(string $label, string $secret, ?string $issuer = null): string
    {
        $issuer = trim((string) ($issuer ?? env('APP_NAME', 'Bolfer Official')));
        if ($issuer === '') {
            $issuer = 'Bolfer Official';
        }

        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            rawurlencode($issuer),
            rawurlencode($label),
            rawurlencode(strtoupper(trim($secret))),
            rawurlencode($issuer)
        );
    }

    public function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        $secret = strtoupper(trim($secret));
        $code = preg_replace('/\D+/', '', $code) ?? '';

        if ($secret === '' || strlen($code) !== 6) {
            return false;
        }

        $currentCounter = (int) floor(time() / 30);
        for ($offset = -max(0, $window); $offset <= max(0, $window); $offset++) {
            if (hash_equals($this->totpCode($secret, $currentCounter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    public function generateRecoveryCodes(int $count = 8): array
    {
        $count = max(4, min(12, $count));
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = $this->randomRecoveryCode();
        }

        return $codes;
    }

    public function hashRecoveryCodes(array $codes): array
    {
        $hashes = [];
        foreach ($codes as $code) {
            $normalized = $this->normalizeRecoveryCode((string) $code);
            if ($normalized === '') {
                continue;
            }

            $hashes[] = hash('sha256', $normalized);
        }

        return array_values(array_unique($hashes));
    }

    public function consumeRecoveryCode(string $submittedCode, array $storedHashes): array
    {
        $normalized = $this->normalizeRecoveryCode($submittedCode);
        if ($normalized === '') {
            return [
                'ok' => false,
                'remaining_hashes' => $storedHashes,
            ];
        }

        $targetHash = hash('sha256', $normalized);
        $remaining = [];
        $consumed = false;

        foreach ($storedHashes as $storedHash) {
            $storedHash = trim((string) $storedHash);
            if ($storedHash === '') {
                continue;
            }

            if (!$consumed && hash_equals($storedHash, $targetHash)) {
                $consumed = true;
                continue;
            }

            $remaining[] = $storedHash;
        }

        return [
            'ok' => $consumed,
            'remaining_hashes' => $remaining,
        ];
    }

    private function totpCode(string $secret, int $counter): string
    {
        $binarySecret = $this->base32Decode($secret);
        $counterBytes = pack('N2', 0, $counter);
        $hash = hash_hmac('sha1', $counterBytes, $binarySecret, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $segment = substr($hash, $offset, 4);
        $value = unpack('N', $segment)[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        if ($secret === '') {
            return '';
        }

        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        foreach (str_split($secret) as $character) {
            $value = strpos(self::BASE32_ALPHABET, $character);
            if ($value === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $value;
            $bitsLeft += 5;

            while ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }

    private function randomRecoveryCode(): string
    {
        $groups = [];
        $maxIndex = strlen(self::RECOVERY_ALPHABET) - 1;

        for ($group = 0; $group < 3; $group++) {
            $chunk = '';
            for ($i = 0; $i < 4; $i++) {
                $chunk .= self::RECOVERY_ALPHABET[random_int(0, $maxIndex)];
            }
            $groups[] = $chunk;
        }

        return implode('-', $groups);
    }

    private function normalizeRecoveryCode(string $code): string
    {
        $code = strtoupper(trim($code));
        $code = preg_replace('/[^A-Z0-9]/', '', $code) ?? '';

        return $code;
    }
}
