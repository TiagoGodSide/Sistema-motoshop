<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Cupom — {{ $order->number }}</title>
<style>
  @media print { .no-print{display:none} body{ margin:0 } }
  body { font-family: Arial, sans-serif; }
  .ticket { width: 58mm; padding: 6px; }
  .center { text-align:center }
  .row { display:flex; justify-content:space-between; }
  hr { border:0; border-top:1px dashed #888; margin:6px 0 }
  .small { font-size: 11px }
  .bold { font-weight: bold }
</style>
</head>
<body>
<div class="ticket">
  <div class="center">
    <div class="bold">OFICINA & LOJA DE MOTOS</div>
    <div class="small">CNPJ 00.000.000/0001-00</div>
    <div class="small">Rua Exemplo, 123 - Cidade/UF</div>
    <hr>
    <div class="small">CUPOM NÃO FISCAL</div>
  </div>

  <div class="small">Pedido: <span class="bold">{{ $order->number }}</span></div>
  <div class="small">Data: {{ $order->created_at->format('d/m/Y H:i') }}</div>
  <div class="small">Cliente: {{ $order->customer->name ?? $order->customer_name ?? '—' }}</div>
  <hr>

  @foreach($order->items as $it)
    @php $sub = max(0, ($it->qty * $it->price) - ($it->discount ?? 0)); @endphp
    <div class="small">{{ $it->product->name ?? '—' }}</div>
    <div class="row small">
      <div>{{ $it->qty }} x {{ number_format($it->price,2,',','.') }}</div>
      <div>R$ {{ number_format($sub,2,',','.') }}</div>
    </div>
    @if(($it->discount ?? 0) > 0)
      <div class="row small"><div>Desc item</div><div>- R$ {{ number_format($it->discount,2,',','.') }}</div></div>
    @endif
  @endforeach

  <hr>
  <div class="row small"><div>Subtotal</div><div>R$ {{ number_format($order->subtotal,2,',','.') }}</div></div>
  <div class="row small"><div>Desconto</div><div>- R$ {{ number_format($order->discount,2,',','.') }}</div></div>
  <div class="row bold"><div>Total</div><div>R$ {{ number_format($order->total,2,',','.') }}</div></div>

  {{-- Pagamentos (suporta split) --}}
  @if($order->payments && $order->payments->count())
    <div class="small" style="margin-top:2px">Pagamentos:</div>
    @foreach($order->payments as $p)
      <div class="row small"><div>{{ ucfirst($p->method) }}</div><div>R$ {{ number_format($p->amount,2,',','.') }}</div></div>
    @endforeach
  @elseif($order->payment_method)
    <div class="small">Pagamento: {{ ucfirst($order->payment_method) }}</div>
  @endif

  {{-- PIX (se houver qualquer parte em pix) --}}
  @php
    $pm = strtolower(trim((string)$order->payment_method));
    $hasPixLocal = ($pm === 'pix') || ($order->payments && $order->payments->where('method','pix')->sum('amount') > 0);
  @endphp
  @if($hasPixLocal && isset($pixPayload))
    <hr>
    <div class="center small bold">PAGAMENTO PIX</div>

    @if(isset($qrSvg) && $qrSvg)
      <div class="center" style="margin:6px 0">{!! $qrSvg !!}</div>
    @else
      <div class="center" style="margin:6px 0">
        {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(168)->margin(0)->generate($pixPayload) !!}
      </div>
    @endif

    <div class="small">Copia e cola:</div>
    <div class="small" id="pix-payload" style="word-break:break-all">{{ $pixPayload }}</div>
    <div class="no-print center" style="margin-top:6px">
      <button id="btn-copy-pix" class="btn">Copiar PIX</button>
    </div>
    <script>
      document.getElementById('btn-copy-pix')?.addEventListener('click', async ()=>{
        const t = document.getElementById('pix-payload')?.innerText?.trim() || '';
        try { await navigator.clipboard.writeText(t); alert('Copia e cola copiado!'); }
        catch { alert('Não foi possível copiar automaticamente. Selecione e copie manualmente.'); }
      });
    </script>
  @endif

  <hr>
  <div class="center small">Obrigado pela preferência!</div>
</div>

<div class="no-print" style="padding:8px; text-align:center">
  <a href="javascript:window.print()" class="btn">Imprimir</a>
</div>
</body>
</html>
