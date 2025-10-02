@extends('layouts.app')
@section('title','Novo Produto')

@section('content')
<h4 class="mb-3"><i class="bi bi-plus-lg"></i> Novo Produto</h4>

@include('partials.flash')

<form method="POST" action="{{ route('products.store') }}" class="row g-3">
  @csrf

  <div class="col-md-6">
    <label class="form-label">Nome *</label>
    <input name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  <div class="col-md-3">
    <label class="form-label">SKU *</label>
    <input name="sku" class="form-control @error('sku') is-invalid @enderror" value="{{ old('sku') }}" required>
    @error('sku') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  <div class="col-md-3">
    <label class="form-label">EAN (externo)</label>
    <input name="ean" class="form-control @error('ean') is-invalid @enderror" value="{{ old('ean') }}" placeholder="opcional">
    @error('ean') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  <div class="col-md-3">
    <label class="form-label">Preço (R$) *</label>
    <input type="number" step="0.01" min="0" name="price" class="form-control @error('price') is-invalid @enderror" value="{{ old('price',0) }}" required>
    @error('price') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  <div class="col-md-3">
    <label class="form-label">Preço de custo (R$)</label>
    <input type="number" step="0.01" min="0" name="cost_price" class="form-control @error('cost_price') is-invalid @enderror" value="{{ old('cost_price') }}">
    @error('cost_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  <div class="col-md-3">
    <label class="form-label">Estoque inicial *</label>
    <input type="number" min="0" name="stock" class="form-control @error('stock') is-invalid @enderror" value="{{ old('stock',0) }}" required>
    @error('stock') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  <div class="col-md-3">
    <label class="form-label">Estoque mínimo</label>
    <input type="number" min="0" name="min_stock" class="form-control @error('min_stock') is-invalid @enderror" value="{{ old('min_stock',0) }}">
    @error('min_stock') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  <div class="col-md-4">
    <label class="form-label">Categoria</label>
    @php($categories = \App\Models\Category::orderBy('name')->get())
    <select name="category_id" class="form-select @error('category_id') is-invalid @enderror">
      <option value="">— Selecione —</option>
      @foreach($categories as $c)
        <option value="{{ $c->id }}" @selected(old('category_id')==$c->id)>{{ $c->name }}</option>
      @endforeach
    </select>
    @error('category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  <div class="col-md-2">
    <label class="form-label">Unidade</label>
    <input name="unit" class="form-control @error('unit') is-invalid @enderror" value="{{ old('unit','UN') }}">
    @error('unit') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  <div class="col-12">
    <div class="alert alert-info small mb-0">
      <i class="bi bi-upc-scan"></i> O <strong>código de barras interno</strong> será gerado automaticamente ao salvar (ex.: C128).  
      Depois você poderá <strong>imprimir a etiqueta</strong> na tela de edição.
    </div>
  </div>

  <div class="col-12 d-flex gap-2">
    <a href="{{ route('products.index') }}" class="btn btn-light">Cancelar</a>
    <button class="btn btn-dark">Salvar</button>
  </div>
</form>
@endsection
