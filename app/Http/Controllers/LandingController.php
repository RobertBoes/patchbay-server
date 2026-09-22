<?php

namespace App\Http\Controllers;

use App\Models\Metric;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use RobertBoes\Patchbay\Server\HealthCheck;

class LandingController extends Controller
{
    public function __invoke(HealthCheck $health): View
    {
        abort_unless(config()->boolean('dashboard.landing.enabled'), 404);

        return view('landing', [
            'panel' => Filament::getPanel('admin'),
            'health' => $health->status(),
            'activity' => config()->boolean('dashboard.landing.metrics')
                ? Cache::remember('landing:activity', 60, fn (): array => $this->activity())
                : null,
        ]);
    }

    /**
     * Fleet-wide totals for the last day, bucketed by hour.
     *
     * @return array{connections: int, messages: int, hourly: array<int, int>}
     */
    protected function activity(): array
    {
        $now = CarbonImmutable::now();
        $start = $now->startOfHour()->subHours(23);

        // One row per flush of each server, however many applications it
        // carries, so this stays small as the fleet grows. Unscoped: a
        // signed-in visitor sees the same public totals as anyone else.
        $flushes = Metric::withoutGlobalScopes()
            ->where('recorded_at', '>=', $start)
            ->select('server', 'recorded_at')
            ->selectRaw('SUM(connections) as connections, SUM(messages_sent + messages_received) as messages')
            ->groupBy('server', 'recorded_at')
            ->orderBy('recorded_at')
            ->get();

        $hourly = array_fill(0, 24, 0);

        foreach ($flushes as $flush) {
            $hour = (int) floor($start->diffInSeconds($flush->recorded_at) / 3600);
            $hourly[min($hour, 23)] += (int) $flush->messages;
        }

        return [
            'connections' => $this->connectionsNow($flushes, $now),
            'messages' => array_sum($hourly),
            'hourly' => $hourly,
        ];
    }

    /**
     * Each server's latest flush, if it is recent enough to still be true.
     *
     * @param  Collection<int, Metric>  $flushes
     */
    protected function connectionsNow(Collection $flushes, CarbonImmutable $now): int
    {
        $fresh = $now->subSeconds(2 * (int) config('patchbay.metrics.interval', 60));

        return (int) $flushes
            ->filter(fn (Metric $flush): bool => $flush->recorded_at->greaterThanOrEqualTo($fresh))
            ->groupBy('server')
            ->sum(fn (Collection $server): int => (int) $server->last()->connections);
    }
}
