<?php

use App\Jobs\SyncOpenSeo;
use App\Models\Host;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    Host::query()->chunkById(50, function ($hosts) {
        foreach ($hosts as $host) {
            SyncOpenSeo::dispatch($host->id);
        }
    });
})->daily();
