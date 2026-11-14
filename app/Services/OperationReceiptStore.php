<?php

namespace App\Services;

use App\Models\User;
use App\Data\Orders\OrderResult;
use App\Support\CanonicalJson;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Throwable;

final class OperationReceiptStore
{
    public function recover(FinancialContext $context, User $actor, string $key, string $operation, string $hash): ?array
    {
        $context->assertActive();
        $this->envelope($key, $operation, $hash);
        $receipt = DB::table('operation_receipts')->where('idempotency_key', $key)->lockForUpdate()->first();
        $financial = DB::table('financial_operations')->where('idempotency_key', $key)->lockForUpdate()->first();
        if ($receipt !== null) {
            $this->identity($receipt, $actor, $operation, $hash);
            if ((int) $receipt->contract_version !== 1) {
                $this->unavailable();
            }
            if ($receipt->financial_operation_id !== null) {
                if ($financial === null || (int) $financial->id !== (int) $receipt->financial_operation_id || $receipt->result !== null) {
                    $this->unavailable();
                }
                $this->identity($financial, $actor, $operation, $hash);
                $result = $this->financialResult($financial);
            } else {
                if ($financial !== null || $operation !== 'order.quote.create' || $receipt->order_id === null) {
                    $this->unavailable();
                }
                $result = json_decode(CanonicalJson::encode($this->decode($receipt->result)), true, 512, JSON_THROW_ON_ERROR);
            }
            try {
                if (str_starts_with($operation, 'cash.')) {
                    if ($receipt->order_id !== null) {
                        $this->unavailable();
                    }
                } else {
                    $validated = OrderResult::fromArray($result)->data;
                    if ($validated['order_id'] !== (int) $receipt->order_id || $validated['operation_key'] !== $key
                        || $validated['status'] !== ($operation === 'order.quote.create' ? 'draft' : 'paid')
                        || CanonicalJson::hash($validated) !== CanonicalJson::hash($result)) {
                        $this->unavailable();
                    }
                    $this->orderFinancialIdentity($operation, $financial, $result);
                    if ($result['discount_approval'] !== null && $result['discount_approval']['user_id'] !== (int) $receipt->user_id) {
                        $this->unavailable();
                    }
                    $this->orderLinks($receipt, $result);
                }
            } catch (Throwable $exception) {
                throw new ServiceUnavailableHttpException(1, 'Resultado financeiro inconsistente. Preserve a chave.', $exception);
            }
            if (! hash_equals($receipt->result_hash, CanonicalJson::hash($result))) {
                $this->unavailable();
            }
            return $result;
        }
        if ($financial !== null) {
            $this->identity($financial, $actor, $operation, $hash);
            // Historical recovery never backfills the new registry.
            if (! in_array($operation, ['cash.open', 'cash.close', 'cash.movement'], true)) {
                $this->unavailable();
            }
            return $this->financialResult($financial);
        }
        return null;
    }

    public function append(
        FinancialContext $context, User $actor, string $key, string $operation, string $hash,
        ?int $orderId, ?int $financialId, array $result,
    ): int {
        $context->assertActive();
        $this->envelope($key, $operation, $hash);
        $financial = DB::table('financial_operations')->where('idempotency_key', $key)->lockForUpdate()->first();
        if ($financialId === null) {
            if ($financial !== null) {
                throw new ConflictHttpException('Chave financeira histórica já utilizada.');
            }
            if ($operation !== 'order.quote.create' || $orderId === null) {
                $this->unavailable();
            }
        } else {
            if ($financial === null || (int) $financial->id !== $financialId) {
                $this->unavailable();
            }
            $this->identity($financial, $actor, $operation, $hash);
            if (CanonicalJson::hash($this->financialResult($financial)) !== CanonicalJson::hash($result)) {
                $this->unavailable();
            }
        }
        if (str_starts_with($operation, 'cash.')) {
            if ($orderId !== null) {
                $this->unavailable();
            }
        } else {
            $validated = OrderResult::fromArray($result)->data;
            if ($validated['order_id'] !== $orderId || $validated['operation_key'] !== $key
                || $validated['status'] !== ($operation === 'order.quote.create' ? 'draft' : 'paid')
                || CanonicalJson::hash($validated) !== CanonicalJson::hash($result)) {
                $this->unavailable();
            }
            $this->orderFinancialIdentity($operation, $financial, $result);
        }
        return (int) DB::table('operation_receipts')->insertGetId([
            'idempotency_key' => $key, 'operation' => $operation, 'contract_version' => 1,
            'user_id' => $actor->id, 'request_hash' => $hash, 'order_id' => $orderId,
            'financial_operation_id' => $financialId,
            'result' => $financialId === null ? CanonicalJson::encode($result) : null,
            'result_hash' => CanonicalJson::hash($result), 'recorded_at_utc' => $context->utc(),
        ]);
    }

