<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('horizon:snapshot')->everyFiveMinutes();

Schedule::command('certificates:renew-expiring')->dailyAt('04:00');

Schedule::command('servers:collect-metrics')->everyFiveMinutes();

Schedule::command('security:scan')->everyMinute()->withoutOverlapping();

Schedule::command('security:cleanup')->dailyAt('03:00');
