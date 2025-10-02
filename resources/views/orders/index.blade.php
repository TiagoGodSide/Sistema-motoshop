@extends('layouts.app')
@section('title','Pedidos')
@section('content')

<h4><i class="bi bi-receipt-cutoff"></i> Pedidos</h4>

<form method="get" class="row g-2 align-items-end mb-3">
  <div class="col-auto">
    <label class="form-label">De</label>
    <input type="date" class="form-control" name="from" value="{{ request('from') }}">
  </div>
  <div class="col-auto">
    <label class="form-label">Até</label>
    <input type="date" class="form-control" name="to" value="{{ request('to') }}">
  </div>
  <div class="col-auto">
    <label class="form-label">Status</label>
    <select name="status" class="form-select">
      <option value="">Todos</option>
      <option value="open" {{ request('status')==='open'?'selected':'' }}>Aberto</option>
      <option value="paid" {{ request('status')==='paid'?'selected':'' }}>Pago</option>
      <option value="cancelled" {{ request('status')==='cancelled'?'selected':'' }}>Cancelado</option>
    </select>
  </div>
  <div class="col-auto">
    <button class="btn btn-outline-secondary"><i class="bi bi-search"></i> Filtrar</button>
  </div>
</form>

<div class="table-responsive">
  <table class="table table-sm align-middle table-hover">
    <thead class="table-light"><tr>
      <th>#</th><th>Data</th><th>Cliente</th><th>Status</th><th class="text-end">Total</th><th></th>
    </tr></thead>
    <tbody>
      @foreach($orders as $o)
        <tr>
          <td>{{ $o->number }}</td>
          <td>{{ $o->created_at->format('d/m/Y H:i') }}</td>
          <td>{{ $o->customer_name }}</td>
          <td>
            @if($o->status==='paid')<span class="badge bg-success">Pago</span>
            @elseif($o->status==='cancelled')<span class="badge bg-danger">Cancelado</span>
            @else<span class="badge bg-secondary">{{ $o->status }}</span>
            @endif
          </td>
          <td class="text-end">R$ {{ number_format($o->total,2,',','.') }}</td>
          <td class="text-end">
            <a href="{{ route('orders.show',$o) }}" class="btn btn-sm btn-outline-primary">Ver</a>
            @if($o->status==='paid')
              <form method="POST" action="{{ route('orders.cancel',$o) }}" class="d-inline">@csrf
                <button class="btn btn-sm btn-outline-danger">Cancelar</button>
              </form>
            @endif
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>

{{ $orders->links() }}

@endsection
