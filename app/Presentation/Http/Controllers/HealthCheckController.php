<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

use App\Infrastructure\Cache\IgniteCacheAdapter;
use App\Domain\Currency\ValueObjects\CurrencyCode;
use App\Domain\Currency\ValueObjects\ExchangeRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class HealthCheckController
{
    public function __construct(
        private IgniteCacheAdapter $cache,
        private array $externalProviders
    ) {}

    public function check(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'external_providers' => $this->checkExternalProviders(),
        ];

        $isHealthy = !in_array(false, array_column($checks, 'status'), true);

        return response()->json([
            'status' => $isHealthy ? 'healthy' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks
        ], $isHealthy ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return ['status' => true, 'message' => 'Database is accessible'];
        } catch (\Exception $e) {
            return ['status' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        try {
            $testKey = 'health_check_' . time();
            $testRate = new ExchangeRate(
                CurrencyCode::from('USD'),
                CurrencyCode::from('EUR'),
                '1.0',
                now(),
                'test'
            );
            $this->cache->set($testKey, $testRate, 5);
            $value = $this->cache->get($testKey);

            return $value !== null
                ? ['status' => true, 'message' => 'Cache is working']
                : ['status' => false, 'message' => 'Cache read/write failed'];

        } catch (\Exception $e) {
            return ['status' => false, 'message' => 'Cache error: ' . $e->getMessage()];
        }
    }

    private function checkExternalProviders(): array
    {
        $results = [];

        foreach ($this->externalProviders as $provider) {
            $providerName = class_basename($provider);
            try {
                $rate = $provider->getRate(
                    CurrencyCode::from('USD'),
                    CurrencyCode::from('EUR'),
                    timeout: 1000
                    );

                    $results[$providerName] = $rate !== null
                        ? ['status' => true, 'message' => 'Available']
                        : ['status' => false, 'message' => 'No response'];

                } catch (\Exception $e) {
                $results[$providerName] = [
                    'status' => false,
                    'message' => 'Error: ' . $e->getMessage()
                ];
            }
        }

        return $results;
    }
}