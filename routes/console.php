<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Without these, the retention settings are never applied: the metrics table
// grows by a row per application per interval and the event log by a row per
// message, forever.
Schedule::command('patchbay:prune-metrics')->daily();

// Hourly rather than daily: the log keeps a day by default, and a busy server
// writes a row per message, so a day's worth arrives a good deal faster than
// a day's worth of samples.
Schedule::command('patchbay:prune-events')->hourly();
