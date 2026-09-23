<?php

use App\Support\BackupSettings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('forms:close-expired')->everyFifteenMinutes();

Schedule::command('backup:run --type=auto')
    ->everyMinute()
    ->when(fn (): bool => BackupSettings::shouldRunAutoNow())
    ->withoutOverlapping(120);
