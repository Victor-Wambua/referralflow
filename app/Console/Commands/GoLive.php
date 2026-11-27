<?php

namespace App\Console\Commands;

use App\Security\GoLiveGate;
use Illuminate\Console\Command;

class GoLive extends Command
{
    protected $signature = 'golive:gate';
    protected $description = 'List unmet prerequisites before ANY real patient-data pilot (exits non-zero while any are unmet)';

    public function handle(): int
    {
        $items = GoLiveGate::items();
        $this->table(['Area', 'Requirement', 'Status'], array_map(fn ($i) => [$i['area'], $i['requirement'], $i['met'] ? 'met' : 'UNMET'], $items));
        $unmet = count(GoLiveGate::unmet());
        $this->line("$unmet of ".count($items).' prerequisites unmet. ReferralFlow is for SYNTHETIC data only until all are met and counsel has signed off.');

        return $unmet ? self::FAILURE : self::SUCCESS;
    }
}
