<?php

declare(strict_types=1);

namespace App\Domain\Contracts;

interface RateLimiter
{
    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool;
    public function remaining(string $key, int $maxAttempts): int;
    public function reset(string $key): void;
}