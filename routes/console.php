<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Without this, patchbay.metrics.retain_days is never applied and the
// metrics table grows by a row per application per interval, forever.
Schedule::command('patchbay:prune-metrics')->daily();
