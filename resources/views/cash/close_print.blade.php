<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Fechamento de Caixa</title>
<style>
  @media print { .no-print{display:none} body{ margin:0 } }
  body{ font-family: Arial, sans-serif; }
  .ticket { width: 72mm; max-width: 100%; padding: 8px; }
  .center { text-align: center }
  .row { display:flex; justify-content: space-between; }
  hr { border:0; border-top:1px dashed #888; margin:6px 0 }
  .small { font-size: 11px }
  .bold { font-weight: bold }
</style>
</head>
<body>
<div class="ticket">
  <div class="center">
    <div class="bold">OFICINA & LOJA DE MOTOS</div>
    <div class="small">Fechamento de Caixa</div>
    <hr>
  </div>

  <div class="small">Aberto em: <span class="bold">{{ optional($reg->opened_at)->format('d/m/Y H:i') }}</span></div>
  <div class="small">Aberto por: {{ optional($reg->openedBy)->name ?? '—' }}</div>
  @if(!empty($reg->closed_at))
    <div class="small">Fechado em: <span class="bold">{{ $reg->closed_at->format('d/m/Y H:i') }}</span></div>
  @endif
  @if(!empty($reg->closedBy))
    <div class="small">Fechado por: {{ optional($reg->closedBy)->name ?? '—' }}</div>
  @endif
  <hr>

  <div class="row small"><div>Valor de abertura</div><div>R$ {{ number_format((float)($reg->opening_amount ?? 0),2,',','.') }}</div></div>

  <div class="row small"><div>Entradas (mov.)</div><div>R$ {{ number_format($byMethod->sum('entradas'),2,',','.') }}</div></div>
  <div class="row small"><div>Saídas (mov.)</div><div>R$ {{ number_format($byMethod->sum('saidas'),2,',','.') }}</div></div>
  <div class="row small"><div>Sangrias</div><div>R$ {{ number_format($sangrias,2,',','.') }}</div></div>
  <div class="row bold"><div>Previsão de fechamento</div><div>R$ {{ number_format($prevClosing,2,',','.') }}</div></div>

  <hr>
  <div class="center small bold">Movimentações por pagamento</div>
  @forelse($byMethod as $row)
    <div class="small bold">{{ $row->payment_method ? ucfirst($row->payment_method) : '—' }}</div>
    <div class="row small"><div>Entradas</div><div>R$ {{ number_format($row->entradas,2,',','.') }}</div></div>
    <div class="row small"><div>Saídas</div><div>R$ {{ number_format($row->saidas,2,',','.') }}</div></div>
    <div class="row small"><div>Saldo</div><div>R$ {{ number_format($row->saldo,2,',','.') }}</div></div>
  @empty
    <div class="small">Sem movimentações.</div>
  @endforelse

  @if(!empty($reg->close_notes))
    <hr>
    <div class="small"><span class="bold">Obs. fechamento:</span> {{ $reg->close_notes }}</div>
  @endif

  <hr>
  <div class="center small">Impresso em {{ now()->format('d/m/Y H:i') }}</div>
</div>

<div class="no-print" style="padding:8px; text-align:center">
  <button onclick="window.print()">Imprimir</button>
</div>
</body>
</html>
