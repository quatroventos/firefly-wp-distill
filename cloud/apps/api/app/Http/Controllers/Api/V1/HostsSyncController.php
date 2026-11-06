<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Host;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HostsSyncController extends Controller
{
    public function __invoke(Request $request, string $site): JsonResponse
    {
        /** @var Site $model */
        $model = $request->attributes->get('ffd_site');
        if ($request->filled('plugin_rest_url')) {
            $model->plugin_rest_url = rtrim((string) $request->input('plugin_rest_url'), '/');
            $model->save();
        }
        if ($request->filled('home_url')) {
            $model->home_url = (string) $request->input('home_url');
            $model->save();
        }
        $incoming = $request->input('hosts', []);
        if (!is_array($incoming)) {
            return response()->json(['message' => 'hosts inválido'], 422);
        }
        $seen = [];
        foreach ($incoming as $row) {
            $hostname = strtolower((string) ($row['host'] ?? ''));
            if ($hostname === '') {
                continue;
            }
            $host = Host::query()->updateOrCreate(
                ['site_id' => $model->id, 'host' => $hostname],
                [
                    'post_id' => (int) ($row['post_id'] ?? 0),
                    'routes_json' => $row['routes'] ?? [],
                    'https' => (bool) ($row['https'] ?? true),
                    'flag_robots_index' => (bool) ($row['flag_robots_index'] ?? true),
                    'flag_sitemap' => (bool) ($row['flag_sitemap'] ?? true),
                    'flag_llm_txt' => (bool) ($row['flag_llm_txt'] ?? true),
                    'static_enabled' => (bool) ($row['static_enabled'] ?? false),
                    'openseo_project_id' => $row['openseo_project_id'] ?? null,
                    'gsc_property' => $row['gsc_property'] ?? null,
                    'is_primary' => (bool) ($row['is_primary'] ?? false),
                ]
            );
            $seen[] = $host->id;
        }
        Host::query()->where('site_id', $model->id)->whereNotIn('id', $seen ?: [0])->delete();
        return response()->json(['ok' => true, 'count' => count($seen)]);
    }
}
