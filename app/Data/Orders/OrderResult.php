<?php

namespace App\Data\Orders;

use App\Support\Money;
use App\Support\CanonicalJson;
use InvalidArgumentException;

final readonly class OrderResult
{
    private function __construct(public array $data) {}

    public static function fromArray(array $data): self
    {
        OrderInput::fields($data, ['contract_version', 'operation_key', 'order_id', 'number', 'status', 'cash_register_id',
            'subtotal', 'discount', 'total', 'discount_approval', 'payments', 'cash_movement_ids', 'stock_movement_ids']);
        OrderInput::envelope(['contract_version' => $data['contract_version'] ?? null, 'idempotency_key' => $data['operation_key'] ?? null], []);
        OrderInput::id($data['order_id'] ?? null);
        if (! is_string($data['number'] ?? null) || ! preg_match('/\A[0-9]{4}-[0-9]{6}\z/', $data['number'])) {
            throw new InvalidArgumentException('Número de pedido inválido.');
        }
        $total = Money::decimal($data['total'] ?? null);
        $subtotal = Money::decimal($data['subtotal'] ?? null);
        $discount = Money::decimal($data['discount'] ?? null);
        if ($subtotal->subtract($discount)->cents !== $total->cents) {
            throw new InvalidArgumentException('Totais do resultado divergentes.');
        }
        if ($discount->cents > 0) {
            $approval = $data['discount_approval'] ?? null;
            if (! is_array($approval)) {
                throw new InvalidArgumentException('Aprovação do desconto ausente.');
            }
            OrderInput::fields($approval, ['user_id', 'reason']);
            OrderInput::id($approval['user_id'] ?? null);
            if (OrderInput::text($approval['reason'] ?? null, 500) === null) {
                throw new InvalidArgumentException('Justificativa ausente.');
            }
        } elseif (($data['discount_approval'] ?? null) !== null) {
            throw new InvalidArgumentException('Aprovação de desconto sem desconto.');
        }
        foreach (['payments', 'cash_movement_ids', 'stock_movement_ids'] as $field) {
            if (! isset($data[$field]) || ! is_array($data[$field]) || ! array_is_list($data[$field])) {
                throw new InvalidArgumentException('Coleção de resultado inválida.');
            }
        }
        foreach (array_merge($data['cash_movement_ids'], $data['stock_movement_ids']) as $id) {
            OrderInput::id($id);
        }
        foreach (['cash_movement_ids', 'stock_movement_ids'] as $field) {
            if (count(array_unique($data[$field])) !== count($data[$field])) {
                throw new InvalidArgumentException('Referência de movimento duplicada.');
            }
        }
        if (($data['status'] ?? null) === 'draft') {
            if (($data['cash_register_id'] ?? null) !== null || $data['payments'] || $data['cash_movement_ids'] || $data['stock_movement_ids']) {
                throw new InvalidArgumentException('Orçamento não pode produzir efeitos.');
            }
        } elseif (($data['status'] ?? null) === 'paid') {
            OrderInput::id($data['cash_register_id'] ?? null);
            $parts = [];
            $paymentIds = [];
            foreach ($data['payments'] as $index => $part) {
                if (! is_array($part)) {
                    throw new InvalidArgumentException('Parcela de resultado inválida.');
                }
                OrderInput::fields($part, ['id', 'method', 'amount']);
                $id = OrderInput::id($part['id'] ?? null);
                if (isset($paymentIds[$id])) {
                    throw new InvalidArgumentException('Referência de pagamento duplicada.');
                }
                $paymentIds[$id] = true;
                $data['payments'][$index]['amount'] = Money::decimal($part['amount'] ?? null)->decimalString();
                $parts[] = ['method' => $part['method'] ?? null, 'amount' => $part['amount'] ?? null];
            }
            $parts = OrderInput::payments($parts);
            (new \App\Services\OrderAmountCalculator())->validatePayments($total, $parts);
            if (count($data['cash_movement_ids']) < count($parts)) {
                throw new InvalidArgumentException('Recebimentos sem movimentos correspondentes.');
            }
        } else {
            throw new InvalidArgumentException('Estado de resultado inválido.');
        }
        $data['total'] = $total->decimalString();
        $data['subtotal'] = $subtotal->decimalString();
        $data['discount'] = $discount->decimalString();
        $data['discount_approval'] ??= null;
        return new self(json_decode(CanonicalJson::encode($data), true, 512, JSON_THROW_ON_ERROR));
    }
}
