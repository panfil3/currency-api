<?php

declare(strict_types=1);

namespace App\Infrastructure\ExternalProviders;

use App\Domain\Contracts\ExchangeRateProvider;
use App\Domain\Currency\ValueObjects\CurrencyCode;
use App\Domain\Currency\ValueObjects\ExchangeRate;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

final class FixerIoProvider implements ExchangeRateProvider
{
    private string $apiKey;
    private string $baseUrl = 'https://data.fixer.io/api/latest';

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function getRate(CurrencyCode $from, CurrencyCode $to, int $timeout = 500): ?ExchangeRate
    {
        try {
            $response = Http::timeout($timeout / 1000)
                ->get($this->baseUrl, [
                    'access_key' => $this->apiKey,
                    // Note: Free plan only supports EUR as base currency
                    // 'base' => (string) $from,
                    'base' => 'EUR',
                    'symbols' => (string) $to,
                ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();

            if (!$data['success']) {
                return null;
            }

            $rate = $data['rates'][(string) $to] ?? null;

            if ($rate === null) {
                return null;
            }

            return new ExchangeRate(
                $from,
                $to,
                (string) $rate,
                Carbon::createFromTimestamp($data['timestamp']),
                'external_fallback_3'
            );
        } catch (\Exception $e) {
            return null;
        }
    }
}