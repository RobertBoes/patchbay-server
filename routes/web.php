<?php

use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

Route::domain(config('dashboard.domain'))
    ->group(function () {
        Route::get('/', LandingController::class)->name('landing');
    });
