<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Host;
use App\Models\Site;
use App\Services\Debouncer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EnqueueController extends Controller
{
    public function __invoke(Request $request, string $site, Debouncer $debouncer): JsonResponse
    {
        /** @var Site $model */
        $model = $request->attributes->get('ffd_site');
        $items = $request->input('items', []);
        if (!is_array($items) || $items === []) {
            return response()->json(['message' => 'items obrigatório'], 422);
        }
        $hostsTouched = [];
        foreach ($items as $item) {
            $hostname = strtolower((string) ($item['host'] ?? ''));
            $host = Host::query()->where('site_id', $model->id)->where('host', $hostname)->first();
            if (!$host) {
                continue;
            }
            $path = (string) ($item['path'] ?? '/');
            $reason = (string) ($item['reason'] ?? 'manual');
            $scope = $reason === 'host_meta' ? 'host_meta' : 'host';
            $debouncer->push($model, $host, $scope, [$path]);
            $hostsTouched[$host->id] = $host->host;
        }
        return response()->json(['ok' => true, 'hosts' => array_values($hostsTouched)]);
    }
}
