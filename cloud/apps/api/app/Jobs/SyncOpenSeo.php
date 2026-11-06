<?php

namespace App\Jobs;

use App\Models\Host;
use App\Models\PageAudit;
use App\Models\Suggestion;
use Firefly\OpenSeo\OpenSeoClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SyncOpenSeo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $hostId)
    {
    }

    public function handle(OpenSeoClient $client): void
    {
        $host = Host::query()->with('site')->find($this->hostId);
        if (!$host) {
            return;
        }
        $projectId = $host->openseo_project_id ?: $host->site->openseo_project_id;
        $audits = PageAudit::query()->where('host_id', $host->id)->where('phase', 'build')->get();
        foreach ($audits as $audit) {
            $slice = $client->projectSlice($projectId, $host->host, $audit->path);
            $audit->openseo_slice = $slice;
            $audit->save();
        }
        foreach ($client->opportunities($projectId, $host->host) as $opp) {
            $title = (string) ($opp['title'] ?? 'oportunidade OpenSEO');
            $path = (string) ($opp['path'] ?? '/');
            $hash = hash('sha256', $host->host . $path . 'openseo' . mb_strtolower($title));
            Suggestion::query()->firstOrCreate(
                ['dedupe_hash' => $hash],
                [
                    'site_id' => $host->site_id,
                    'host_id' => $host->id,
                    'url' => ($host->https ? 'https://' : 'http://') . $host->host . $path,
                    'category' => (string) ($opp['category'] ?? 'content_seo_geo'),
                    'severity' => (string) ($opp['severity'] ?? 'medium'),
                    'title' => $title,
                    'body' => (string) ($opp['body'] ?? ''),
                    'status' => 'open',
                    'source' => 'openseo',
                    'payload_json' => $opp,
                ]
            );
        }
    }
}
