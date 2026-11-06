<?php

namespace App\Jobs;

use App\Models\Job as DistillJob;
use App\Services\DeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RunSuggestAgent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $jobId)
    {
    }

    public function handle(DeliveryService $delivery): void
    {
        $job = DistillJob::query()->with(['site', 'host'])->find($this->jobId);
        if (!$job || !$job->site) {
            return;
        }
        $delivery->webhook($job->site, [
            'event' => 'job.suggestions',
            'job_id' => $job->id,
            'host' => $job->host?->host,
            'status' => $job->status,
            'occurred_at' => now()->toIso8601String(),
        ]);
    }
}
