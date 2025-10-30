@extends('layouts.app')

@section('title', 'Fechamento do caixa')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-cash-stack"></i> Fechamento do caixa</h4>
  <div class="btn-group">
    <a href="{{ route('cash.close.print') }}" target="_blank" class="btn btn-outline-secondary">
      <i class="bi bi-printer"></i> Imprimir
    </a>
  </div>
</div>
<div class="d-flex gap-2 mb-3">
  <a href="{{ route('cash.close.export') }}" class="btn btn-outline-secondary">
    <i class="bi bi-filetype-csv"></i> Exportar CSV
  </a>
  <a href="{{ route('cash.close.print') }}" target="_blank" class="btn btn-dark">
    <i class="bi bi-printer"></i> Imprimir fechamento
  </a>
</div>
@if($reg->open_note)
  <div class="alert alert-secondary"><strong>Obs. abertura:</strong> {{ $reg->open_note }}</div>
@endif
@if($reg->close_note)
  <div class="alert alert-secondary"><strong>Obs. fechamento:</strong> {{ $reg->close_note }}</div>
@endif


@if(session('ok'))
  <div class="alert alert-success">{{ session('ok') }}</div>
@endif
@if($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
  </div>
@endif

<div class="row g-3">
  <div class="col-md-6">
    <div class="card">
      <div class="card-header">Resumo</div>
      <div class="card-body">
        <div class="mb-2">Aberto em: <strong>{{ optional($reg->opened_at)->format('d/m/Y H:i') }}</strong></div>
        <div class="mb-2">Aberto por: <strong>{{ optional($reg->openedBy)->name ?? '—' }}</strong></div>
        <div class="mb-2">Valor de abertura: <strong>R$ {{ number_format((float)($reg->opening_amount ?? 0),2,',','.') }}</strong></div>
        <hr>
        <div class="mb-2">Entradas (mov.): <strong>R$ {{ number_format((float)($sumIn ?? 0),2,',','.') }}</strong></div>
        <div class="mb-2">Saídas (mov.): <strong>R$ {{ number_format((float)($sumOut ?? 0),2,',','.') }}</strong></div>
        <div class="mb-2">Saldo esperado em dinheiro*:
          @php
            $dinheiro = (float)($totalsByMethod['dinheiro'] ?? 0);
            $esperado = (float)($reg->opening_amount ?? 0) + $dinheiro + (float)($sumIn ?? 0) - (float)($sumOut ?? 0);
          @endphp
          <strong>R$ {{ number_format($esperado,2,',','.') }}</strong>
        </div>
        <small class="text-muted">*Cálculo simples; ajuste conforme sua regra (pedidos por método, etc.).</small>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header">Totais por forma de pagamento (pedidos)</div>
      <div class="card-body">
        <div class="d-flex flex-column gap-1">
          <div class="d-flex justify-content-between">
            <span>Dinheiro</span>
            <strong>R$ {{ number_format((float)($totalsByMethod['dinheiro'] ?? 0),2,',','.') }}</strong>
          </div>
          <div class="d-flex justify-content-between">
            <span>PIX</span>
            <strong>R$ {{ number_format((float)($totalsByMethod['pix'] ?? 0),2,',','.') }}</strong>
          </div>
          <div class="d-flex justify-content-between">
            <span>Cartão</span>
            <strong>R$ {{ number_format((float)($totalsByMethod['cartao'] ?? 0),2,',','.') }}</strong>
          </div>
          <div class="d-flex justify-content-between">
            <span>Outros</span>
            <strong>R$ {{ number_format((float)($totalsByMethod['outro'] ?? 0),2,',','.') }}</strong>
          </div>
        </div>
        <hr>
        <form method="POST" action="{{ route('cash.close') }}" class="mt-2">
          @csrf
          <div class="mb-2">
            <label class="form-label">Observações do fechamento</label>
            <textarea name="close_notes" class="form-control" rows="3" placeholder="Diferenças, sangrias, conferência..."></textarea>
          </div>
          <button class="btn btn-dark">
            <i class="bi bi-check2-circle"></i> Fechar caixa
          </button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-header">Movimentações</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th>Data</th><th>Tipo</th><th>Valor</th><th>Motivo</th><th>Anexo</th><th>Usuário</th>
              </tr>
            </thead>
            <tbody>
              @forelse($movements as $m)
                <tr>
                  <td>{{ optional($m->created_at)->format('d/m/Y H:i') }}</td>
                  <td>{{ strtoupper($m->type ?? '-') }}</td>
                  <td>R$ {{ number_format((float)($m->amount ?? 0),2,',','.') }}</td>
                  <td>{{ $m->reason ?? '—' }}</td>
                  <td>
                    @if(!empty($m->attachment))
                      <a href="{{ Storage::url($m->attachment) }}" target="_blank">ver</a>
                    @else — @endif
                  </td>
                  <td>{{ optional($m->user)->name ?? '—' }}</td>
                </tr>
              @empty
                <tr><td colspan="6" class="text-center text-muted py-3">Sem movimentações.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
