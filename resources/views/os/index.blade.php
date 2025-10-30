@extends('layouts.app')
@section('title','Ordens de Serviço')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-wrench-adjustable"></i> Ordens de Serviço</h4>
  <a href="{{ route('os.create') }}" class="btn btn-dark"><i class="bi bi-plus-lg"></i> Nova OS</a>
</div>

<form method="get" class="row g-2 mb-3">
  <div class="col-md-3">
    <select name="status" class="form-select">
      <option value="">Todos os status</option>
      @foreach(['opened'=>'Aberta','approved'=>'Aprovada','in_service'=>'Em serviço','ready'=>'Pronta','delivered'=>'Entregue','canceled'=>'Cancelada'] as $k=>$v)
        <option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-md-6">
    <input name="q" class="form-control" value="{{ $q ?? '' }}" placeholder="Nº OS, veículo ou placa">
  </div>
  <div class="col-md-3">
    <button class="btn btn-outline-secondary w-100">Filtrar</button>
  </div>
</form>

<div class="table-responsive">
<table class="table table-sm align-middle">
  <thead class="table-light">
    <tr><th>Nº</th><th>Cliente</th><th>Veículo/Placa</th><th>Status</th><th class="text-end">Total</th><th></th></tr>
  </thead>
  <tbody>
  @foreach($rows as $os)
    <tr>
      <td>{{ $os->number }}</td>
      <td>{{ optional($os->customer)->name ?? '—' }}</td>
      <td>{{ $os->vehicle }} {{ $os->plate ? '· '.$os->plate : '' }}</td>
      <td>{{ strtoupper($os->status) }}</td>
      <td class="text-end">R$ {{ number_format($os->total,2,',','.') }}</td>
      <td class="text-end">
        <a href="{{ route('os.edit',$os) }}" class="btn btn-sm btn-outline-secondary">Editar</a>
        @if($os->status==='approved')
          <form method="POST" action="{{ route('os.to.pos',$os) }}" class="d-inline">@csrf
            <button class="btn btn-sm btn-outline-primary">Enviar ao PDV</button>
          </form>
        @endif
      </td>
    </tr>
  @endforeach
  </tbody>
</table>
</div>

{{ $rows->links() }}
@endsection
