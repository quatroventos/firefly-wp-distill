<?php

namespace App\Services;

use App\Jobs\DispatchJob;
use App\Models\Host;
use App\Models\Site;
use Illuminate\Support\Facades\Cache;

final class Debouncer
{
    public function push(Site $site, Host $host, string $scope, array $urls, int $seconds = 60): void
    {
        $key = 'ffd_debounce_' . $site->id . '_' . $host->id . '_' . $scope;
        $existing = Cache::get($key, []);
        $merged = array_values(array_unique(array_merge($existing, $urls), SORT_REGULAR));
        Cache::put($key, $merged, $seconds + 5);
        DispatchJob::dispatch($site->id, $host->id, $scope, $key)->delay(now()->addSeconds($seconds));
    }
}
