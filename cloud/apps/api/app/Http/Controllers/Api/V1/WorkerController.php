<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\RunSuggestAgent;
use App\Jobs\SyncOpenSeo;
use App\Models\Build;
use App\Models\Job;
use App\Models\LighthouseRun;
use App\Models\PageAudit;
use App\Models\Suggestion;
use App\Services\BypassSigner;
use App\Services\DeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WorkerController extends Controller
{
    public function next(BypassSigner $signer, DeliveryService $delivery): JsonResponse
    {
        $job = Job::query()->with(['site', 'host'])->where('status', 'queued')->orderBy('id')->first();
        if (!$job || !$job->site || !$job->host) {
            return response()->json(['job' => null]);
        }
        $job->status = 'crawling';
        $job->started_at = now();
        $job->save();

        $host = $job->host;
        $site = $job->site;
        $routes = $host->routes_json ?: [];
        $paths = $job->urls_json ?: ['/'];
        if ($job->scope === 'full' || $paths === []) {
            $paths = array_values(array_unique(array_merge(['/'], array_column($routes, 'path'))));
        }
        $signed = [];
        foreach ($paths as $path) {
            $path = is_string($path) ? $path : '/';
            $token = $signer->sign($site, $host->host, $path);
            $scheme = $host->https ? 'https' : 'http';
            $signed[] = [
                'path' => $path,
                'url' => $scheme . '://' . $host->host . ($path === '/' ? '/' : rtrim($path, '/')),
                'ff_sig' => $token['ff_sig'],
                'ff_exp' => $token['ff_exp'],
            ];
        }
        $buildId = gmdate('Ymd\THis\Z') . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
        $pluginToken = $delivery->issueJobToken($site, $job, $host->host, $buildId);
        if (is_string($pluginToken) && $pluginToken !== '') {
            $job->job_token = $pluginToken;
            $job->save();
        }

        return response()->json([
            'job' => [
                'id' => $job->id,
                'site' => [
                    'id' => $site->id,
                    'home_url' => $site->home_url,
                    'site_key_id' => $site->site_key_id,
                    'openseo_project_id' => $site->openseo_project_id,
                ],
                'scope' => $job->scope,
                'host' => [
                    'id' => $host->id,
                    'host' => $host->host,
                    'post_id' => $host->post_id,
                    'https' => $host->https,
                    'flags' => [
                        'robots_index' => $host->flag_robots_index,
                        'sitemap' => $host->flag_sitemap,
                        'llm_txt' => $host->flag_llm_txt,
                        'static_enabled' => $host->static_enabled,
                    ],
                    'routes' => $routes,
                    'openseo_project_id' => $host->openseo_project_id,
                    'gsc_property' => $host->gsc_property,
                    'origin_ip' => null,
                ],
                'paths' => $paths,
                'signed_urls' => $signed,
                'build_id' => $buildId,
                'bypass' => ['query_name' => 'firefly-distill'],
                'delivery' => [
                    'channel' => $site->delivery ?: 'rest',
                    'plugin_rest_url' => $site->plugin_rest_url,
                    'job_token' => $pluginToken,
                ],
            ],
        ]);
    }

    public function events(Request $request, int $id): JsonResponse
    {
        $job = Job::query()->findOrFail($id);
        $status = (string) $request->input('status', $job->status);
        $job->status = $status;
        if ($request->filled('error')) {
            $job->error = (string) $request->input('error');
        }
        if (in_array($status, ['completed', 'completed_with_warnings', 'failed'], true)) {
            $job->finished_at = now();
        }
        $job->save();
        return response()->json(['ok' => true, 'status' => $job->status]);
    }

    public function audits(Request $request, int $id, DeliveryService $delivery): JsonResponse
    {
        $job = Job::query()->with(['site', 'host'])->findOrFail($id);
        $items = $request->input('audits', []);
        foreach ($items as $item) {
            PageAudit::query()->create([
                'site_id' => $job->site_id,
                'host_id' => $job->host_id,
                'path' => $item['path'] ?? '/',
                'url' => $item['url'] ?? '',
                'phase' => $item['phase'] ?? 'build',
                'performance' => $item['performance'] ?? null,
                'seo' => $item['seo'] ?? null,
                'accessibility' => $item['accessibility'] ?? null,
                'best_practices' => $item['best_practices'] ?? null,
                'psi_json' => $item['psi_json'] ?? null,
                'openseo_slice' => $item['openseo_slice'] ?? null,
                'audited_at' => now(),
            ]);
            LighthouseRun::query()->create([
                'job_id' => $job->id,
                'phase' => $item['phase'] ?? 'build',
                'scores_json' => $item,
            ]);
        }
        if ($job->host_id) {
            SyncOpenSeo::dispatch($job->host_id);
        }
        $delivery->webhook($job->site, [
            'event' => 'job.audits',
            'job_id' => $job->id,
            'host' => $job->host?->host,
            'status' => $job->status,
            'occurred_at' => now()->toIso8601String(),
            'audits' => $items,
        ]);
        return response()->json(['ok' => true]);
    }

    public function suggestions(Request $request, int $id, DeliveryService $delivery): JsonResponse
    {
        $job = Job::query()->with(['site', 'host'])->findOrFail($id);
        $items = $request->input('suggestions', []);
        $stored = [];
        foreach ($items as $item) {
            $title = (string) ($item['title'] ?? '');
            $url = (string) ($item['url'] ?? '');
            $category = (string) ($item['category'] ?? 'content_seo_geo');
            $hash = hash('sha256', ($job->host?->host ?? '') . $url . $category . mb_strtolower($title));
            $row = Suggestion::query()->firstOrCreate(
                ['dedupe_hash' => $hash],
                [
                    'site_id' => $job->site_id,
                    'host_id' => $job->host_id,
                    'url' => $url,
                    'category' => $category,
                    'severity' => $item['severity'] ?? 'medium',
                    'title' => $title,
                    'body' => $item['body'] ?? '',
                    'status' => 'open',
                    'source' => $item['source'] ?? 'agent',
                    'payload_json' => $item,
                ]
            );
            $stored[] = $row->toArray() + ['cloud_suggestion_id' => (string) $row->id];
        }
        RunSuggestAgent::dispatch($job->id);
        $delivery->webhook($job->site, [
            'event' => 'job.suggestions',
            'job_id' => $job->id,
            'host' => $job->host?->host,
            'status' => $job->status,
            'occurred_at' => now()->toIso8601String(),
            'suggestions' => $stored,
        ]);
        return response()->json(['ok' => true]);
    }

    public function pushBuild(Request $request, int $id, DeliveryService $delivery): JsonResponse
    {
        $job = Job::query()->with(['site', 'host'])->findOrFail($id);
        $site = $job->site;
        $base = rtrim((string) $site->plugin_rest_url, '/');
        if ($base === '') {
            return response()->json(['message' => 'plugin_rest_url ausente'], 422);
        }
        $payload = [
            'host' => $request->input('host'),
            'build_id' => $request->input('build_id'),
            'job_token' => $job->job_token,
            'activate' => true,
            'manifest' => $request->input('manifest'),
            'files' => $request->input('files'),
        ];
        $path = parse_url($base . '/receive-build', PHP_URL_PATH) ?: '/wp-json/firefly-distill/v1/receive-build';
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $res = \Illuminate\Support\Facades\Http::withHeaders($delivery->signPlugin($site, 'POST', $path, $body))
            ->timeout(120)
            ->withBody($body, 'application/json')
            ->post($base . '/receive-build');
        if (!$res->successful()) {
            return response()->json(['message' => 'receive-build falhou', 'status' => $res->status(), 'body' => $res->body()], 502);
        }
        return response()->json(['ok' => true]);
    }

    public function delivered(Request $request, int $id, DeliveryService $delivery): JsonResponse
    {
        $job = Job::query()->with(['site', 'host'])->findOrFail($id);
        Build::query()->create([
            'job_id' => $job->id,
            'host_id' => $job->host_id,
            'build_id' => (string) $request->input('build_id'),
            'checksum' => (string) $request->input('checksum', ''),
            'delivered_at' => now(),
            'activated_at' => now(),
        ]);
        if ($job->host) {
            $job->host->current_build_id = (string) $request->input('build_id');
            $job->host->save();
        }
        $delivery->webhook($job->site, [
            'event' => 'build.delivered',
            'job_id' => $job->id,
            'host' => $job->host?->host,
            'status' => $job->status,
            'build_id' => $request->input('build_id'),
            'occurred_at' => now()->toIso8601String(),
        ]);
        return response()->json(['ok' => true]);
    }
}
