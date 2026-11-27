<?php

namespace App\Security;

final class Masker
{
    /** "Amina Wekesa" -> "A. W." */
    public static function initials(?string $name): string
    {
        if (! $name) {
            return '—';
        }
        $parts = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: []));

        return implode(' ', array_map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)).'.', array_slice($parts, 0, 3)));
    }

    /** Show only the last two characters of an identifier. */
    public static function identifier(?string $id): string
    {
        if (! $id) {
            return '—';
        }
        $len = mb_strlen($id);

        return str_repeat('•', max(4, $len - 2)).mb_substr($id, -2);
    }
}
