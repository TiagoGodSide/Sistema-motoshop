@extends('layouts.app')
@section('title','Editar produto')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-pencil-square"></i> Editar — {{ $product->name }}</h4>
  <div class="btn-group">
    <a href="{{ route('products.history', $product) }}" class="btn btn-outline-secondary">
      <i class="bi bi-clock-history"></i> Histórico
    </a>
    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#adjustStockModal">
      <i class="bi bi-boxes"></i> Ajustar estoque
    </button>
    <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">Voltar</a>
  </div>
</div>

{{-- flash/erros padrão (opcional) --}}
@if(session('ok'))
  <div class="alert alert-success">{{ session('ok') }}</div>
@endif
@if($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
    </ul>
  </div>
@endif

<form method="POST" action="{{ route('products.update',$product) }}" class="row g-3">
  @csrf @method('PUT')

  <div class="col-md-6">
    <label class="form-label">Nome</label>
    <input name="name" class="form-control" value="{{ old('name',$product->name) }}" required>
  </div>

  <div class="col-md-3">
    <label class="form-label">SKU</label>
    <input name="sku" class="form-control" value="{{ old('sku',$product->sku) }}">
  </div>
  <div class="col-md-3">
    <label class="form-label">EAN</label>
    <input name="ean" class="form-control" value="{{ old('ean',$product->ean) }}">
  </div>

  <div class="col-md-3">
    <label class="form-label">Cód. interno</label>
    <input name="internal_barcode" class="form-control" value="{{ old('internal_barcode',$product->internal_barcode) }}">
  </div>

  <div class="col-md-3">
    <label class="form-label">Preço</label>
    <input name="price" type="number" step="0.01" min="0" class="form-control"
           value="{{ old('price',$product->price) }}" required>
  </div>

  <div class="col-md-3">
    <label class="form-label">Estoque</label>
    <input name="stock" type="number" min="0" class="form-control" value="{{ old('stock',$product->stock) }}">
  </div>

  <div class="col-md-3">
    <label class="form-label">Estoque mínimo</label>
    <input name="min_stock" type="number" min="0" class="form-control" value="{{ old('min_stock',$product->min_stock) }}">
  </div>

  <div class="col-md-3">
    <label class="form-label">Unidade</label>
    <input name="unit" class="form-control" value="{{ old('unit',$product->unit) }}" placeholder="ex.: UN, CX, LT">
  </div>

  <div class="col-md-3">
    <label class="form-label">Categoria</label>
    <select name="category_id" class="form-select">
      <option value="">—</option>
      @foreach($categories as $c)
        <option value="{{ $c->id }}" @selected(old('category_id',$product->category_id)==$c->id)>{{ $c->name }}</option>
      @endforeach
    </select>
  </div>

  <div class="col-12 form-check mt-2">
    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="chkActive"
           @checked(old('is_active',$product->is_active))>
    <label class="form-check-label" for="chkActive">Ativo</label>
  </div>

  <div class="col-12">
    <button class="btn btn-dark">Salvar</button>
    <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">Cancelar</a>
  </div>
</form>

{{-- Modal: Ajustar estoque (fora do form) --}}
<div class="modal fade" id="adjustStockModal" tabindex="-1" aria-labelledby="adjustStockLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" action="{{ route('products.adjust', $product) }}" class="modal-content">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title" id="adjustStockLabel">Ajustar estoque</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2 text-muted">
          Estoque atual: <span class="fw-bold">{{ (int)$product->stock }}</span>
          @if(!is_null($product->min_stock))
            <span class="ms-2">| Mínimo: {{ (int)$product->min_stock }}</span>
          @endif
        </div>

        <div class="row g-3">
        <div class="col-6">
          <label class="form-label">Tipo</label>
          <select name="type" class="form-select" required>
            <option value="in">Entrada (+)</option>
            <option value="out">Saída (−)</option>
          </select>
        </div>
        <div class="col-6">
          <label class="form-label">Quantidade</label>
          <input type="number" name="qty" class="form-control" min="1" step="1" required>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Motivo</label>
          <select name="reason_code" id="reason_code" class="form-select">
            <option value="">— selecione —</option>
            <option value="inventory">Inventário/Acerto</option>
            <option value="loss">Perda/Quebra</option>
            <option value="exchange">Troca/Devolução</option>
            <option value="gift">Brinde</option>
            <option value="other">Outro</option>
          </select>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Complemento</label>
          <input type="text" name="reason" id="reason" class="form-control" maxlength="180" placeholder="ex.: Ajuste de inventário">
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Preço unit. (opcional)</label>
          <input type="number" name="unit_price" class="form-control" min="0" step="0.01" placeholder="ex.: 12,50">
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Comprovante (jpg/png/pdf, até 4MB)</label>
          <input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
        </div>
      </div>

      @push('scripts')
      <script>
      document.addEventListener('DOMContentLoaded', () => {
        const sel = document.getElementById('reason_code');
        const txt = document.getElementById('reason');
        if(!sel || !txt) return;
        function updatePlaceholder(){
          const map = {
            'inventory':'Ex.: Inventário 2025/10',
            'loss':'Ex.: Quebra na bancada',
            'exchange':'Ex.: Devolução OS #123',
            'gift':'Ex.: Brinde ao cliente',
            'other':'Descreva o motivo'
          };
          txt.placeholder = map[sel.value] || 'ex.: Ajuste de inventário';
        }
        sel.addEventListener('change', updatePlaceholder);
        updatePlaceholder();
      });
      </script>
      @endpush

@endsection
