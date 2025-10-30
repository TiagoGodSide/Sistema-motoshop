@extends('layouts.app')
@section('title','Fechamento do Caixa')

@section('content')
<h4><i class="bi bi-cash-coin"></i> Fechamento — Caixa #{{ $reg->id }}</h4>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card card-body">
      <div class="d-flex justify-content-between">
        <span>Aberto em</span><strong>{{ $reg->opened_at->format('d/m/Y H:i') }}</strong>
      </div>
      <div class="d-flex justify-content-between">
        <span>Fundo de troco</span><strong>R$ {{ number_format($reg->opening_amount,2,',','.') }}</strong>
      </div>
      <div class="d-flex justify-content-between">
        <span>Sangrias</span><strong>R$ {{ number_format($sangrias,2,',','.') }}</strong>
      </div>
      <hr>
      <div class="d-flex justify-content-between">
        <span>Previsão fechamento</span><strong>R$ {{ number_format($prevClosing,2,',','.') }}</strong>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card card-body">
      <h6 class="mb-2">Resumo por método</h6>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead class="table-light"><tr><th>Método</th><th class="text-end">Entradas</th><th class="text-end">Saídas</th></tr></thead>
          <tbody>
            @foreach($byMethod as $r)
            <tr>
              <td>{{ $r->payment_method ? ucfirst($r->payment_method) : '—' }}</td>
              <td class="text-end">R$ {{ number_format($r->entradas,2,',','.') }}</td>
              <td class="text-end">R$ {{ number_format($r->saidas,2,',','.') }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <form method="POST" action="{{ route('cash.close') }}" class="mt-2">@csrf
        <button class="btn btn-danger"><i class="bi bi-check2-circle"></i> Fechar caixa</button>
        <a href="{{ route('cash.index') }}" class="btn btn-outline-secondary">Voltar</a>
      </form>
    </div>
  </div>
</div>
<form method="POST" action="{{ route('cash.close') }}" class="mt-2">@csrf
  <a href="{{ route('cash.close.export') }}" class="btn btn-outline-secondary">
    <i class="bi bi-download"></i> Exportar CSV
  </a>
  <button class="btn btn-danger"><i class="bi bi-check2-circle"></i> Fechar caixa</button>
  <a href="{{ route('cash.index') }}" class="btn btn-outline-secondary">Voltar</a>
</form>
@endsection
