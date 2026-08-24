<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('oasse:aggregate-metrics')->dailyAt('00:15');
Schedule::command('oasse:scan-alerts')->hourly();
Schedule::command('oasse:refresh-overdue')->hourly();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
