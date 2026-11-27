<?php

namespace App\Console\Commands;

use App\Security\AuditLog;
use Illuminate\Console\Command;

class VerifyAudit extends Command
{
    protected $signature = 'audit:verify';
    protected $description = 'Verify the audit log hash chain';

    public function handle(): int
    {
        $r = AuditLog::verify();
        $this->line($r['message']);

        return $r['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
