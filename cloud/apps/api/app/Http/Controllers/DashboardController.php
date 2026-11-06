<?php

namespace App\Http\Controllers;

use App\Models\Host;
use App\Models\Job;
use App\Models\PageAudit;
use App\Models\Site;
use App\Models\Suggestion;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $hostFilter = $request->string('host')->toString();
        $sites = Site::query()->with('hosts')->orderBy('id')->get();
        $audits = PageAudit::query()
            ->when($hostFilter !== '', fn ($q) => $q->whereHas('host', fn ($h) => $h->where('host', $hostFilter)))
            ->orderByDesc('audited_at')
            ->limit(200)
            ->get();
        $jobs = Job::query()->with('host')->orderByDesc('id')->limit(30)->get();
        $suggestions = Suggestion::query()->orderByDesc('id')->limit(50)->get();
        $hosts = Host::query()->orderBy('host')->get();
        return view('dashboard.index', compact('sites', 'audits', 'jobs', 'suggestions', 'hosts', 'hostFilter'));
    }
}
