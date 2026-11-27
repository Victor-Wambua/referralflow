<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Security\Totp;
use Illuminate\Console\Command;

class DemoTotp extends Command
{
    protected $signature = 'demo:totp {email}';
    protected $description = 'Print the current TOTP code for a SYNTHETIC demo user (local/testing environments only)';

    public function handle(): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('Only available in local/testing environments.');

            return self::FAILURE;
        }
        $u = User::query()->where('email', $this->argument('email'))->first();
        if (! $u || ! $u->mfa_secret) {
            $this->error('No such user or no MFA secret.');

            return self::FAILURE;
        }
        $this->line(Totp::code($u->mfa_secret));

        return self::SUCCESS;
    }
}
