<?php

namespace App\Domain;

/** Placeholder: performs NO scanning. Files are marked not_scanned. */
final class NullScanner implements VirusScanner
{
    public function isOperational(): bool
    {
        return false;
    }

    public function scan(string $contents): string
    {
        return 'not_scanned';
    }
}
