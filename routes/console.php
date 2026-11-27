<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('referrals:escalate')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('retention:purge')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('audit:verify')->dailyAt('03:00');
