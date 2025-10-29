<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Currency\Entities\ConversionResult;
use App\Domain\Currency\ValueObjects\CurrencyCode;
use App\Domain\Currency\ValueObjects\ExchangeRate;
use App\Domain\Currency\ValueObjects\Money;
use App\Infrastructure\Cache\IgniteCacheAdapter;
use App\Infrastructure\Repositories\CurrencyRateRepository;
use Illuminate\Support\Facades\Log;
use App\Infrastructure\CircuitBreaker\CircuitBreaker;

final class CurrencyConversionService
{
    private const CACHE_TTL = 1;
    private const MAX_RATE_AGE_MINUTES = 60;

    public function __construct(
        private IgniteCacheAdapter $l1Cache,
        private CurrencyRateRepository $repository,
        private array $externalProviders,
    ) {}

    public function convert(Money $money, CurrencyCode $targetCurrency): ConversionResult
    {
        if ($money->currency()->equals($targetCurrency)) {
            $rate = new ExchangeRate(
                $money->currency(),
                $targetCurrency,
                '1.0',
                now(),
                'same_currency'
            );
            return new ConversionResult($money, $money, $rate);
        }

        $rate = $this->getExchangeRate($money->currency(), $targetCurrency);
        $convertedMoney = $money->convert($rate);

        return new ConversionResult($money, $convertedMoney, $rate);
    }

    private function getExchangeRate(CurrencyCode $from, CurrencyCode $to): ExchangeRate
    {
        // L1: In-Memory Cache
        $rate = $this->getFromL1Cache($from, $to);
        if ($rate !== null) {
            return $rate;
        }

        // L2: Local Database
        $rate = $this->getFromLocalDatabase($from, $to);
        if ($rate !== null && !$rate->isStale(self::MAX_RATE_AGE_MINUTES)) {
            $this->storeInL1Cache($from, $to, $rate);
            return $rate;
        }

        // L3: External Providers
        $rate = $this->getFromExternalProviders($from, $to);
        if ($rate !== null) {
            $this->storeInL1Cache($from, $to, $rate);
            $this->repository->upsert($rate);
            return $rate;
        }

        // Fallback to stale rate
        if ($rate === null && $this->repository->exists($from, $to)) {
            Log::warning("Using stale rate for {$from}->{$to}");
            return $this->repository->get($from, $to);
        }

        throw new \RuntimeException("Unable to fetch exchange rate for {$from} -> {$to}");
    }

    private function getFromL1Cache(CurrencyCode $from, CurrencyCode $to): ?ExchangeRate
    {
        $key = "rate:{$from}:{$to}";
        $cached = $this->l1Cache->get($key);

        if ($cached !== null) {
            Log::debug("Cache hit (L1) for {$from}->{$to}");
        }

        return $cached;
    }

    private function getFromLocalDatabase(CurrencyCode $from, CurrencyCode $to): ?ExchangeRate
    {
        try {
            $rate = $this->repository->get($from, $to);
            if ($rate !== null) {
                Log::debug("Database hit (L2) for {$from}->{$to}");
            }
            return $rate;
        } catch (\Exception $e) {
            Log::error("Database error: " . $e->getMessage());
            return null;
        }
    }

    private function getFromExternalProviders(CurrencyCode $from, CurrencyCode $to): ?ExchangeRate
    {
        foreach ($this->externalProviders as $provider) {
            $providerName = class_basename($provider);
            $circuitBreaker = new CircuitBreaker($providerName);

            if (!$circuitBreaker->isAvailable()) {
                Log::warning("Circuit breaker OPEN for {$providerName}, skipping");
                continue;
            }

            try {
                Log::info("Trying external provider: {$providerName}");

                $rate = $provider->getRate($from, $to, timeout: 500);

                if ($rate !== null) {
                    $circuitBreaker->recordSuccess();
                    Log::info("External provider SUCCESS: {$providerName}");
                    return $rate;
                }

                $circuitBreaker->recordFailure();
                Log::warning("Provider returned null: {$providerName}");

            } catch (\Exception $e) {
                $circuitBreaker->recordFailure();
                Log::warning("Provider FAILED: {$providerName} - " . $e->getMessage());
                continue;
            }
        }

        return null;
    }

    private function storeInL1Cache(CurrencyCode $from, CurrencyCode $to, ExchangeRate $rate): void
    {
        $key = "rate:{$from}:{$to}";
        $this->l1Cache->set($key, $rate, self::CACHE_TTL);
    }
}