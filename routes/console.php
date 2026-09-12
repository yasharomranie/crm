<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Generates today's task instance for every active recurring checklist
// template (e.g. "چک‌لیست نظافت اتاق") shortly after midnight, Tehran time.
Schedule::command('checklists:generate')
    ->dailyAt('00:30')
    ->timezone('Asia/Tehran')
    ->withoutOverlapping();
