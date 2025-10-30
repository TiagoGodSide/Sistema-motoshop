@extends('layouts.app')
@section('title','Histórico de estoque')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-clock-history"></i> Histórico — {{ $product->name }}</h4>
  <a href="{{ route('products.edit', $product) }}" class="btn btn-outline-secondary">Voltar</a>
</div>

<div class="table-responsive">
  <table class="table table-sm table-hover align-middle">
    <thead class="table-light">
      <tr>
        <th>Data</th>
        <th>Tipo</th>
        <th class="text-end">Qtd</th>
        <th class="text-end">Preço unit.</th>
        <th>Motivo / Comprovante</th>
        <th>Usuário</th>
      </tr>
    </thead>
    <tbody>
      @forelse($movements as $m)
        <tr>
          <td>{{ $m->created_at->format('d/m/Y H:i') }}</td>
          <td>
            <span class="badge text-bg-{{ $m->type==='in' ? 'success' : 'danger' }}">
              {{ $m->type==='in' ? 'Entrada (+)' : 'Saída (−)' }}
            </span>
          </td>
          <td class="text-end">{{ $m->type==='out' ? '-' : '+' }}{{ (int)$m->qty }}</td>
          <td class="text-end">R$ {{ number_format((float)($m->unit_price ?? 0), 2, ',', '.') }}</td>

          <td class="text-truncate" style="max-width:260px">
            <div>{{ $m->reason_label ?? ($m->reason ?? '—') }}</div>
            @if(!empty($m->attachment_path))
              <a href="{{ asset('storage/'.$m->attachment_path) }}" target="_blank" class="small">
                Ver comprovante
              </a>
            @endif
          </td>

          <td>{{ $m->user->name ?? '—' }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="6" class="text-center text-muted">Sem movimentos.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>

{{ $movements->links() }}
@endsection
