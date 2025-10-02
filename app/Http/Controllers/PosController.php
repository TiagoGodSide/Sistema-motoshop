<?php

namespace App\Http\Controllers;

use App\Models\CashRegister;
use App\Models\CashMovement;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PosController extends Controller
{
    public function index()
{
    // Só renderiza a view; nada de dados na Blade
    return view('pos.index');
}

/** Busca PDV: se q vier vazio, devolve TOP itens ativos */
public function find(Request $request)
{
    $q = trim((string)$request->get('q', ''));

    $query = \App\Models\Product::query()
        ->select('id','name','sku','ean','internal_barcode','price','stock')
        ->where('is_active', true);

    if ($q !== '') {
        $query->where(function($w) use ($q) {
            $w->where('name','like',"%{$q}%")
              ->orWhere('sku',$q)
              ->orWhere('ean',$q)
              ->orWhere('internal_barcode',$q);
        })
        // empurra resultados com match exato de SKU/código pra cima
        ->orderByRaw("CASE WHEN sku = ? OR ean = ? OR internal_barcode = ? THEN 0 ELSE 1 END", [$q,$q,$q])
        ->orderBy('name');
    } else {
        // sem q → lista inicial
        $query->orderBy('name');
    }

    return response()->json($query->limit(20)->get());
}

        public function checkout(Request $request)
{
    $data = $request->validate([
        'items' => ['required','array','min:1'],
        'items.*.product_id' => ['required','integer','exists:products,id'],
        'items.*.qty' => ['required','integer','min:1'],
        'items.*.price' => ['required','numeric','min:0'],
        'items.*.discount' => ['nullable','numeric','min:0'],
        'discount' => ['nullable','numeric','min:0'],
        'payment_method' => ['nullable','string','max:40'],
        'lowered_stock' => ['nullable','boolean'],
        'customer_name' => ['nullable','string','max:120'],
        'draft' => ['nullable','boolean'],
    ]);

    // >>> DEFINA o isDraft ANTES de qualquer uso <<<
    $isDraft = (bool)($data['draft'] ?? false);

    // (Opcional) bloquear finalização sem caixa aberto
    if (!$isDraft) {
        $regOpen = CashRegister::open()->latest('id')->first();
        if (!$regOpen) {
            return response('Abra o caixa antes de finalizar uma venda.', 422);
        }
    }

    $order = DB::transaction(function () use ($data, $request, $isDraft) {
        // Totais (considerando desconto por item)
        $subtotal = collect($data['items'])->sum(function($i){
            $line = ($i['qty'] * $i['price']) - floatval($i['discount'] ?? 0);
            return max(0, $line);
        });
        $discount = floatval($data['discount'] ?? 0);
        $total    = max(0, $subtotal - $discount);

        // Cria pedido (draft ou pago)
        $order = Order::create([
            'number'         => $this->makeOrderNumber(),
            'customer_name'  => $data['customer_name'] ?? null,
            'subtotal'       => $subtotal,
            'discount'       => $discount,
            'total'          => $total,
            'payment_method' => $isDraft ? null : ($data['payment_method'] ?? 'dinheiro'),
            'status'         => $isDraft ? 'draft' : 'paid',
            'lowered_stock'  => $isDraft ? false : (bool)($data['lowered_stock'] ?? true),
            'user_id'        => optional($request->user())->id,
        ]);

        // Itens + baixa de estoque (se pago e marcado)
        foreach ($data['items'] as $it) {
            OrderItem::create([
                'order_id'   => $order->id,
                'product_id' => $it['product_id'],
                'qty'        => $it['qty'],
                'price'      => $it['price'],
                'discount'   => floatval($it['discount'] ?? 0),
            ]);

            if (!$isDraft && $order->lowered_stock) {
                $product = Product::lockForUpdate()->find($it['product_id']);
                $product->decrement('stock', $it['qty']);
                StockMovement::create([
                    'product_id' => $product->id,
                    'type'       => 'OUT',
                    'qty'        => $it['qty'],
                    'unit_price' => $it['price'],
                    'reason'     => 'Venda PDV '.$order->number,
                    'user_id'    => optional($request->user())->id,
                ]);
            }
        }

        // Caixa (somente pedidos pagos)
        if (!$isDraft) {
            if ($reg = CashRegister::open()->latest('id')->first()) {
                CashMovement::create([
                    'cash_register_id' => $reg->id,
                    'type'             => 'IN',
                    'amount'           => $total,
                    'payment_method'   => $order->payment_method, // dinheiro/pix/cartao/outro
                    'reason'           => 'Venda PDV '.$order->number,
                    'order_id'         => $order->id,
                    'user_id'          => $order->user_id,
                ]);

                // Sangria automática (apenas dinheiro)
                if ($order->payment_method === 'dinheiro') {
                    $limit = (float) env('CASH_SANGRIA_LIMIT', 0);
                    $keep  = (float) env('CASH_SANGRIA_KEEP', 0);
                    if ($limit > 0) {
                        $sum = CashMovement::where('cash_register_id',$reg->id)
                            ->where(function($q){
                                $q->whereNull('payment_method')->orWhere('payment_method','dinheiro');
                            })
                            ->selectRaw("SUM(CASE WHEN type='IN' THEN amount ELSE -amount END) as total")
                            ->value('total');
                        $current = (float)$reg->opening_amount + (float)$sum;
                        if ($current > $limit) {
                            $withdraw = max(0, $current - max($keep, 0));
                            if ($withdraw > 0.01) {
                                CashMovement::create([
                                    'cash_register_id' => $reg->id,
                                    'type'             => 'OUT',
                                    'amount'           => $withdraw,
                                    'payment_method'   => 'dinheiro',
                                    'reason'           => 'Sangria automática (limite)',
                                    'order_id'         => null,
                                    'user_id'          => $order->user_id,
                                ]);
                            }
                        }
                    }
                }
            }
        }

        return $order->fresh('items');
    });

    return response()->json($order, 201);
}

    protected function makeOrderNumber(): string
    {
        // Ex.: 2025-000001
        $prefix = now()->format('Y');
        $last = Order::where('number','like',"$prefix-%")->max('number');
        $seq = 1;
        if ($last) {
            $seq = (int)substr($last, -6) + 1;
        }
        return sprintf('%s-%06d', $prefix, $seq);
    }
}
