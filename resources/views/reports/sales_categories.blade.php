@extends('layouts.app')
@section('title','Vendas por categoria')

@section('content')
<h4>Vendas por categoria</h4>
<form method="get" class="row g-2 mb-3">
  <div class="col-md-3"><label class="form-label">De</label><input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control"></div>
  <div class="col-md-3"><label class="form-label">Até</label><input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control"></div>
  <div class="col-md-2 d-flex align-items-end"><button class="btn btn-dark">Filtrar</button></div>
  <div class="col-md-4 d-flex align-items-end justify-content-end">
    <a href="{{ route('reports.sales.categories.csv', request()->query()) }}" class="btn btn-outline-secondary">Exportar CSV</a>
  </div>
</form>

<div class="table-responsive">
  <table class="table table-sm align-middle">
    <thead class="table-light"><tr><th>Categoria</th><th class="text-end">Qtd</th><th class="text-end">Faturamento</th></tr></thead>
    <tbody>
      @foreach($rows as $r)
        <tr>
          <td>{{ $r->category }}</td>
          <td class="text-end">{{ (int)$r->qty }}</td>
          <td class="text-end fw-bold">R$ {{ number_format($r->revenue,2,',','.') }}</td>
        </tr>
      @endforeach
    </tbody>
    <tfoot class="table-light">
      <tr>
        <th>Total</th>
        <th class="text-end">{{ (int)$totals['qty'] }}</th>
        <th class="text-end">R$ {{ number_format($totals['revenue'],2,',','.') }}</th>
      </tr>
    </tfoot>
  </table>
</div>
@endsection
