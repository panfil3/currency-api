<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Domain\Currency\ValueObjects\ExchangeRate;
use App\Domain\Currency\ValueObjects\CurrencyCode;
use Illuminate\Support\Facades\Redis;

final class IgniteCacheAdapter
{
    private const SERIALIZATION_PREFIX = 'serialized:';

    public function get(string $key): ?ExchangeRate
    {
        try {
            $value = Redis::connection('cache')->get($key);

            if ($value === null) {
                return null;
            }

            return $this->deserialize($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function set(string $key, ExchangeRate $value, int $ttlSeconds): bool
    {
        try {
            $serialized = $this->serialize($value);
            return Redis::connection('cache')->setex($key, $ttlSeconds, $serialized);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function delete(string $key): bool
    {
        try {
            return (bool) Redis::connection('cache')->del($key);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function has(string $key): bool
    {
        try {
            return (bool) Redis::connection('cache')->exists($key);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function serialize(ExchangeRate $rate): string
    {
        $data = [
            'from' => $rate->sourceCurrency()->value(),
            'to' => $rate->targetCurrency()->value(),
            'rate' => $rate->value(),
            'last_updated' => $rate->lastUpdated()->timestamp,
            'source' => $rate->source(),
        ];

        return self::SERIALIZATION_PREFIX . json_encode($data);
    }

    private function deserialize(string $value): ?ExchangeRate
    {
        if (!str_starts_with($value, self::SERIALIZATION_PREFIX)) {
            return null;
        }

        $json = substr($value, strlen(self::SERIALIZATION_PREFIX));
        $data = json_decode($json, true);

        if ($data === null) {
            return null;
        }

        return new ExchangeRate(
            CurrencyCode::from($data['from']),
            CurrencyCode::from($data['to']),
            $data['rate'],
            \Carbon\Carbon::createFromTimestamp($data['last_updated']),
            $data['source']
        );
    }
}