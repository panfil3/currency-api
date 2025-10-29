<?php

use Illuminate\Support\Facades\Route;
use App\Presentation\Http\Controllers\CurrencyConversionController;
use App\Presentation\Http\Controllers\HealthCheckController;
use App\Presentation\Http\Middleware\RateLimitMiddleware;

Route::prefix('v1')->group(function () {
    Route::get('/convert', [CurrencyConversionController::class, 'convert'])
        ->middleware(RateLimitMiddleware::class);
});

Route::get('/health', [HealthCheckController::class, 'check']);