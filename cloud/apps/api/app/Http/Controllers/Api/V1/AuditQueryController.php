<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Host;
use App\Models\PageAudit;
use App\Models\Site;
use Illuminate\Http\JsonResponse;

final class AuditQueryController extends Controller
{
    public function __invoke(string $site, string $host): JsonResponse
    {
        $siteModel = Site::query()->where('site_key_id', $site)->firstOrFail();
        $hostModel = Host::query()->where('site_id', $siteModel->id)->where('host', $host)->firstOrFail();
        $audits = PageAudit::query()->where('host_id', $hostModel->id)->orderByDesc('audited_at')->get();
        return response()->json(['audits' => $audits]);
    }
}
