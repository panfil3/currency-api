<?php

declare(strict_types=1);

namespace App\Domain\Currency\Entities;

use App\Domain\Currency\ValueObjects\CurrencyCode;
use App\Domain\Currency\ValueObjects\ExchangeRate;
use App\Domain\Currency\ValueObjects\Money;

final class ConversionResult
{
    public function __construct(
        private Money $originalAmount,
        private Money $convertedAmount,
        private ExchangeRate $rate
    ) {
        if (bccomp($originalAmount->amount(), '0', 8) <= 0 ||
            bccomp($convertedAmount->amount(), '0', 8) <= 0) {
            throw new \InvalidArgumentException('Amounts must be positive');
        }

        if (!$originalAmount->currency()->equals($rate->sourceCurrency()) ||
            !$convertedAmount->currency()->equals($rate->targetCurrency())) {
            throw new \InvalidArgumentException('Currency mismatch in conversion result');
        }
    }

    public function originalAmount(): Money
    {
        return $this->originalAmount;
    }

    public function convertedAmount(): Money
    {
        return $this->convertedAmount;
    }

    public function rate(): ExchangeRate
    {
        return $this->rate;
    }

    public function fromCurrency(): CurrencyCode
    {
        return $this->originalAmount->currency();
    }

    public function toCurrency(): CurrencyCode
    {
        return $this->convertedAmount->currency();
    }
}