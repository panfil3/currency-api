<?php

declare(strict_types=1);

namespace App\Infrastructure\CircuitBreaker;

use Illuminate\Support\Facades\Redis;

class CircuitBreaker
{
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    private const FAILURE_THRESHOLD = 5;
    private const TIMEOUT_SECONDS = 60;
    private const SUCCESS_THRESHOLD = 2;

    private string $serviceName;
    private string $keyPrefix = 'circuit_breaker:';

    public function __construct(string $serviceName)
    {
        $this->serviceName = $serviceName;
    }

    public function isAvailable(): bool
    {
        $state = $this->getState();

        if ($state === self::STATE_CLOSED) {
            return true;
        }

        if ($state === self::STATE_OPEN) {
            if ($this->shouldAttemptReset()) {
                $this->setState(self::STATE_HALF_OPEN);
                return true;
            }
            return false;
        }

        return true;
    }

    public function recordSuccess(): void
    {
        $state = $this->getState();

        if ($state === self::STATE_HALF_OPEN) {
            $successes = $this->incrementSuccessCount();

            if ($successes >= self::SUCCESS_THRESHOLD) {
                $this->setState(self::STATE_CLOSED);
                $this->resetCounters();
            }
        } elseif ($state === self::STATE_CLOSED) {
            $this->resetFailureCount();
        }
    }

    public function recordFailure(): void
    {
        $state = $this->getState();

        if ($state === self::STATE_HALF_OPEN) {
            $this->setState(self::STATE_OPEN);
            $this->setOpenedAt();
        } elseif ($state === self::STATE_CLOSED) {
            $failures = $this->incrementFailureCount();

            if ($failures >= self::FAILURE_THRESHOLD) {
                $this->setState(self::STATE_OPEN);
                $this->setOpenedAt();
            }
        }
    }

    public function getState(): string
    {
        $state = Redis::get($this->keyPrefix . $this->serviceName . ':state');
        return $state ?: self::STATE_CLOSED;
    }

    private function setState(string $state): void
    {
        Redis::set($this->keyPrefix . $this->serviceName . ':state', $state);
    }

    private function incrementFailureCount(): int
    {
        $key = $this->keyPrefix . $this->serviceName . ':failures';
        Redis::incr($key);
        Redis::expire($key, self::TIMEOUT_SECONDS);
        return (int) Redis::get($key);
    }

    private function resetFailureCount(): void
    {
        Redis::del($this->keyPrefix . $this->serviceName . ':failures');
    }

    private function incrementSuccessCount(): int
    {
        $key = $this->keyPrefix . $this->serviceName . ':successes';
        Redis::incr($key);
        Redis::expire($key, 60);
        return (int) Redis::get($key);
    }

    private function resetCounters(): void
    {
        Redis::del($this->keyPrefix . $this->serviceName . ':failures');
        Redis::del($this->keyPrefix . $this->serviceName . ':successes');
    }

    private function setOpenedAt(): void
    {
        Redis::set(
            $this->keyPrefix . $this->serviceName . ':opened_at',
            time()
        );
    }

    private function shouldAttemptReset(): bool
    {
        $openedAt = Redis::get($this->keyPrefix . $this->serviceName . ':opened_at');

        if (!$openedAt) {
            return true;
        }

        return (time() - (int) $openedAt) >= self::TIMEOUT_SECONDS;
    }
}