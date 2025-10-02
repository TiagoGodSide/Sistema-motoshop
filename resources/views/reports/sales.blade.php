@extends('layouts.app')
@section('title','Relatório de Vendas')

@push('styles')
<style>
  .stat { border:1px solid #e9ecef; border-radius:.75rem; padding:1rem }
</style>
@endpush

@section('content')
<h4 class="mb-3"><i class="bi bi-graph-up"></i> Relatório de Vendas</h4>

<form method="get" class="row g-2 align-items-end mb-3">
  <div class="col-auto">
    <label class="form-label">De</label>
    <input type="date" name="from" class="form-control" value="{{ $from?->format('Y-m-d') }}">
  </div>
  <div class="col-auto">
    <label class="form-label">Até</label>
    <input type="date" name="to" class="form-control" value="{{ $to?->format('Y-m-d') }}">
  </div>
  <div class="col-auto">
    <label class="form-label">Agrupar por</label>
    <select name="group" class="form-select">
      <option value="dia" {{ $group==='dia'?'selected':'' }}>Dia</option>
      <option value="mes" {{ $group==='mes'?'selected':'' }}>Mês</option>
      <option value="vendedor" {{ $group==='vendedor'?'selected':'' }}>Vendedor</option>
      <option value="pagamento" {{ $group==='pagamento'?'selected':'' }}>Forma de pagamento</option>
    </select>
  </div>
  <div class="col-auto">
    <button class="btn btn-outline-secondary"><i class="bi bi-search"></i> Gerar</button>
  </div>
  <div class="col-auto">
    <a href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->query(),['export'=>'csv'])) }}" class="btn btn-outline-primary">
      <i class="bi bi-download"></i> Exportar CSV
    </a>
  </div>
</form>

<div class="row g-3 mb-3">
  <div class="col-md-3">
    <div class="stat">
      <div class="text-muted small">Período</div>
      <div class="fw-bold">
        {{ $from?->format('d/m/Y') ?? '—' }} — {{ $to?->format('d/m/Y') ?? '—' }}
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="stat">
      <div class="text-muted small">Agrupamento</div>
      <div class="fw-bold text-capitalize">{{ $group }}</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="stat">
      <div class="text-muted small">Registros</div>
      <div class="fw-bold">{{ number_format($rows->count(),0,',','.') }}</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="stat">
      <div class="text-muted small">Total no período</div>
      <div class="fw-bold">R$ {{ number_format($sumTotal,2,',','.') }}</div>
    </div>
  </div>
</div>

<div class="soft-card bg-white p-3 mb-3">
  <canvas id="chart" height="120"></canvas>
</div>

<div class="table-responsive">
  <table class="table table-sm align-middle table-hover">
    <thead class="table-light">
      <tr>
        <th>{{ $group==='dia'?'Dia':($group==='mes'?'Mês':($group==='vendedor'?'Vendedor':'Forma de pagamento')) }}</th>
        <th class="text-end">Total</th>
        <th class="text-end">Qtd. Pedidos</th>
      </tr>
    </thead>
    <tbody>
      @foreach($rows as $r)
        <tr>
          <td>{{ $r->label }}</td>
          <td class="text-end">R$ {{ number_format($r->total,2,',','.') }}</td>
          <td class="text-end">{{ number_format($r->count ?? 0,0,',','.') }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const labels = @json($rows->pluck('label'));
const data   = @json($rows->pluck('total'));
const ctx = document.getElementById('chart').getContext('2d');

new Chart(ctx, {
  type: 'bar',
  data: {
    labels,
    datasets: [{
      label: 'Faturamento',
      data
    }]
  },
  options: {
    responsive: true,
    scales: {
      y: {
        ticks: {
          callback: (v)=> v.toLocaleString('pt-BR', { style:'currency', currency:'BRL' })
        }
      }
    },
    plugins: {
      tooltip: {
        callbacks: {
          label: (ctx) => ' ' + Number(ctx.parsed.y).toLocaleString('pt-BR',{style:'currency',currency:'BRL'})
        }
      }
    }
  }
});
</script>
@endpush
