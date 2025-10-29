<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

use App\Application\Services\RateAggregationService;
use App\Domain\Currency\ValueObjects\CurrencyCode;
use App\Infrastructure\Repositories\CurrencyRateRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class SyncExchangeRatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    private const MAJOR_CURRENCIES = [
        'USD', 'EUR', 'GBP', 'JPY', 'CHF', 'CAD', 'AUD'
    ];

    public function __construct(
        private ?string $baseCurrency = null
    ) {
        $this->baseCurrency = $baseCurrency ?? 'USD';
    }

    public function handle(
        RateAggregationService $aggregationService,
        CurrencyRateRepository $repository
    ): void {
        Log::info("Starting exchange rate synchronization for base: {$this->baseCurrency}");

        $baseCurrency = CurrencyCode::from($this->baseCurrency);
        $successCount = 0;
        $failureCount = 0;

        foreach (self::MAJOR_CURRENCIES as $targetCode) {
            if ($targetCode === $this->baseCurrency) {
                continue;
            }

            try {
                $targetCurrency = CurrencyCode::from($targetCode);

                $aggregatedRate = $aggregationService->aggregateFromProviders(
                    $baseCurrency,
                    $targetCurrency
                );

                if ($aggregatedRate !== null) {
                    $repository->upsert($aggregatedRate);

                    $reverseRate = $aggregatedRate->reverse();
                    $repository->upsert($reverseRate);

                    $successCount++;
                    Log::debug("Synced rate: {$baseCurrency} -> {$targetCurrency} = {$aggregatedRate->value()}");
                } else {
                    $failureCount++;
                    Log::warning("Failed to aggregate rate for {$baseCurrency} -> {$targetCurrency}");
                }

            } catch (\Exception $e) {
                $failureCount++;
                Log::error("Error syncing rate {$baseCurrency} -> {$targetCode}: " . $e->getMessage());
            }
        }

        Log::info("Exchange rate sync completed. Success: {$successCount}, Failures: {$failureCount}");

        if ($failureCount > 0 && $successCount === 0) {
            throw new \RuntimeException("All rate synchronizations failed");
        }
    }

    public function backoff(): array
    {
        return [60, 120, 240];
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical("Exchange rate sync job failed permanently: " . $exception->getMessage());
    }
}