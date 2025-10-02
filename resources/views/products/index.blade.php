@extends('layouts.app')
@section('title','Produtos')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-box-seam"></i> Produtos</h4>
  <a href="{{ route('products.create') }}" class="btn btn-dark"><i class="bi bi-plus-lg"></i> Novo</a>
</div>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link {{ $tab==='ativos'?'active':'' }}" href="{{ route('products.index',['tab'=>'ativos']) }}">Ativos</a></li>
  <li class="nav-item"><a class="nav-link {{ $tab==='desativados'?'active':'' }}" href="{{ route('products.index',['tab'=>'desativados']) }}">Desativados</a></li>
  <li class="nav-item"><a class="nav-link {{ $tab==='sem-estoque'?'active':'' }}" href="{{ route('products.index',['tab'=>'sem-estoque']) }}">Sem Estoque</a></li>
</ul>

<form method="get" class="input-group mb-3" style="max-width:400px">
  <input name="q" class="form-control" value="{{ $q ?? '' }}" placeholder="Buscar por nome, SKU, código">
  <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
</form>

<div class="table-responsive">
  <table class="table table-hover table-sm align-middle">
    <thead class="table-light"><tr>
      <th>Produto</th><th>SKU</th><th>Preço</th><th>Estoque</th><th>Cód. Interno</th><th></th>
    </tr></thead>
    <tbody>
      @foreach($products as $p)
        <tr>
          <td>{{ $p->name }}</td>
          <td>{{ $p->sku }}</td>
          <td>R$ {{ number_format($p->price,2,',','.') }}</td>
          <td>{{ $p->stock }}</td>
          <td><span class="kbd">{{ $p->internal_barcode }}</span></td>
          <td class="text-end">
            <a href="{{ route('products.edit',$p) }}" class="btn btn-sm btn-outline-secondary">Editar</a>
            @if($p->is_active)
              <form action="{{ route('products.destroy',$p) }}" method="POST" class="d-inline">@csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger">Desativar</button>
              </form>
            @else
              <form action="{{ route('products.activate',$p) }}" method="POST" class="d-inline">@csrf
                <button class="btn btn-sm btn-outline-success">Ativar</button>
              </form>
            @endif
            <a href="{{ route('products.label',$p) }}" target="_blank" class="btn btn-sm btn-outline-primary">Etiqueta</a>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>

{{ $products->links() }}

@endsection
