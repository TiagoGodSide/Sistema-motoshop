@extends('layouts.app')
@section('title','Importar produtos')

@section('content')
<h4><i class="bi bi-upload"></i> Importar produtos (CSV)</h4>

<p class="text-muted small">
  Baixe o <a href="{{ route('products.template.csv') }}">modelo CSV</a>.
  Aceita delimitador <code>;</code> ou <code>,</code>. Cabeçalho obrigatório.
</p>

@include('partials.flash')

@if($errors->any())
  <div class="alert alert-warning">
    <div class="fw-bold">Ocorreram avisos durante a importação:</div>
    <ul class="mb-0">
      @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
    </ul>
  </div>
@endif

<form method="POST" action="{{ route('products.import.process') }}" enctype="multipart/form-data" class="row g-3">
  @csrf
  <div class="col-md-6">
    <label class="form-label">Arquivo CSV</label>
    <input type="file" name="file" class="form-control" required>
  </div>
  <div class="col-md-6">
    <label class="form-label">Modo</label>
    <select name="mode" class="form-select">
      <option value="upsert">Inserir e atualizar (SKU/EAN/Código interno)</option>
      <option value="insert">Apenas inserir</option>
    </select>
  </div>
  <div class="col-12">
    <button class="btn btn-dark"><i class="bi bi-check2-circle"></i> Importar</button>
    <a href="{{ route('products.export.csv') }}" class="btn btn-outline-secondary">
      <i class="bi bi-download"></i> Exportar todos
    </a>
    <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">Voltar</a>
  </div>
</form>

<hr>
<div class="small text-muted">
  <strong>Colunas:</strong> name, sku, ean, internal_barcode, price, cost_price, stock, min_stock, unit, category, is_active(0/1).
  Valores monetários podem usar vírgula (<code>49,90</code>).
</div>
@endsection
