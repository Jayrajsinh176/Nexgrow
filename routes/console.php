<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:pay-daily-nft-bonus')->everyTwoMinutes();
Schedule::command('app:process-bonus-boosters')->everyTwoMinutes();
Schedule::command('app:process-team-performance-bonuses')->everyTwoMinutes();
Schedule::command('app:process-monthly-loyalty-bonus')->daily();
Schedule::command('app:process-royalty-bonus')->daily();
Schedule::command('app:process-team-development-bonus')->everyTwoMinutes();
