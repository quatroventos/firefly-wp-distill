<?php

use App\Http\Controllers\Api\V1\AuditQueryController;
use App\Http\Controllers\Api\V1\EnqueueController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\HostsSyncController;
use App\Http\Controllers\Api\V1\JobsController;
use App\Http\Controllers\Api\V1\WorkerController;
use App\Http\Middleware\VerifySiteHmac;
use App\Http\Middleware\VerifyWorkerToken;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', HealthController::class);

Route::prefix('v1/sites/{site}')->middleware(VerifySiteHmac::class)->group(function () {
    Route::post('/enqueue', EnqueueController::class);
    Route::put('/hosts', HostsSyncController::class);
    Route::post('/jobs', [JobsController::class, 'store']);
    Route::get('/jobs/{id}', [JobsController::class, 'show']);
    Route::get('/hosts/{host}/audits', AuditQueryController::class);
});

Route::prefix('v1/worker')->middleware(VerifyWorkerToken::class)->group(function () {
    Route::get('/jobs/next', [WorkerController::class, 'next']);
    Route::post('/jobs/{id}/events', [WorkerController::class, 'events']);
    Route::post('/jobs/{id}/audits', [WorkerController::class, 'audits']);
    Route::post('/jobs/{id}/suggestions', [WorkerController::class, 'suggestions']);
    Route::post('/jobs/{id}/delivered', [WorkerController::class, 'delivered']);
    Route::post('/jobs/{id}/push-build', [WorkerController::class, 'pushBuild']);
});
