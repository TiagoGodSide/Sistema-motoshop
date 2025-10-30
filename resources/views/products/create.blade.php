@extends('layouts.app')
@section('title','Novo produto')

@section('content')
<h4>Novo produto</h4>

<form method="POST" action="{{ route('products.store') }}" class="row g-3">
  @csrf

  <div class="col-md-6">
    <label class="form-label">Nome</label>
    <input name="name" class="form-control" value="{{ old('name') }}" required>
  </div>

  <div class="col-md-3">
    <label class="form-label">SKU</label>
    <input name="sku" class="form-control" value="{{ old('sku') }}">
  </div>
  <div class="col-md-3">
    <label class="form-label">EAN</label>
    <input name="ean" class="form-control" value="{{ old('ean') }}">
  </div>

  <div class="col-md-3">
    <label class="form-label">Cód. interno</label>
    <input name="internal_barcode" class="form-control" value="{{ old('internal_barcode') }}"
           placeholder="(gerado se vazio)">
  </div>

  <div class="col-md-3">
    <label class="form-label">Preço</label>
    <input name="price" type="number" step="0.01" min="0" class="form-control"
           value="{{ old('price') }}" required>
  </div>
  <div class="col-md-3">
    <label class="form-label">Estoque</label>
    <input name="stock" type="number" min="0" class="form-control" value="{{ old('stock',0) }}">
  </div>

  <div class="col-md-3">
    <label class="form-label">Categoria</label>
    <select name="category_id" class="form-select">
      <option value="">—</option>
      @foreach($categories as $c)
        <option value="{{ $c->id }}" @selected(old('category_id')==$c->id)>{{ $c->name }}</option>
      @endforeach
    </select>
  </div>

  <div class="col-12 form-check mt-2">
    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="chkActive"
           @checked(old('is_active', true))>
    <label class="form-check-label" for="chkActive">Ativo</label>
  </div>

  <div class="col-12">
    <button class="btn btn-dark">Salvar</button>
    <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">Cancelar</a>
  </div>
</form>
@endsection
