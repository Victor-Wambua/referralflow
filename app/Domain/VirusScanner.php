<?php

namespace App\Domain;

/** Antivirus hook. Bind a real implementation (ClamAV, a cloud scanner) in a service provider. */
interface VirusScanner
{
    /** @return 'clean'|'infected'|'not_scanned' */
    public function scan(string $contents): string;

    /** False for placeholder implementations that perform no scanning. */
    public function isOperational(): bool;
}
