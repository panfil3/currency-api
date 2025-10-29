<?php

declare(strict_types=1);

namespace App\Domain\Currency\ValueObjects;

use App\Domain\Currency\Exceptions\InvalidAmountException;

final class Money
{
    private string $amount;
    private CurrencyCode $currency;
    private const PRECISION = 8;

    private function __construct(string $amount, CurrencyCode $currency)
    {
        $this->validateAmount($amount);
        $this->amount = $this->normalize($amount);
        $this->currency = $currency;
    }

    public static function from(string|float|int $amount, CurrencyCode $currency): self
    {
        return new self((string) $amount, $currency);
    }

    private function validateAmount(string $amount): void
    {
        if (!is_numeric($amount)) {
            throw new InvalidAmountException("Amount must be numeric: {$amount}");
        }

        if (bccomp($amount, '0', self::PRECISION) <= 0) {
            throw new InvalidAmountException("Amount must be positive: {$amount}");
        }

        if (bccomp($amount, '999999999999.99999999', self::PRECISION) > 0) {
            throw new InvalidAmountException("Amount exceeds maximum allowed value");
        }
    }

    private function normalize(string $amount): string
    {
        $normalized = rtrim(rtrim($amount, '0'), '.');

        if (strpos($normalized, '.') === false) {
            return $normalized . '.00';
        }

        $parts = explode('.', $normalized);
        if (strlen($parts[1]) < 2) {
            return $parts[0] . '.' . str_pad($parts[1], 2, '0');
        }

        return $normalized;
    }

    public function convert(ExchangeRate $rate): self
    {
        $result = bcmul($this->amount, $rate->value(), self::PRECISION);
        return new self($result, $rate->targetCurrency());
    }

    public function amount(): string
    {
        return $this->amount;
    }

    public function currency(): CurrencyCode
    {
        return $this->currency;
    }

    public function format(int $decimals = 2): string
    {
        return number_format((float) $this->amount, $decimals, '.', '');
    }
}