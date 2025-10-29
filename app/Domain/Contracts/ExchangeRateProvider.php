<?php

declare(strict_types=1);

namespace App\Domain\Contracts;

use App\Domain\Currency\ValueObjects\CurrencyCode;
use App\Domain\Currency\ValueObjects\ExchangeRate;

interface ExchangeRateProvider
{
    public function getRate(CurrencyCode $from, CurrencyCode $to, int $timeout = 500): ?ExchangeRate;
}