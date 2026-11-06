<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Host;
use App\Models\Job;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class JobsController extends Controller
{
    public function store(Request $request, string $site): JsonResponse
    {
        /** @var Site $model */
        $model = $request->attributes->get('ffd_site') ?? Site::query()->where('site_key_id', $site)->firstOrFail();
        $host = null;
        if ($request->filled('host')) {
            $host = Host::query()->where('site_id', $model->id)->where('host', $request->input('host'))->first();
        }
        $job = Job::query()->create([
            'site_id' => $model->id,
            'host_id' => $host?->id,
            'scope' => (string) $request->input('scope', 'host'),
            'status' => 'queued',
            'urls_json' => $request->input('paths', []),
            'job_token' => Str::random(48),
        ]);
        return response()->json(['job_id' => $job->id, 'status' => $job->status], 201);
    }

    public function show(Request $request, string $site, int $id): JsonResponse
    {
        $job = Job::query()->with(['host', 'pages'])->findOrFail($id);
        return response()->json($job);
    }
}
