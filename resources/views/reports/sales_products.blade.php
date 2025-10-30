@extends('layouts.app')
@section('title','Vendas por produto')

@section('content')
<h4>Vendas por produto</h4>
<form method="get" class="row g-2 mb-3">
  <div class="col-md-3"><label class="form-label">De</label><input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control"></div>
  <div class="col-md-3"><label class="form-label">Até</label><input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control"></div>
  <div class="col-md-4"><label class="form-label">Produto/SKU</label><input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="nome ou SKU"></div>
  <div class="col-md-2 d-flex align-items-end gap-2">
    <button class="btn btn-dark">Filtrar</button>
    <a href="{{ route('reports.sales.products.csv', request()->query()) }}" class="btn btn-outline-secondary">Exportar CSV</a>
  </div>
</form>

<div class="table-responsive">
  <table class="table table-sm align-middle">
    <thead class="table-light"><tr><th>Produto</th><th>SKU</th><th>Categoria</th><th class="text-end">Qtd</th><th class="text-end">Faturamento</th></tr></thead>
    <tbody>
      @foreach($rows as $r)
        <tr>
          <td>{{ $r->name }}</td>
          <td>{{ $r->sku }}</td>
          <td>{{ $r->category }}</td>
          <td class="text-end">{{ (int)$r->qty }}</td>
          <td class="text-end fw-bold">R$ {{ number_format($r->revenue,2,',','.') }}</td>
        </tr>
      @endforeach
    </tbody>
    <tfoot class="table-light">
      <tr>
        <th colspan="3" class="text-end">Totais</th>
        <th class="text-end">{{ (int)$totals['qty'] }}</th>
        <th class="text-end">R$ {{ number_format($totals['revenue'],2,',','.') }}</th>
      </tr>
    </tfoot>
  </table>
</div>
@endsection