    private function identity(object $row, User $actor, string $operation, string $hash): void
    {
        if ($row->operation !== $operation || (int) $row->user_id !== (int) $actor->id
            || ! hash_equals($row->request_hash, $hash)) {
            throw new ConflictHttpException('Chave de idempotência já utilizada com outra identidade ou conteúdo.');
        }
    }

    private function orderFinancialIdentity(string $operation, ?object $financial, array $result): void
    {
        if ($operation === 'order.quote.create') {
            if ($financial !== null) {
                $this->unavailable();
            }
        } elseif ($financial === null || $financial->cash_movement_id !== null
            || (int) $financial->cash_register_id !== $result['cash_register_id']) {
            $this->unavailable();
        }
    }

    /** Verify immutable operation links, without consulting the order's current lifecycle. */
    private function orderLinks(object $receipt, array $result): void
    {
        $settlement = DB::table('order_settlements')->where('operation_receipt_id', $receipt->id)->first();
        if ($result['status'] === 'draft') {
            if ($settlement !== null || DB::table('order_stock_effects')->where('operation_receipt_id', $receipt->id)->exists()) {
                $this->unavailable();
            }
            return;
        }
        if ($settlement === null || (int) $settlement->order_id !== $result['order_id']
            || (int) $settlement->financial_operation_id !== (int) $receipt->financial_operation_id
            || (int) $settlement->cash_register_id !== $result['cash_register_id']
            || (int) $settlement->total_cents !== Money::decimal($result['total'])->cents) {
            $this->unavailable();
        }
        $effects = DB::table('order_cash_effects')->where('settlement_id', $settlement->id)->get();
        $stock = DB::table('order_stock_effects')->where('operation_receipt_id', $receipt->id)->get();
        $this->sameIds($effects->pluck('cash_movement_id')->all(), $result['cash_movement_ids']);
        $this->sameIds($stock->pluck('stock_movement_id')->all(), $result['stock_movement_ids']);
        $this->sameIds($effects->where('effect', 'payment')->pluck('order_payment_id')->all(), array_column($result['payments'], 'id'));
        foreach ($result['payments'] as $part) {
            $effect = $effects->firstWhere('order_payment_id', $part['id']);
            $payment = DB::table('order_payments')->where('id', $part['id'])->select('order_id', 'method')
                ->selectRaw('CAST(amount AS CHAR) as amount_text')->first();
            $movement = DB::table('cash_movements')->where('id', $effect->cash_movement_id)->select('order_id', 'cash_register_id', 'user_id', 'type', 'payment_method')
                ->selectRaw('CAST(amount AS CHAR) as amount_text')->first();
            if ($payment === null || $movement === null || (int) $payment->order_id !== $result['order_id']
                || $payment->method !== $part['method'] || Money::decimal($payment->amount_text)->cents !== Money::decimal($part['amount'])->cents
                || (int) $movement->order_id !== $result['order_id'] || (int) $movement->cash_register_id !== $result['cash_register_id']
                || (int) $movement->user_id !== (int) $receipt->user_id || $movement->type !== 'IN'
                || $movement->payment_method !== $part['method'] || Money::decimal($movement->amount_text)->cents !== Money::decimal($part['amount'])->cents) {
                $this->unavailable();
            }
        }
    }

    private function sameIds(array $actual, array $expected): void
    {
        sort($actual);
        sort($expected);
        if ($actual !== $expected) {
            $this->unavailable();
        }
    }

    private function envelope(string $key, string $operation, string $hash): void
    {
        if (! Str::isUuid($key) || $key !== strtolower($key) || ! preg_match('/\A[a-f0-9]{64}\z/', $hash)
            || ! in_array($operation, ['cash.open', 'cash.close', 'cash.movement', 'pos.checkout', 'order.quote.create', 'order.finalize'], true)) {
            throw new InvalidArgumentException('Identidade de operação inválida.');
        }
    }

    private function financialResult(object $row): array
    {
        if ($row->operation === 'cash.open') {
            return ['cash_register_id' => (int) $row->cash_register_id];
        }
        $result = $this->decode($row->result ?? null);
        // Native MySQL JSON may reorder object keys. New contracts have one
        // canonical response order; historical cash arrays keep their format.
        return str_starts_with($row->operation, 'cash.') ? $result
            : json_decode(CanonicalJson::encode($result), true, 512, JSON_THROW_ON_ERROR);
    }

    private function decode(?string $json): array
    {
        try {
            $result = json_decode($json ?? '', true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($result) || array_is_list($result)) {
                $this->unavailable();
            }
            CanonicalJson::encode($result);
            return $result;
        } catch (Throwable $exception) {
            throw new ServiceUnavailableHttpException(1, 'Resultado financeiro inválido. Preserve a chave.', $exception);
        }
    }

    private function unavailable(): never
    {
        throw new ServiceUnavailableHttpException(1, 'Integridade financeira indisponível. Preserve a chave.');
    }
}
