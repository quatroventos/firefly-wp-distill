<?php

namespace App\Jobs;

use App\Models\Host;
use App\Models\Job;
use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class DispatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $siteId,
        public int $hostId,
        public string $scope,
        public string $cacheKey
    ) {
    }

    public function handle(): void
    {
        $urls = Cache::pull($this->cacheKey, []);
        $site = Site::query()->find($this->siteId);
        $host = Host::query()->find($this->hostId);
        if (!$site || !$host) {
            return;
        }
        Job::query()->create([
            'site_id' => $site->id,
            'host_id' => $host->id,
            'scope' => $this->scope,
            'status' => 'queued',
            'urls_json' => $urls,
            'job_token' => Str::random(48),
        ]);
    }
}
