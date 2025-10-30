@extends('layouts.app')
@section('title','Pedido '.$order->number)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-receipt"></i> Pedido {{ $order->number }}</h4>
  <div class="d-print-none">
    <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
    @if($order->status==='paid')
      <form action="{{ route('orders.cancel',$order) }}" method="POST" class="d-inline">@csrf
        <button class="btn btn-outline-danger"><i class="bi bi-x-circle"></i> Cancelar</button>
      </form>
    @endif
    <a href="{{ route('orders.index') }}" class="btn btn-light">Voltar</a>
  </div>
           @if($order->status==='draft')
              <form action="{{ route('orders.finalize',$order) }}" method="POST" class="d-inline-flex align-items-center gap-2">
                @csrf
                <select name="payment_method" class="form-select form-select-sm w-auto">
                  @foreach(config('payment.methods', ['dinheiro'=>'Dinheiro','pix'=>'PIX','cartao'=>'Cartão','outro'=>'Outro']) as $val=>$label)
                    <option value="{{ $val }}">{{ $label }}</option>
                  @endforeach
                </select>

                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" name="lowered_stock" value="1" id="finalLowered" checked>
                  <label class="form-check-label small" for="finalLowered">Dar baixa</label>
                </div>

                <button class="btn btn-success btn-sm">
                  <i class="bi bi-cash-coin"></i> Finalizar venda
                </button>
              </form>
            @endif

        <a href="{{ route('orders.receipt',$order) }}" target="_blank" class="btn btn-outline-secondary d-print-none">
          <i class="bi bi-printer"></i> Cupom
        </a>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card">
      <div class="card-body">
        <h6 class="card-title">Resumo</h6>
        <dl class="row mb-0">
          <dt class="col-5">Número</dt><dd class="col-7">{{ $order->number }}</dd>
          <dt class="col-5">Data</dt><dd class="col-7">{{ $order->created_at->format('d/m/Y H:i') }}</dd>
          <p><strong>Cliente:</strong>{{ $order->customer->name ?? $order->customer_name ?? '—' }}</p>
          <dt class="col-5">Vendedor</dt><dd class="col-7">{{ optional($order->user)->name ?? '—' }}</dd>
          <dt class="col-5">Status</dt>
          <dd class="col-7">
            @if($order->status==='paid')<span class="badge bg-success">Pago</span>
            @elseif($order->status==='cancelled')<span class="badge bg-danger">Cancelado</span>
            @else<span class="badge bg-secondary">{{ ucfirst($order->status) }}</span>
            @endif
            @if($order->lowered_stock) <span class="badge bg-dark">Baixou estoque</span> @endif
          </dd>
          <dt class="col-5">Forma de pagamento</dt><dd class="col-7">{{ ucfirst($order->payment_method) }}</dd>
        </dl>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card">
      <div class="card-body">
        <h6 class="card-title">Totais</h6>
        <div class="d-flex justify-content-between"><span class="text-muted">Subtotal</span><strong>R$ {{ number_format($order->subtotal,2,',','.') }}</strong></div>
        <div class="d-flex justify-content-between"><span class="text-muted">Desconto</span><strong>R$ {{ number_format($order->discount,2,',','.') }}</strong></div>
        <div class="d-flex justify-content-between fs-5 border-top pt-2"><span>Total</span><strong>R$ {{ number_format($order->total,2,',','.') }}</strong></div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <h6 class="card-title mb-3">Itens</h6>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead class="table-light">
              <tr><th>Produto</th><th>SKU</th><th class="text-center">Qtd</th><th class="text-end">Preço</th><th class="text-end">Subtotal</th></tr>
            </thead>
            <tbody>
              @foreach($order->items as $it)
                <tr>
                  <td>{{ $it->product->name ?? '—' }}</td>
                  <td>{{ $it->product->sku ?? '—' }}</td>
                  <td class="text-center">{{ $it->qty }}</td>
                  <td class="text-end">R$ {{ number_format($it->price,2,',','.') }}</td>
                  <td class="text-end">R$ {{ number_format($it->qty * $it->price,2,',','.') }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <small class="text-muted">Impresso em {{ now()->format('d/m/Y H:i') }}</small>
      </div>
    </div>
  </div>
</div>
@endsection
