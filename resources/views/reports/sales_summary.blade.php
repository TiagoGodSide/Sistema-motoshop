@extends('layouts.app')
@section('title','Vendas — resumo')

@section('content')
<h4>Vendas — resumo</h4>
<form method="get" class="row g-2 mb-3">
  <div class="col-md-3"><label class="form-label">De</label><input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control"></div>
  <div class="col-md-3"><label class="form-label">Até</label><input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control"></div>
  <div class="col-md-3">
    <label class="form-label">Pagamento</label>
    <select name="payment" class="form-select">
      <option value="">Todos</option>
      <option value="pix"   @selected($pay==='pix')>PIX</option>
      <option value="cash"  @selected($pay==='cash')>Dinheiro</option>
      <option value="card"  @selected($pay==='card')>Cartão</option>
    </select>
  </div>
  <div class="col-md-3 d-flex align-items-end gap-2">
    <button class="btn btn-dark">Filtrar</button>
    <a href="{{ route('reports.sales.summary.csv', request()->query()) }}" class="btn btn-outline-secondary">Exportar CSV</a>
  </div>
</form>

<div class="table-responsive">
  <table class="table table-sm align-middle">
    <thead class="table-light"><tr><th>Dia</th><th class="text-end">Pedidos</th><th class="text-end">Subtotal</th><th class="text-end">Desconto</th><th class="text-end">Total</th></tr></thead>
    <tbody>
      @foreach($rows as $r)
        <tr>
          <td>{{ \Carbon\Carbon::parse($r->day)->format('d/m/Y') }}</td>
          <td class="text-end">{{ $r->orders }}</td>
          <td class="text-end">R$ {{ number_format($r->subtotal,2,',','.') }}</td>
          <td class="text-end">R$ {{ number_format($r->discount,2,',','.') }}</td>
          <td class="text-end fw-bold">R$ {{ number_format($r->total,2,',','.') }}</td>
        </tr>
      @endforeach
    </tbody>
    <tfoot class="table-light">
      <tr>
        <th>Total</th>
        <th class="text-end">{{ $totals['orders'] }}</th>
        <th class="text-end">R$ {{ number_format($totals['subtotal'],2,',','.') }}</th>
        <th class="text-end">R$ {{ number_format($totals['discount'],2,',','.') }}</th>
        <th class="text-end">R$ {{ number_format($totals['total'],2,',','.') }}</th>
      </tr>
    </tfoot>
  </table>
</div>
@endsection
