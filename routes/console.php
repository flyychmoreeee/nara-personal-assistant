<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Monday morning reminder (for Monday's classes)
Schedule::command('app:send-morning-reminders')
    ->mondays()
    ->at('05:00');

// Afternoon H-1 reminder at 16:00 WIB (Monday to Friday for next day's classes)
Schedule::command('app:send-d-minus-one-reminders')
    ->weekdays()
    ->at('16:00');

// Pre-class 1-hour reminder on class days
Schedule::command('app:send-preclass-reminders')
    ->everyMinute();

// Sync holidays monthly
Schedule::command('app:sync-holidays')
    ->monthly();


