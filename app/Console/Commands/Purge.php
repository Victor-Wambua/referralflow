<?php

namespace App\Console\Commands;

use App\Domain\RetentionService;
use App\Security\AuditLog;
use Illuminate\Console\Command;

class Purge extends Command
{
    protected $signature = 'retention:purge {--dry-run : Count what would be purged without changing anything}';
    protected $description = 'Apply retention settings: remove clinical content from closed referrals past their period (legal holds respected)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $r = RetentionService::purge($dry);
        if (! $dry) {
            AuditLog::record('retention.run', 'settings', 'retention', $r + ['dry_run' => false]);
        }
        $this->table(['referrals', 'messages', 'attachments', 'drafts', 'held (skipped)'], [[$r['referrals'], $r['messages'], $r['attachments'], $r['drafts'], $r['held']]]);
        $this->line($dry ? 'Dry run: nothing changed.' : 'Purge complete.');

        return self::SUCCESS;
    }
}
