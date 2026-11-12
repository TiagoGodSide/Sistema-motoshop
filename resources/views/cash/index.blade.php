@extends('layouts.app')
@section('title','Caixa')

@section('content')
<h4><i class="bi bi-cash-coin"></i> Caixa</h4>
@include('partials.flash')

@if($ambiguous)
  <div class="alert alert-warning" role="alert">
    Existem múltiplos caixas abertos ({{ $openRegisters->pluck('id')->join(', ') }}).
    As operações financeiras dependem da reconciliação.
  </div>
@elseif(!$open)
  <form class="row g-2 mb-3" method="POST" action="{{ route('cash.open') }}">@csrf
    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $openingKey) }}">
    <div class="col-auto">
      <label class="form-label">Fundo de troco</label>
      <input type="number" step="0.01" min="0" max="99999999.99" name="opening_amount" class="form-control" value="{{ old('opening_amount', 0) }}">
    </div>
    <div class="col-auto">
      <button class="btn btn-dark mt-4">Abrir caixa</button>
    </div>
    <div class="mb-2">
      <label class="form-label">Observação da abertura (opcional)</label>
      <textarea name="open_notes" class="form-control" rows="2" maxlength="2000" placeholder="Ex.: Troca de turno, fundo de caixa...">{{ old('open_notes') }}</textarea>
    </div>

  </form>
@else
  <div class="alert alert-success">Caixa aberto em {{ $open->opened_at->format('d/m/Y H:i') }} | Fundo R$ {{ number_format($open->opening_amount,2,',','.') }}</div>

  <form class="row g-2 mb-3" method="POST" action="{{ route('cash.movement') }}">@csrf
    @include('cash._operation-fields', ['action' => 'movement', 'register' => $open, 'key' => $movementKey])
    <div class="col-auto">
      <label class="form-label">Tipo</label>
      <select name="type" class="form-select">
        <option value="IN" @selected(old('type') === 'IN')>Suprimento</option>
        <option value="OUT" @selected(old('type') === 'OUT')>Sangria</option>
      </select>
    </div>
    <div class="col-auto">
      <label class="form-label">Valor</label>
      <input name="amount" type="number" step="0.01" min="0.01" max="99999999.99" value="{{ old('amount') }}" class="form-control" required>
    </div>
    <div class="col-auto">
      <label class="form-label">Descrição</label>
      <input name="reason" value="{{ old('reason') }}" maxlength="120" class="form-control" placeholder="Motivo">
    </div>
    <div class="col-auto">
      <label class="form-label">Pagamento</label>
      <input name="payment_method" value="{{ old('payment_method') }}" maxlength="40" class="form-control" placeholder="dinheiro/pix/cartao">
    </div>
    <div class="col-auto">
      <button class="btn btn-outline-secondary mt-4">Lançar</button>
    </div>
  </form>

  <form method="POST" action="{{ route('cash.close') }}" class="mb-3">@csrf
    @include('cash._operation-fields', ['action' => 'close', 'register' => $open, 'key' => $closingKey])
    <button class="btn btn-danger">Fechar caixa</button>
    <div class="mb-2">
      <label class="form-label">Observação do fechamento (opcional)</label>
      <textarea name="close_notes" maxlength="2000" class="form-control" rows="2" placeholder="Ex.: Conferido com o gerente, divergência...">{{ old('close_notes') }}</textarea>
    </div>
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
