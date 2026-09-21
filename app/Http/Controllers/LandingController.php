<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use RobertBoes\Patchbay\Server\ServerApi;

class LandingController extends Controller
{
    public function __invoke(ServerApi $server): View
    {
        abort_unless(config()->boolean('dashboard.landing.enabled'), 404);

        return view('landing', [
            'running' => $server->isRunning(),
        ]);
    }
}
