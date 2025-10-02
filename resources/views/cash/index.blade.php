@extends('layouts.app')
@section('title','Caixa')

@section('content')
<h4><i class="bi bi-cash-coin"></i> Caixa</h4>
@include('partials.flash')

@if(!$open)
  <form class="row g-2 mb-3" method="POST" action="{{ route('cash.open') }}">@csrf
    <div class="col-auto">
      <label class="form-label">Fundo de troco</label>
      <input type="number" step="0.01" name="opening_amount" class="form-control" value="0">
    </div>
    <div class="col-auto">
      <button class="btn btn-dark mt-4">Abrir caixa</button>
    </div>
  </form>
@else
  <div class="alert alert-success">Caixa aberto em {{ $open->opened_at->format('d/m/Y H:i') }} | Fundo R$ {{ number_format($open->opening_amount,2,',','.') }}</div>

  <form class="row g-2 mb-3" method="POST" action="{{ route('cash.movement') }}">@csrf
    <div class="col-auto">
      <label class="form-label">Tipo</label>
      <select name="type" class="form-select">
        <option value="IN">Suprimento</option>
        <option value="OUT">Sangria</option>
      </select>
    </div>
    <div class="col-auto">
      <label class="form-label">Valor</label>
      <input name="amount" type="number" step="0.01" class="form-control" required>
    </div>
    <div class="col-auto">
      <label class="form-label">Descrição</label>
      <input name="reason" class="form-control" placeholder="Motivo">
    </div>
    <div class="col-auto">
      <label class="form-label">Pagamento</label>
      <input name="payment_method" class="form-control" placeholder="dinheiro/pix/cartao">
    </div>
    <div class="col-auto">
      <button class="btn btn-outline-secondary mt-4">Lançar</button>
    </div>
  </form>

  <form method="POST" action="{{ route('cash.close') }}" class="mb-3">@csrf
    <button class="btn btn-danger">Fechar caixa</button>
  </form>
@endif

<h6>Últimos caixas</h6>
<div class="table-responsive">
  <table class="table table-sm">
    <thead class="table-light"><tr><th>#</th><th>Status</th><th>Abertura</th><th>Fechamento</th><th>Fundo</th><th>Fechamento (calc)</th></tr></thead>
    <tbody>
      @foreach($history as $c)
        <tr>
          <td>{{ $c->id }}</td>
          <td>{{ $c->status }}</td>
          <td>{{ $c->opened_at }}</td>
          <td>{{ $c->closed_at ?? '—' }}</td>
          <td>R$ {{ number_format($c->opening_amount,2,',','.') }}</td>
          <td>R$ {{ number_format($c->closing_amount ?? 0,2,',','.') }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection