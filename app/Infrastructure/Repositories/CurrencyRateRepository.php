<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Currency\ValueObjects\CurrencyCode;
use App\Domain\Currency\ValueObjects\ExchangeRate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class CurrencyRateRepository
{
    public function get(CurrencyCode $from, CurrencyCode $to): ?ExchangeRate
    {
        $record = DB::table('currency_rates')
            ->where('from_currency', $from->value())
            ->where('to_currency', $to->value())
            ->first();

        if ($record === null) {
            return null;
        }

        return new ExchangeRate(
            $from,
            $to,
            $record->rate,
            Carbon::parse($record->last_updated),
            $record->source
        );
    }

    public function exists(CurrencyCode $from, CurrencyCode $to): bool
    {
        return DB::table('currency_rates')
            ->where('from_currency', $from->value())
            ->where('to_currency', $to->value())
            ->exists();
    }

    public function upsert(ExchangeRate $rate): void
    {
        DB::table('currency_rates')->updateOrInsert(
            [
                'from_currency' => $rate->sourceCurrency()->value(),
                'to_currency' => $rate->targetCurrency()->value(),
            ],
            [
                'rate' => $rate->value(),
                'source' => $rate->source(),
                'last_updated' => $rate->lastUpdated(),
                'updated_at' => now(),
            ]
        );
    }

    public function getAllRates(): array
    {
        $records = DB::table('currency_rates')->get();

        return $records->map(function ($record) {
            return new ExchangeRate(
                CurrencyCode::from($record->from_currency),
                CurrencyCode::from($record->to_currency),
                $record->rate,
                Carbon::parse($record->last_updated),
                $record->source
            );
        })->toArray();
    }

    public function deleteStaleRates(int $maxAgeHours = 24): int
    {
        return DB::table('currency_rates')
            ->where('last_updated', '<', now()->subHours($maxAgeHours))
            ->delete();
    }
}