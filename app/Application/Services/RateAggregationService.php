<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Currency\ValueObjects\CurrencyCode;
use App\Domain\Currency\ValueObjects\ExchangeRate;
use Illuminate\Support\Facades\Log;

final class RateAggregationService
{
    public function __construct(
        private array $providers
    ) {}

    public function aggregateFromProviders(
        CurrencyCode $from,
        CurrencyCode $to
    ): ?ExchangeRate {
        $rates = [];
        $latestTimestamp = null;

        foreach ($this->providers as $provider) {
            try {
                $rate = $provider->getRate($from, $to, timeout: 2000);

                    if ($rate !== null) {
                        $rates[] = (float) $rate->value();

                        if ($latestTimestamp === null ||
                            $rate->lastUpdated()->isAfter($latestTimestamp)) {
                            $latestTimestamp = $rate->lastUpdated();
                        }
                    }
                } catch (\Exception $e) {
                Log::warning("Provider error in aggregation: " . $e->getMessage());
            }
        }

        if (empty($rates)) {
            return null;
        }

        $medianRate = $this->calculateMedian($rates);

        return new ExchangeRate(
            $from,
            $to,
            (string) $medianRate,
            $latestTimestamp ?? now(),
            'local_db'
        );
    }

    private function calculateMedian(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = floor($count / 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }

        return $values[$middle];
    }
}