<?php

use App\Http\Controllers\Api\WebsiteMonitoringController;
use Illuminate\Support\Facades\Route;

Route::prefix('monitoring/websites/{website}')
    ->middleware('throttle:60,1')
    ->group(function (): void {
        Route::post('/checks', [WebsiteMonitoringController::class, 'store'])->name('api.monitoring.websites.checks.store');
    });
