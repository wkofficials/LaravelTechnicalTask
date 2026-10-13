<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Requires the scheduler cron entry (see deployment notes).
Schedule::command('exports:prune')->hourly();
Schedule::command('imports:prune')->dailyAt('02:30');
Schedule::command('billing:run')->dailyAt('03:00')->withoutOverlapping();
// Laravel removes failed-job records after a week so the table can't grow forever.
Schedule::command('queue:prune-failed', ['--hours' => 24 * 7])->daily();
