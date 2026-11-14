<?php

namespace App\Support;

use InvalidArgumentException;

final readonly class Money
{
    public const MAX_CENTS = 9_999_999_999;
    public const MAX_QTY = 2_147_483_647;

    private function __construct(public int $cents) {}

    public static function cents(mixed $value): self
    {
        if (PHP_INT_SIZE !== 8 || ! is_int($value) || $value < 0 || $value > self::MAX_CENTS) {
            throw new InvalidArgumentException('Valor monetário fora do limite.');
        }
        return new self($value);
    }

    public static function decimal(mixed $value): self
    {
        if (! is_string($value) || strlen($value) > 64 || ! preg_match('/\A[0-9]+(?:\.[0-9]{1,2})?\z/', $value)) {
            throw new InvalidArgumentException('Use uma string decimal com até duas casas.');
        }
        [$whole, $fraction] = array_pad(explode('.', $value), 2, '');
        $whole = ltrim($whole, '0') ?: '0';
        if (strlen($whole) > 8) {
            throw new InvalidArgumentException('Valor monetário fora do limite.');
        }
        return self::cents((int) $whole * 100 + (int) str_pad($fraction, 2, '0'));
    }

    public function add(self $other): self
    {
        if ($this->cents > self::MAX_CENTS - $other->cents) {
            throw new InvalidArgumentException('Soma monetária fora do limite.');
        }
        return self::cents($this->cents + $other->cents);
    }

    public function subtract(self $discount): self
    {
        return self::cents($this->cents - $discount->cents);
    }

    public function multiply(mixed $quantity): self
    {
        if (! is_int($quantity) || $quantity < 1 || $quantity > self::MAX_QTY
            || ($this->cents > 0 && $quantity > intdiv(self::MAX_CENTS, $this->cents))) {
            throw new InvalidArgumentException('Quantidade ou multiplicação fora do limite.');
        }
        return self::cents($this->cents * $quantity);
    }

    public function decimalString(): string
    {
        return intdiv($this->cents, 100).'.'.str_pad((string) ($this->cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
