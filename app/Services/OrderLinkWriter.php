<?php

namespace App\Services;

use App\Data\Orders\PreparedOrderOperation;
use App\Support\CanonicalJson;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class OrderLinkWriter
{
    public function write(FinancialContext $context, int $receiptId, PreparedOrderOperation $prepared): void
    {
        $context->assertActive();
        $result = $prepared->result->data;
        $receipt = DB::table('operation_receipts')->where('id', $receiptId)->sole();
        $orderId = $result['order_id'];
        $order = DB::table('orders')->where('id', $orderId)->select('*')
            ->selectRaw('CAST(subtotal AS CHAR) as subtotal_text, CAST(discount AS CHAR) as discount_text, CAST(total AS CHAR) as total_text')->sole();
        $this->ensure((int) $receipt->order_id === $orderId && $order->number === $result['number'] && $order->status === $result['status'], 'Identidade do pedido divergente.');
        foreach (['subtotal', 'discount', 'total'] as $field) {
            $this->ensure(Money::decimal($order->{$field.'_text'})->decimalString() === $result[$field], 'Valores do pedido divergentes.');
        }
        if ($result['discount_approval'] !== null) {
            $this->ensure($result['discount_approval']['user_id'] === (int) $receipt->user_id, 'Autor da aprovação divergente.');
        }
        $items = DB::table('order_items')->where('order_id', $orderId)->orderBy('id')->select('*')
            ->selectRaw('CAST(price AS CHAR) as price_text, CAST(discount AS CHAR) as discount_text')->get()->keyBy('id');
        $this->ensure($items->isNotEmpty() && count($prepared->snapshots) === $items->count(), 'Fotografias incompletas.');
        $subtotal = Money::cents(0);
        $tracked = [];
        foreach ($prepared->snapshots as $itemId => $snapshot) {
            $item = $items->get($itemId);
            $this->ensure($item !== null, 'Fotografia de outro pedido.');
            $allowed = ['product_id','qty','catalog_price_cents','unit_price_cents','discount_cents','total_cents','price_source','price_reason','price_approved_by','stock_policy'];
            $this->ensure(! array_diff(array_keys($snapshot), $allowed) && ! array_diff($allowed, array_keys($snapshot)), 'Campos da fotografia inválidos.');
            foreach (['qty','unit_price_cents','discount_cents','total_cents','product_id'] as $field) {
                $this->ensure(is_int($snapshot[$field]), 'Fotografia exige inteiros exatos.');
            }
            $this->ensure($snapshot['product_id'] === (int) $item->product_id && $snapshot['qty'] === (int) $item->qty, 'Item da fotografia divergente.');
            $unit = Money::cents($snapshot['unit_price_cents']);
            $discount = Money::cents($snapshot['discount_cents']);
            $net = $unit->multiply($snapshot['qty'])->subtract($discount);
            $this->ensure($net->cents === $snapshot['total_cents']
                && $unit->cents === Money::decimal($item->price_text)->cents
                && $discount->cents === Money::decimal($item->discount_text)->cents, 'Cálculo da fotografia divergente.');
            if ($snapshot['catalog_price_cents'] !== null) {
                $this->ensure(is_int($snapshot['catalog_price_cents']), 'Preço de catálogo inválido.');
                Money::cents($snapshot['catalog_price_cents']);
            }
            $this->ensure(in_array($snapshot['stock_policy'], ['tracked', 'untracked'], true), 'Política de estoque inválida.');
            $this->ensure(in_array($snapshot['price_source'], ['catalog', 'quote', 'negotiated'], true), 'Origem de preço inválida.');
            if ($snapshot['price_source'] === 'negotiated') {
                $this->ensure($snapshot['price_approved_by'] === (int) $receipt->user_id
                    && is_string($snapshot['price_reason']) && trim($snapshot['price_reason']) !== ''
                    && mb_strlen($snapshot['price_reason']) <= 500, 'Aprovação de preço ausente.');
            } else {
                $this->ensure($snapshot['price_approved_by'] === null, 'Aprovador inesperado.');
            }
            if ($snapshot['price_source'] === 'catalog') {
                $this->ensure($snapshot['catalog_price_cents'] === $unit->cents && $discount->cents === 0, 'Catálogo sem aprovação não pode divergir.');
            }
            if ($snapshot['stock_policy'] === 'tracked') {
                $tracked[(int) $itemId] = $snapshot;
            }
            $subtotal = $subtotal->add($net);
            DB::table('order_item_snapshots')->insert($snapshot + [
                'operation_receipt_id' => $receiptId, 'order_item_id' => $itemId,
                'order_id' => $orderId, 'recorded_at_utc' => $context->utc(),
            ]);
        }
        $this->ensure($subtotal->decimalString() === $result['subtotal'], 'Subtotal dos itens divergente.');
        $payments = DB::table('order_payments')->where('order_id', $orderId)->orderBy('id')
            ->select('*')->selectRaw('CAST(amount AS CHAR) as amount_text')->get()->keyBy('id');
        $movements = DB::table('cash_movements')->where('order_id', $orderId)->orderBy('id')
            ->select('*')->selectRaw('CAST(amount AS CHAR) as amount_text')->get()->keyBy('id');

        if ($result['status'] === 'draft') {
            $this->ensure($receipt->financial_operation_id === null && $payments->isEmpty() && $movements->isEmpty()
                && ! $order->lowered_stock && ! $prepared->stockEffects, 'Orçamento produziu efeitos.');
            return;
        }

        $this->ensure((bool) $order->lowered_stock === (count($tracked) > 0) && $order->stock_reverted_at === null, 'Estado da baixa divergente.');
        $this->ensure($receipt->financial_operation_id !== null, 'Comprovante financeiro ausente.');
        $financial = DB::table('financial_operations')->where('id', $receipt->financial_operation_id)->sole();
        $this->ensure((int) $financial->cash_register_id === $result['cash_register_id'], 'Caixa do resultado divergente.');
        $settlement = DB::table('order_settlements')->insertGetId([
            'order_id' => $orderId, 'operation_receipt_id' => $receiptId,
            'financial_operation_id' => $receipt->financial_operation_id, 'cash_register_id' => $result['cash_register_id'],
            'total_cents' => Money::decimal($result['total'])->cents, 'recorded_at_utc' => $context->utc(),
        ]);
        $this->sameIds($payments->keys()->all(), array_column($result['payments'], 'id'));
        $this->sameIds($movements->keys()->all(), $result['cash_movement_ids']);
        $used = [];
        $sum = Money::cents(0);
        foreach ($result['payments'] as $part) {
            $payment = $payments->get($part['id']);
            $amount = Money::decimal($part['amount']);
            $this->ensure($payment !== null && $payment->method === $part['method']
                && Money::decimal($payment->amount_text)->cents === $amount->cents, 'Pagamento divergente.');
            $move = $movements->first(fn ($m) => ! isset($used[$m->id]) && $m->type === 'IN'
                && (int) $m->cash_register_id === $result['cash_register_id']
                && (int) $m->user_id === (int) $receipt->user_id
                && $m->payment_method === $part['method'] && Money::decimal($m->amount_text)->cents === $amount->cents);
            $this->ensure($move !== null, 'Pagamento sem entrada única correspondente.');
            $used[$move->id] = true;
            $sum = $sum->add($amount);
            DB::table('order_cash_effects')->insert([
                'cash_movement_id' => $move->id, 'settlement_id' => $settlement, 'order_id' => $orderId,
                'cash_register_id' => $result['cash_register_id'], 'order_payment_id' => $part['id'],
                'effect' => 'payment', 'effect_key' => 'payment:'.$part['id'],
            ]);
        }
        $this->ensure($sum->decimalString() === $result['total'], 'Soma dos recebimentos divergente.');
        $withdrawals = $movements->reject(fn ($m) => isset($used[$m->id]));
        $this->ensure($withdrawals->count() <= 1, 'Mais de uma sangria na operação.');
        foreach ($withdrawals as $move) {
            $this->ensure($move->type === 'OUT' && $move->payment_method === 'dinheiro'
                && (int) $move->cash_register_id === $result['cash_register_id']
                && (int) $move->user_id === (int) $receipt->user_id
                && Money::decimal($move->amount_text)->cents > 0, 'Efeito de saída inválido.');
            DB::table('order_cash_effects')->insert([
                'cash_movement_id' => $move->id, 'settlement_id' => $settlement, 'order_id' => $orderId,
                'cash_register_id' => $result['cash_register_id'], 'order_payment_id' => null,
                'effect' => 'automatic_withdrawal', 'effect_key' => 'automatic_withdrawal',
            ]);
        }
        $this->sameIds(array_keys($tracked), array_keys($prepared->stockEffects));
        $this->sameIds(array_values($prepared->stockEffects), $result['stock_movement_ids']);
        foreach ($prepared->stockEffects as $itemId => $movementId) {
            $move = DB::table('stock_movements')->where('id', $movementId)->select('*')
                ->selectRaw('CAST(unit_price AS CHAR) as price_text')->sole();
            $snapshot = $tracked[$itemId];
            $this->ensure($move->type === 'OUT' && (int) $move->product_id === $snapshot['product_id']
                && (int) $move->qty === $snapshot['qty'] && (int) $move->user_id === (int) $receipt->user_id
                && Money::decimal($move->price_text)->cents === $snapshot['unit_price_cents'], 'Baixa sem correspondência com o item.');
            DB::table('order_stock_effects')->insert([
                'stock_movement_id' => $movementId, 'operation_receipt_id' => $receiptId,
                'order_id' => $orderId, 'order_item_id' => $itemId, 'product_id' => $snapshot['product_id'], 'effect' => 'sale',
            ]);
        }
        $this->validateProjectedBalance($result['cash_register_id']);
    }

    private function validateProjectedBalance(int $id): void
    {
        $register = DB::table('cash_registers')->where('id', $id)->selectRaw('CAST(opening_amount AS CHAR) as amount_text')->sole();
        $balance = Money::decimal($register->amount_text)->cents;
        $rows = DB::table('cash_movements')->where('cash_register_id', $id)->select('type')
            ->selectRaw('CAST(amount AS CHAR) as amount_text')->lockForUpdate()->get();
        foreach ($rows as $row) {
            $amount = Money::decimal($row->amount_text)->cents;
            $delta = $row->type === 'IN' ? $amount : -$amount;
            if (($delta > 0 && $balance > PHP_INT_MAX - $delta) || ($delta < 0 && $balance < PHP_INT_MIN - $delta)) {
                throw new InvalidArgumentException('Saldo fora da capacidade de cálculo.');
            }
            $balance += $delta;
        }
        if ($balance < -Money::MAX_CENTS || $balance > Money::MAX_CENTS) {
            throw new ConflictHttpException('Saldo projetado excede a capacidade do fechamento.');
        }
    }

    private function sameIds(array $expected, array $actual): void
    {
        $this->ensure(count(array_unique($actual)) === count($actual), 'Referência duplicada.');
        sort($expected); sort($actual);
        $this->ensure($expected === $actual, 'Conjunto de referências divergente.');
    }

    private function ensure(bool $valid, string $message): void
    {
        if (! $valid) {
            throw new InvalidArgumentException($message);
        }
    }
}
