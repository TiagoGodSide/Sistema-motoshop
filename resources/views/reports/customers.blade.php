@extends('layouts.app')
@section('title','Relatórios — Clientes')

@section('content')
<h4><i class="bi bi-graph-up"></i> Relatório de Clientes</h4>

<form method="GET" class="row g-2 align-items-end mb-3">
  <div class="col-auto">
    <label class="form-label small">De</label>
    <input type="date" name="from" value="{{ optional($from)->toDateString() }}" class="form-control form-control-sm">
  </div>
  <div class="col-auto">
    <label class="form-label small">Até</label>
    <input type="date" name="to" value="{{ optional($to)->toDateString() }}" class="form-control form-control-sm">
  </div>
  <div class="col-sm-4">
    <label class="form-label small">Buscar</label>
    <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Nome, telefone, doc. ou e-mail">
  </div>
  <div class="col-auto">
    <button class="btn btn-dark btn-sm"><i class="bi bi-search"></i> Filtrar</button>
    <a href="{{ route('reports.customers.csv').'?'.http_build_query(request()->query()) }}"
       class="btn btn-outline-secondary btn-sm"><i class="bi bi-download"></i> Exportar CSV</a>
  </div>
</form>

<div class="table-responsive">
  <table class="table table-sm align-middle">
    <thead class="table-light">
      <tr>
        <th>Cliente</th>
        <th>Contato</th>
        <th class="text-center">Pedidos</th>
        <th class="text-end">Total (R$)</th>
        <th class="text-end">Última compra</th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $r)
        <tr>
          <td class="fw-medium">{{ $r->name }}</td>
          <td class="small text-muted">
            {{ $r->phone ?? '—' }} {!! $r->document ? ' · <span>'.$r->document.'</span>' : '' !!}
          </td>
          <td class="text-center">{{ $r->total_orders }}</td>
          <td class="text-end">R$ {{ number_format($r->total_spent,2,',','.') }}</td>
          <td class="text-end">{{ $r->last_order_at ? \Carbon\Carbon::parse($r->last_order_at)->format('d/m/Y H:i') : '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="5" class="text-center text-muted">Nenhum resultado.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

{{ $rows->links() }}
@endsection
