<?php

namespace App\Http\Controllers;

use App\Models\CashRegister;
use App\Models\CashMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\Pix;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->date('from');
        $to   = $request->date('to');
        $status = $request->get('status');

        $orders = Order::query()
            ->when($from, fn($q) => $q->whereDate('created_at','>=',$from))
            ->when($to,   fn($q) => $q->whereDate('created_at','<=',$to))
            ->when($status, fn($q) => $q->where('status',$status))
            ->latest()
            ->withCount('items')
            ->paginate(20)
            ->appends($request->query());

        return view('orders.index', compact('orders','from','to','status'));
       // return response()->json($orders);
    }

    public function show(Order $order)
    {
        $order->load('items.product');
        return view('orders.show', compact('order'));
        //return response()->json($order);
    }

    /** Cancela e estorna estoque (se a venda havia baixado) */
    public function cancel(\App\Models\Order $order)
{
    abort_if($order->status === 'canceled', 422, 'Pedido já cancelado.');

    DB::transaction(function () use ($order) {
        // Se houve baixa na finalização e ainda não foi revertida, repõe o estoque
        if ($order->lowered_stock && is_null($order->stock_reverted_at)) {
            foreach ($order->items as $it) {
                if ($it->product_id && $it->qty > 0) {
                    \App\Models\Product::where('id',$it->product_id)->lockForUpdate()->increment('stock', $it->qty);
                }
            }
            $order->stock_reverted_at = now();
            $order->lowered_stock     = false; // estado atual: sem baixa válida
        }

        $order->status = 'canceled';
        $order->save();
    });

    // HTML
    if (!request()->wantsJson()) {
        return back()->with('ok', 'Pedido cancelado e estoque ajustado.');
    }
    // API
   // return response()->json(['ok'=>true]);
}


    public function finalize(Order $order, Request $request)
{
    abort_if($order->status !== 'draft', 422, 'Apenas orçamentos podem ser finalizados.');

    $data = $request->validate([
        'payment_method' => ['required','in:dinheiro,pix,cartao,outro'],
        'lowered_stock'  => ['nullable','boolean'],
    ]);
    $method = strtolower(trim($data['payment_method']));
    DB::transaction(function () use ($order, $request, $data) {
                $order->update([
            'status' => 'paid',
            'payment_method' => $method,   // << normalizado
            'lowered_stock'  => (bool)($data['lowered_stock'] ?? true),
        ]);

        if ($order->lowered_stock) {
            foreach ($order->items as $it) {
                $product = Product::lockForUpdate()->find($it->product_id);
                $product->decrement('stock', $it->qty);
                StockMovement::create([
                    'product_id' => $product->id,
                    'type'       => 'OUT',
                    'qty'        => $it->qty,
                    'unit_price' => $it->price,
                    'reason'     => 'Finalização orçamento '.$order->number,
                    'user_id'    => optional($request->user())->id,
                ]);
            }
        }

        if ($reg = CashRegister::open()->latest('id')->first()) {
            CashMovement::create([
                'cash_register_id' => $reg->id,
                'type' => 'IN',
                'amount' => $order->total,
                'payment_method' => $order->payment_method,
                'reason' => 'Venda (finalização) '.$order->number,
                'order_id' => $order->id,
                'user_id' => optional($request->user())->id,
            ]);
        }
    });

    return redirect()->route('orders.show',$order)->with('ok','Orçamento finalizado.');
}


        public function receipt(\App\Models\Order $order) {
    $order->load('items.product','user');

    $pixPayload = Pix::payload($order->total, $order->number, 'Pedido '.$order->number);

    // gera SVG só quando for PIX
    $qrSvg = $order->payment_method === 'pix'
        ? QrCode::size(168)->margin(0)->generate($pixPayload)
        : null;

        // PIX: exibe QR se houver QUALQUER pagamento em pix
        $hasPix = $order->payments->where('method','pix')->sum('amount') > 0
                || strtolower((string)$order->payment_method)==='pix';

        $pixPayload = $hasPix ? Pix::payload($order->total, $order->number, 'Pedido '.$order->number) : null;
        $qrSvg = $hasPix ? QrCode::size(168)->margin(0)->generate($pixPayload) : null;

        return view('orders.receipt', compact('order','pixPayload','qrSvg','hasPix'));
        }

        public function receiptSimple(Order $order) {
        $order->load('items.product','payments','user');
        return view('orders.receipt_simple', compact('order'));
    return view('orders.receipt', compact('order','pixPayload','qrSvg'));
}
}
