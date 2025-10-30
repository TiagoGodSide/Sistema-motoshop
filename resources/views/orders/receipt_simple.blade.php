<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Cupom — {{ $order->number }}</title>
<style>
  @media print { .no-print{display:none} body{ margin:0 } }
  body { font-family: Arial, sans-serif; }
  .ticket { width: {{ request('w','58') }}mm; padding: 6px; }
  .c { text-align:center } .r{ text-align:right }
  .small { font-size: 11px } .bold{font-weight:bold}
  hr { border:0;border-top:1px dashed #000;margin:6px 0 }
</style>
</head>
<body>
<div class="ticket">
  @if(file_exists(public_path('storage/logo.png')))
    <div class="c"><img src="{{ asset('storage/logo.png') }}" alt="logo" style="max-width:100%;max-height:60px"></div>
  @else
    <div class="c bold">OFICINA & LOJA DE MOTOS</div>
  @endif

  <div class="c small">CUPOM NÃO FISCAL</div>
  <hr>

  <div class="small">Pedido: <span class="bold">{{ $order->number }}</span></div>
  <div class="small">Data: {{ $order->created_at->format('d/m/Y H:i') }}</div>
  <div class="small">Cliente: {{ $order->customer->name ?? $order->customer_name ?? '—' }}</div>
  <hr>

  @foreach($order->items as $it)
    @php $sub = max(0, ($it->qty * $it->price) - ($it->discount ?? 0)); @endphp
    <div class="small">{{ $it->product->name ?? '—' }}</div>
    <div class="small r">{{ $it->qty }} x {{ number_format($it->price,2,',','.') }} = R$ {{ number_format($sub,2,',','.') }}</div>
  @endforeach

  <hr>
  <div class="small r">Subtotal: R$ {{ number_format($order->subtotal,2,',','.') }}</div>
  <div class="small r">Desconto: - R$ {{ number_format($order->discount,2,',','.') }}</div>
  <div class="bold r">Total: R$ {{ number_format($order->total,2,',','.') }}</div>

  @if($order->payments->count())
    <hr>
    <div class="small bold">Pagamentos</div>
    @foreach($order->payments as $p)
      <div class="small r">{{ ucfirst($p->method) }}: R$ {{ number_format($p->amount,2,',','.') }}</div>
    @endforeach
  @elseif($order->payment_method)
    <div class="small r">Pagamento: {{ ucfirst($order->payment_method) }}</div>
  @endif

  <hr>
  <div class="c small">Obrigado!</div>
</div>

<div class="no-print" style="padding:8px; text-align:center">
  <a href="javascript:window.print()" class="btn">Imprimir</a>
  {{-- width: ?w=80 para 80mm --}}
</div>
</body>
</html>
