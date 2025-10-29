<?php

declare(strict_types=1);

namespace App\Domain\Currency\ValueObjects;

use App\Domain\Currency\Exceptions\UnsupportedCurrencyException;

final class CurrencyCode
{
    private const SUPPORTED_CURRENCIES = [
        'USD', 'EUR', 'GBP', 'JPY', 'CHF', 'CAD', 'AUD', 'NZD',
        'CNY', 'INR', 'BRL', 'MXN', 'ZAR', 'RUB', 'KRW', 'SGD',
        'HKD', 'NOK', 'SEK', 'DKK', 'PLN', 'THB', 'MYR', 'IDR'
    ];

    private string $code;

    private function __construct(string $code)
    {
        $normalizedCode = strtoupper(trim($code));

        if (!$this->isValid($normalizedCode)) {
            throw new UnsupportedCurrencyException(
                "Currency code '{$code}' is not supported. Must be ISO 4217."
            );
        }

        $this->code = $normalizedCode;
    }

    public static function from(string $code): self
    {
        return new self($code);
    }

    private function isValid(string $code): bool
    {
        return strlen($code) === 3
            && ctype_alpha($code)
            && in_array($code, self::SUPPORTED_CURRENCIES, true);
    }

    public function value(): string
    {
        return $this->code;
    }

    public function equals(CurrencyCode $other): bool
    {
        return $this->code === $other->code;
    }

    public function __toString(): string
    {
        return $this->code;
    }
}