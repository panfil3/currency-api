<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Services\CurrencyConversionService;
use App\Application\Services\RateAggregationService;
use App\Infrastructure\Cache\IgniteCacheAdapter;
use App\Infrastructure\ExternalProviders\CurrencyLayerProvider;
use App\Infrastructure\ExternalProviders\ExchangeRateApiProvider;
use App\Infrastructure\ExternalProviders\FixerIoProvider;
use App\Infrastructure\RateLimiting\RedisRateLimiter;
use App\Infrastructure\Repositories\CurrencyRateRepository;
use App\Presentation\Http\Controllers\HealthCheckController;
use Illuminate\Support\ServiceProvider;

final class CurrencyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IgniteCacheAdapter::class);

        $this->app->singleton(CurrencyRateRepository::class);

        $this->app->singleton('currency.providers', function ($app) {
            return [
                new ExchangeRateApiProvider(
                    config('services.exchangerate_api.key')
                ),
                new CurrencyLayerProvider(
                    config('services.currencylayer.key')
                ),
                new FixerIoProvider(
                    config('services.fixer.key')
                ),
            ];
        });

        $this->app->singleton(CurrencyConversionService::class, function ($app) {
            return new CurrencyConversionService(
                $app->make(IgniteCacheAdapter::class),
                $app->make(CurrencyRateRepository::class),
                $app->make('currency.providers')
            );
        });

        $this->app->singleton(RateAggregationService::class, function ($app) {
            return new RateAggregationService(
                $app->make('currency.providers')
            );
        });

        $this->app->singleton(RedisRateLimiter::class);

        $this->app->bind(HealthCheckController::class, function ($app) {
            return new HealthCheckController(
                $app->make(IgniteCacheAdapter::class),
                $app->make('currency.providers')
            );
        });
    }
}