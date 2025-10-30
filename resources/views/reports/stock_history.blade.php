@extends('layouts.app')
@section('title','Histórico de estoque (geral)')

@section('content')
<h4>Histórico de estoque</h4>
<form method="get" class="row g-2 mb-3">
  <div class="col-md-3"><label class="form-label">De</label><input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control"></div>
  <div class="col-md-3"><label class="form-label">Até</label><input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control"></div>
  <div class="col-md-3"><label class="form-label">Produto ID (opcional)</label><input type="number" name="product_id" value="{{ $productId ?: '' }}" class="form-control" min="1"></div>
  <div class="col-md-2">
    <label class="form-label">Tipo</label>
    <select name="type" class="form-select">
      <option value="">Todos</option>
      <option value="in"  @selected($type==='in')>Entrada</option>
      <option value="out" @selected($type==='out')>Saída</option>
    </select>
  </div>
  <div class="col-md-1 d-flex align-items-end">
    <button class="btn btn-dark w-100">Filtrar</button>
  </div>
  <div class="col-12 text-end">
    <a href="{{ route('reports.stock.history.csv', request()->query()) }}" class="btn btn-outline-secondary">Exportar CSV</a>
  </div>
</form>

<div class="mb-2">
  <span class="badge text-bg-success">Entradas: {{ (int)$totIn }}</span>
  <span class="badge text-bg-danger">Saídas: {{ (int)$totOut }}</span>
  <span class="badge text-bg-secondary">Saldo período: {{ (int)$totIn - (int)$totOut }}</span>
</div>

<div class="table-responsive">
  <table class="table table-sm align-middle">
    <thead class="table-light"><tr>
      <th>Data</th><th>Produto</th><th>SKU</th><th>Tipo</th>
      <th class="text-end">Qtd</th><th class="text-end">Preço unit.</th>
      <th>Motivo</th><th>Usuário</th>
    </tr></thead>
    <tbody>
      @forelse($moves as $m)
        <tr>
          <td>{{ \Carbon\Carbon::parse($m->created_at)->format('d/m/Y H:i') }}</td>
          <td>{{ $m->product_name }}</td>
          <td>{{ $m->sku }}</td>
          <td>{{ $m->type==='in' ? 'Entrada (+)' : 'Saída (−)' }}</td>
          <td class="text-end">{{ (int)$m->qty }}</td>
          <td class="text-end">R$ {{ number_format((float)$m->unit_price,2,',','.') }}</td>
          <td class="text-truncate" style="max-width:260px">{{ $m->reason ?? $m->reason_code }}</td>
          <td>{{ $m->user_id }}</td>
        </tr>
      @empty
        <tr><td colspan="8" class="text-center text-muted">Sem registros.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
{{ $moves->links() }}
@endsection
