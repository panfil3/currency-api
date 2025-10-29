<?php

declare(strict_types=1);

namespace App\Domain\Currency\ValueObjects;

use Carbon\Carbon;

final class ExchangeRate
{
    private CurrencyCode $from;
    private CurrencyCode $to;
    private string $rate;
    private Carbon $lastUpdated;
    private string $source;

    public function __construct(
        CurrencyCode $from,
        CurrencyCode $to,
        string $rate,
        Carbon $lastUpdated,
        string $source = 'unknown'
    ) {
        if (bccomp($rate, '0', 8) <= 0) {
            throw new \InvalidArgumentException("Exchange rate must be positive");
        }

        $this->from = $from;
        $this->to = $to;
        $this->rate = $rate;
        $this->lastUpdated = $lastUpdated;
        $this->source = $source;
    }

    public function sourceCurrency(): CurrencyCode
    {
        return $this->from;
    }

    public function targetCurrency(): CurrencyCode
    {
        return $this->to;
    }

    public function value(): string
    {
        return $this->rate;
    }

    public function lastUpdated(): Carbon
    {
        return $this->lastUpdated;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function isStale(int $maxAgeMinutes = 60): bool
    {
        return $this->lastUpdated->diffInMinutes(now()) > $maxAgeMinutes;
    }

    public function reverse(): self
    {
        $reversedRate = bcdiv('1', $this->rate, 8);
        return new self($this->to, $this->from, $reversedRate, $this->lastUpdated, $this->source);
    }
}