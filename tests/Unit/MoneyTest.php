<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public static function decimals(): array
    {
        return [['0','0.00',0],['0.01','0.01',1],['001.2','1.20',120],['99999999.99','99999999.99',9999999999]];
    }

    #[DataProvider('decimals')]
    public function test_exact_decimal_round_trip(string $input, string $expected, int $cents): void
    {
        $m = Money::decimal($input);
        $this->assertSame($expected, $m->decimalString());
        $this->assertSame($cents, $m->cents);
    }

    public static function invalid(): array
    {
        return array_map(fn ($v) => [$v], [1.2,1,null,true,'','-1','1,00','1e2','NaN','INF','1.001','100000000.00','+1',' 1.00']);
    }

    #[DataProvider('invalid')]
    public function test_rejects_invalid_inputs_without_float_conversion(mixed $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::decimal($input);
    }

    public function test_exact_arithmetic(): void
    {
        $this->assertSame('0.30', Money::decimal('0.10')->add(Money::decimal('0.20'))->decimalString());
        $this->assertSame('99.80', Money::decimal('49.90')->multiply(2)->decimalString());
        $this->assertSame('99.79', Money::decimal('49.90')->multiply(2)->subtract(Money::decimal('0.01'))->decimalString());
        $this->assertSame('0.00', Money::cents(0)->multiply(Money::MAX_QTY)->decimalString());
    }

    public static function coercions(): array { return [['cents', 1.5], ['cents', '1'], ['multiply', 1.5], ['multiply', '2']]; }

    #[DataProvider('coercions')]
    public function test_integer_api_rejects_php_weak_coercion(string $method, mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        if ($method === 'cents') Money::cents($value);
        else Money::cents(1)->multiply($value);
    }

    public static function overflow(): array { return [['add'],['multiply'],['subtract'],['quantity']]; }

    #[DataProvider('overflow')]
    public function test_checks_limits_before_arithmetic(string $operation): void
    {
        $this->expectException(InvalidArgumentException::class);
        match ($operation) {
            'add' => Money::cents(Money::MAX_CENTS)->add(Money::cents(1)),
            'multiply' => Money::cents(Money::MAX_CENTS)->multiply(Money::MAX_QTY),
            'subtract' => Money::cents(0)->subtract(Money::cents(1)),
            'quantity' => Money::cents(0)->multiply(Money::MAX_QTY + 1),
        };
    }
}
