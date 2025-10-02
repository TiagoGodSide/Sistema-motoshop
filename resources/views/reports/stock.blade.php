@extends('layouts.app')
@section('title','Relatório de Estoque')

@section('content')
<h4 class="mb-3"><i class="bi bi-boxes"></i> Relatório de Estoque</h4>

<form method="get" class="row g-2 align-items-end mb-3">
  <div class="col-auto">
    <label class="form-label">Filtro</label>
    <select name="filter" class="form-select">
      <option value="" {{ request('filter')==''?'selected':'' }}>Todos</option>
      <option value="min" {{ request('filter')==='min'?'selected':'' }}>Abaixo do mínimo</option>
      <option value="zero" {{ request('filter')==='zero'?'selected':'' }}>Sem estoque (0)</option>
      <option value="neg" {{ request('filter')==='neg'?'selected':'' }}>Estoque negativo</option>
      <option value="inativos" {{ request('filter')==='inativos'?'selected':'' }}>Desativados</option>
    </select>
  </div>
  <div class="col-auto">
    <label class="form-label">Categoria</label>
    @php($cats = \App\Models\Category::orderBy('name')->get())
    <select name="category_id" class="form-select">
      <option value="">Todas</option>
      @foreach($cats as $c)
        <option value="{{ $c->id }}" @selected(request('category_id')==$c->id)>{{ $c->name }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-auto">
    <label class="form-label">Busca</label>
    <input name="q" class="form-control" value="{{ request('q') }}" placeholder="Nome/SKU/Código">
  </div>
  <div class="col-auto">
    <button class="btn btn-outline-secondary"><i class="bi bi-search"></i> Filtrar</button>
  </div>
  <div class="col-auto">
    <a class="btn btn-outline-primary" href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->query(),['export'=>'csv'])) }}">
      <i class="bi bi-download"></i> Exportar CSV
    </a>
  </div>
</form>

<div class="table-responsive">
  <table class="table table-sm table-hover align-middle">
    <thead class="table-light">
      <tr>
        <th>Produto</th>
        <th>SKU</th>
        <th>Categoria</th>
        <th class="text-end">Estoque</th>
        <th class="text-end">Mínimo</th>
        <th class="text-end">Preço</th>
        <th class="text-end">Valorizado</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      @forelse($items as $p)
        @php
          $warn = $p->stock <= 0 || $p->stock < $p->min_stock;
        @endphp
        <tr class="{{ $warn ? 'table-warning' : '' }}">
          <td>{{ $p->name }}</td>
          <td><span class="kbd">{{ $p->sku }}</span></td>
          <td>{{ optional($p->category)->name }}</td>
          <td class="text-end">{{ $p->stock }}</td>
          <td class="text-end">{{ $p->min_stock }}</td>
          <td class="text-end">R$ {{ number_format($p->price,2,',','.') }}</td>
          <td class="text-end">R$ {{ number_format($p->price * max(0,$p->stock),2,',','.') }}</td>
          <td>
            @if(!$p->is_active)<span class="badge bg-secondary">Desativado</span>
            @elseif($p->stock<=0)<span class="badge bg-danger">Sem estoque</span>
            @elseif($p->stock<$p->min_stock)<span class="badge bg-warning text-dark">Abaixo do mínimo</span>
            @else <span class="badge bg-success">OK</span>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="8" class="text-center text-muted">Nenhum item encontrado.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

{{ $items->links() }}

@endsection
