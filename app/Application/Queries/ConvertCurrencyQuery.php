<?php

declare(strict_types=1);

namespace App\Application\Queries;

final class ConvertCurrencyQuery
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly string $amount
    ) {}
}