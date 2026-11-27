<?php

namespace App\Security;

/**
 * Keyed deterministic hash (HMAC-SHA-256) used ONLY for same-facility exact matching of encrypted
 * identifiers. Separate key from the encrypter. Rotation: change the key and rebuild (docs/SECURITY.md).
 */
final class BlindIndex
{
    public static function make(string $purpose, string $value): string
    {
        return hash_hmac('sha256', $purpose.'|'.self::normalise($value), self::key());
    }

    public static function normalise(string $v): string
    {
        $v = mb_strtolower(trim($v));

        return (string) preg_replace('/\s+/', ' ', $v);
    }

    public static function nameDob(string $name, string $dob, string $sex): string
    {
        $tokens = array_values(array_filter(preg_split('/[\s,.\-]+/', self::normalise($name)) ?: []));
        sort($tokens);

        return self::make('name_dob', implode(' ', $tokens).'|'.$dob.'|'.$sex);
    }

    private static function key(): string
    {
        $k = config('referralflow.blind_index_key');
        if ($k) {
            return $k;
        }
        $appKey = (string) config('app.key');
        $raw = str_starts_with($appKey, 'base64:') ? base64_decode(substr($appKey, 7)) : $appKey;

        return hash_hkdf('sha256', (string) $raw, 32, 'referralflow-blind-index');
    }
}
