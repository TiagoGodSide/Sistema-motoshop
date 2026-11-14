<?php

namespace App\Data\Orders;

use App\Support\CanonicalJson;
use App\Support\Money;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class OrderInput
{
    public static function fields(array $data, array $allowed): void
    {
        if (array_diff(array_keys($data), $allowed)) {
            throw new InvalidArgumentException('Campo não permitido no contrato.');
        }
    }

    public static function envelope(array $input, array $allowed): string
    {
        self::fields($input, array_merge(['contract_version', 'idempotency_key'], $allowed));
        if (($input['contract_version'] ?? null) !== 1 || ! is_string($input['idempotency_key'] ?? null)
            || ! Str::isUuid($input['idempotency_key'])) {
            throw new InvalidArgumentException('Versão ou UUID inválido.');
        }
        return strtolower($input['idempotency_key']);
    }

    public static function id(mixed $value): int
    {
        if (! is_int($value) || $value < 1) {
            throw new InvalidArgumentException('Identificador inteiro positivo obrigatório.');
        }
        return $value;
    }

    public static function text(mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value) || mb_strlen($value) > $max) {
            throw new InvalidArgumentException('Texto inválido ou muito longo.');
        }
        return trim($value) === '' ? null : trim($value);
    }

    public static function items(mixed $items): array
    {
        if (! is_array($items) || ! array_is_list($items) || count($items) < 1 || count($items) > 200) {
            throw new InvalidArgumentException('Informe de 1 a 200 itens.');
        }
        $normalized = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException('Item inválido.');
            }
            self::fields($item, ['kind', 'product_id', 'qty', 'expected_unit_price', 'discount', 'requested_unit_price', 'price_reason']);
            if (($item['kind'] ?? null) !== 'product') {
                throw new InvalidArgumentException('Persistência de serviços será integrada na fase 4.3.5.');
            }
            $qty = self::id($item['qty'] ?? null);
            if ($qty > Money::MAX_QTY) {
                throw new InvalidArgumentException('Quantidade fora do limite.');
            }
            $expected = Money::decimal($item['expected_unit_price'] ?? null);
            $requested = isset($item['requested_unit_price']) ? Money::decimal($item['requested_unit_price']) : null;
            $discount = Money::decimal($item['discount'] ?? '0.00');
            ($requested ?? $expected)->multiply($qty)->subtract($discount);
            $normalized[] = [
                'kind' => 'product', 'product_id' => self::id($item['product_id'] ?? null), 'qty' => $qty,
                'expected_unit_price' => $expected->decimalString(), 'discount' => $discount->decimalString(),
                'requested_unit_price' => $requested?->decimalString(),
                'price_reason' => self::text($item['price_reason'] ?? null, 500),
            ];
        }
        usort($normalized, fn ($a, $b) => strcmp(CanonicalJson::encode($a), CanonicalJson::encode($b)));
        return $normalized;
    }

    public static function payments(mixed $payments): array
    {
        if (! is_array($payments) || ! array_is_list($payments) || count($payments) < 1 || count($payments) > 20) {
            throw new InvalidArgumentException('Informe de 1 a 20 pagamentos confirmados.');
        }
        $normalized = [];
        $sum = Money::cents(0);
        foreach ($payments as $payment) {
            if (! is_array($payment)) {
                throw new InvalidArgumentException('Pagamento inválido.');
            }
            self::fields($payment, ['method', 'amount']);
            if (! in_array($payment['method'] ?? null, ['dinheiro', 'pix', 'cartao', 'outro'], true)) {
                throw new InvalidArgumentException('Forma de pagamento inválida.');
            }
            $money = Money::decimal($payment['amount'] ?? null);
            if ($money->cents === 0) {
                throw new InvalidArgumentException('Parcela deve ser positiva.');
            }
            $sum = $sum->add($money);
            $normalized[] = ['method' => $payment['method'], 'amount' => $money->decimalString()];
        }
        usort($normalized, fn ($a, $b) => strcmp(CanonicalJson::encode($a), CanonicalJson::encode($b)));
        return $normalized;
    }

    public static function cart(array $input): array
    {
        return [
            'customer_id' => isset($input['customer_id']) ? self::id($input['customer_id']) : null,
            'customer_name' => self::text($input['customer_name'] ?? null, 120),
            'items' => self::items($input['items'] ?? null),
            'discount' => Money::decimal($input['discount'] ?? '0.00')->decimalString(),
            'discount_reason' => self::text($input['discount_reason'] ?? null, 500),
        ];
    }
}
