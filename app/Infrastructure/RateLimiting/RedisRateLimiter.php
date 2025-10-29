<?php

declare(strict_types=1);

namespace App\Infrastructure\RateLimiting;

use App\Domain\Contracts\RateLimiter as RateLimiterContract;
use Illuminate\Support\Facades\Redis;

final class RedisRateLimiter implements RateLimiterContract
{
    private const PREFIX = 'rate_limit:';

    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $fullKey = self::PREFIX . $key;
        $current = (int) Redis::get($fullKey);

        if ($current >= $maxAttempts) {
            return false;
        }

        Redis::pipeline(function ($pipe) use ($fullKey, $decaySeconds) {
            $pipe->incr($fullKey);
            $pipe->expire($fullKey, $decaySeconds);
        });

        return true;
    }

    public function remaining(string $key, int $maxAttempts): int
    {
        $fullKey = self::PREFIX . $key;
        $current = (int) Redis::get($fullKey);
        return max(0, $maxAttempts - $current);
    }

    public function reset(string $key): void
    {
        Redis::del(self::PREFIX . $key);
    }
}