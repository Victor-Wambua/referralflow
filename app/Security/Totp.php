<?php

namespace App\Security;

/** RFC 6238 TOTP (HMAC-SHA1, 30 s step, 6 digits by default) with RFC 4648 base32. */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function code(string $base32Secret, ?int $timestamp = null, int $digits = 6, int $period = 30): string
    {
        return self::codeForStep($base32Secret, intdiv($timestamp ?? time(), $period), $digits);
    }

    public static function codeForStep(string $base32Secret, int $step, int $digits = 6): string
    {
        $key = self::base32Decode($base32Secret);
        $hash = hash_hmac('sha1', pack('J', $step), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $bin = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($bin % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Verify with +/- $window steps. Returns the matched step (for replay protection) or null.
     * Steps at or below $lastUsedStep are rejected.
     */
    public static function verify(string $base32Secret, string $code, ?int $lastUsedStep = null, int $window = 1, ?int $now = null, int $period = 30): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (! preg_match('/^\d{6}$/', $code)) {
            return null;
        }
        $current = intdiv($now ?? time(), $period);
        for ($i = -$window; $i <= $window; $i++) {
            $step = $current + $i;
            if ($lastUsedStep !== null && $step <= $lastUsedStep) {
                continue;
            }
            if (hash_equals(self::codeForStep($base32Secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    public static function uri(string $secret, string $account, string $issuer = 'ReferralFlow'): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer).':'.rawurlencode($account)
            .'?secret='.$secret.'&issuer='.rawurlencode($issuer).'&algorithm=SHA1&digits=6&period=30';
    }

    public static function base32Encode(string $bin): string
    {
        $bits = '';
        foreach (str_split($bin) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    public static function base32Decode(string $b32): string
    {
        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $b32) ?? '');
        $bits = '';
        foreach (str_split($b32) as $c) {
            $bits .= str_pad(decbin((int) strpos(self::ALPHABET, $c)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }
}
