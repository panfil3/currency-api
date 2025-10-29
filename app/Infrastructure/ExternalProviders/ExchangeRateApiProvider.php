<?php

declare(strict_types=1);

namespace App\Infrastructure\ExternalProviders;

use App\Domain\Contracts\ExchangeRateProvider;
use App\Domain\Currency\ValueObjects\CurrencyCode;
use App\Domain\Currency\ValueObjects\ExchangeRate;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

final class ExchangeRateApiProvider implements ExchangeRateProvider
{
    private string $apiKey;
    private string $baseUrl = 'https://v6.exchangerate-api.com/v6/';

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function getRate(CurrencyCode $from, CurrencyCode $to, int $timeout = 500): ?ExchangeRate
    {
        try {
            $response = Http::timeout($timeout / 1000)
                ->get("{$this->baseUrl}{$this->apiKey}/pair/{$from}/{$to}");

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();

            if ($data['result'] !== 'success') {
                return null;
            }

            return new ExchangeRate(
                $from,
                $to,
                (string) $data['conversion_rate'],
                Carbon::createFromTimestamp($data['time_last_update_unix']),
                'external_fallback_1'
            );
        } catch (\Exception $e) {
            return null;
        }
    }
}