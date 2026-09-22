<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use RobertBoes\Patchbay\Server\HealthCheck;

class LandingController extends Controller
{
    public function __invoke(HealthCheck $health): View
    {
        abort_unless(config()->boolean('dashboard.landing.enabled'), 404);

        return view('landing', [
            'health' => $health->status(),
        ]);
    }
}
