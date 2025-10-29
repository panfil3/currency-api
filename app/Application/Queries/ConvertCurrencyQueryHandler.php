<?php

declare(strict_types=1);

namespace App\Application\Queries;

use App\Application\Services\CurrencyConversionService;
use App\Domain\Currency\ValueObjects\CurrencyCode;
use App\Domain\Currency\ValueObjects\Money;

final class ConvertCurrencyQueryHandler
{
    public function __construct(
        private CurrencyConversionService $conversionService
    ) {}

    public function handle(ConvertCurrencyQuery $query): array
    {
        $startTime = microtime(true);

        $fromCurrency = CurrencyCode::from($query->from);
        $toCurrency = CurrencyCode::from($query->to);
        $money = Money::from($query->amount, $fromCurrency);

        $result = $this->conversionService->convert($money, $toCurrency);

        $executionTime = (microtime(true) - $startTime) * 1000;

        return [
            'data' => [
                'from' => $result->fromCurrency()->value(),
                'to' => $result->toCurrency()->value(),
                'amount' => $result->originalAmount()->format(),
                'result' => $result->convertedAmount()->format(),
                'rate' => $result->rate()->value(),
                'last_updated' => $result->rate()->lastUpdated()->toIso8601String(),
            ],
            'meta' => [
                'source' => $result->rate()->source(),
                'execution_time_ms' => round($executionTime, 2),
            ]
        ];
    }
}