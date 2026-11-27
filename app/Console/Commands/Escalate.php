<?php

namespace App\Console\Commands;

use App\Domain\EscalationService;
use Illuminate\Console\Command;

class Escalate extends Command
{
    protected $signature = 'referrals:escalate';
    protected $description = 'Escalate referrals that have not been acknowledged within their SLA';

    public function handle(): int
    {
        $this->info('Escalated '.EscalationService::run().' referral(s).');

        return self::SUCCESS;
    }
}
