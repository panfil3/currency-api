<?php

declare(strict_types=1);

namespace App\Infrastructure\ExternalProviders;

use App\Domain\Contracts\ExchangeRateProvider;
use App\Domain\Currency\ValueObjects\CurrencyCode;
use App\Domain\Currency\ValueObjects\ExchangeRate;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

final class CurrencyLayerProvider implements ExchangeRateProvider
{
    private string $apiKey;
    private string $baseUrl = 'https://api.currencylayer.com/live';

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
                    'source' => (string) $from,
                    'currencies' => (string) $to,
                ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();

            if (!$data['success']) {
                return null;
            }

            $key = $from . $to;
            $rate = $data['quotes'][$key] ?? null;

            if ($rate === null) {
                return null;
            }

            return new ExchangeRate(
                $from,
                $to,
                (string) $rate,
                Carbon::createFromTimestamp($data['timestamp']),
                'external_fallback_2'
            );
        } catch (\Exception $e) {
            return null;
        }
    }
}