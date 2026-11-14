<?php

namespace App\Services;

use App\Contracts\Orders\PriceExceptionAuthorization;
use App\Data\Orders\CheckoutCommand;
use App\Data\Orders\OrderTotals;
use App\Data\Orders\QuoteCommand;
use App\Models\User;
use App\Support\Money;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class OrderAmountCalculator
{
    public function calculate(
        CheckoutCommand|QuoteCommand $command, array $catalog, User $actor,
        ?PriceExceptionAuthorization $authorization = null,
    ): OrderTotals {
        $authorization ??= new DenyPriceExceptions();
        $data = $command->payload();
        (new OrderStockPolicy())->validate($data['items'], $catalog, $command instanceof CheckoutCommand);
        $subtotal = Money::cents(0);
        $lines = [];
        foreach ($data['items'] as $item) {
            $product = $catalog[$item['product_id']];
            if ($product->price->decimalString() !== $item['expected_unit_price']) {
                throw new ConflictHttpException('O preço de catálogo mudou; confirme os valores.');
            }
            $unit = isset($item['requested_unit_price']) ? Money::decimal($item['requested_unit_price']) : $product->price;
            $discount = Money::decimal($item['discount']);
            $exception = $unit->cents !== $product->price->cents || $discount->cents > 0;
            if ($exception) {
                if ($item['price_reason'] === null) {
                    throw new InvalidArgumentException('Justificativa de preço/desconto obrigatória.');
                }
                $authorization->authorize($actor, 'line-price', ['product_id' => $product->id, 'catalog_cents' => $product->price->cents,
                    'requested_cents' => $unit->cents, 'discount_cents' => $discount->cents, 'reason' => $item['price_reason']]);
            }
            $net = $unit->multiply($item['qty'])->subtract($discount);
            $subtotal = $subtotal->add($net);
            $lines[] = [
                'product_id' => $product->id, 'qty' => $item['qty'], 'catalog_price_cents' => $product->price->cents,
                'unit_price_cents' => $unit->cents, 'discount_cents' => $discount->cents, 'total_cents' => $net->cents,
                'price_source' => $exception ? 'negotiated' : 'catalog',
                'price_reason' => $item['price_reason'], 'price_approved_by' => $exception ? $actor->id : null,
                'stock_policy' => $product->stockPolicy,
            ];
        }
        $discount = Money::decimal($data['discount']);
        if ($discount->cents > 0) {
            if ($data['discount_reason'] === null) {
                throw new InvalidArgumentException('Justificativa do desconto do pedido obrigatória.');
            }
            $authorization->authorize($actor, 'order-discount', ['discount_cents' => $discount->cents, 'reason' => $data['discount_reason']]);
        }
        $total = $subtotal->subtract($discount);
        if ($command instanceof CheckoutCommand) {
            if ($total->cents === 0 || $total->decimalString() !== $data['expected_total']) {
                throw new ConflictHttpException('Total validado no servidor diverge da confirmação.');
            }
            $this->validatePayments($total, $data['payments']);
        }
        return new OrderTotals($subtotal, $discount, $total, $lines);
    }

    public function validatePayments(Money $total, array $payments): void
    {
        $sum = Money::cents(0);
        foreach ($payments as $payment) {
            $part = Money::decimal($payment['amount']);
            if ($part->cents === 0) {
                throw new InvalidArgumentException('Pagamento deve ser positivo.');
            }
            $sum = $sum->add($part);
        }
        if ($total->cents === 0 || $sum->cents !== $total->cents) {
            throw new InvalidArgumentException('Pagamento confirmado integral obrigatório.');
        }
    }
}
