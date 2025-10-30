@extends('layouts.app')
@section('title',"Editar {$os->number}")
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4>{{ $os->number }}</h4>
  <a href="{{ route('os.index') }}" class="btn btn-outline-secondary">Voltar</a>
</div>

<form method="POST" action="{{ route('os.update',$os) }}" class="row g-3 mb-3">
  @csrf @method('PUT')
  <div class="col-md-4"><label class="form-label">Veículo</label>
    <input name="vehicle" class="form-control" value="{{ old('vehicle',$os->vehicle) }}">
  </div>
  <div class="col-md-2"><label class="form-label">Placa</label>
    <input name="plate" class="form-control" value="{{ old('plate',$os->plate) }}">
  </div>
  <div class="col-md-3"><label class="form-label">Previsão</label>
    <input type="date" name="due_date" class="form-control" value="{{ old('due_date',optional($os->due_date)->toDateString()) }}">
  </div>
  <div class="col-md-3">
    <label class="form-label">Status</label>
    <select name="status" class="form-select">
      @foreach(['opened'=>'Aberta','approved'=>'Aprovada','in_service'=>'Em serviço','ready'=>'Pronta','delivered'=>'Entregue','canceled'=>'Cancelada'] as $k=>$v)
        <option value="{{ $k }}" @selected($os->status===$k)>{{ $v }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-12"><label class="form-label">Observações</label>
    <textarea name="notes" class="form-control" rows="3">{{ old('notes',$os->notes) }}</textarea>
  </div>
  <div class="col-12">
    <button class="btn btn-dark">Salvar</button>
    @if($os->status==='approved')
      <form method="POST" action="{{ route('os.to.pos',$os) }}" class="d-inline">@csrf
        <button class="btn btn-outline-primary">Enviar ao PDV</button>
      </form>
    @endif
  </div>
</form>

<h5 class="mt-3">Itens</h5>
<form method="POST" action="{{ route('os.items.add',$os) }}" class="row g-2 align-items-end mb-2">
  @csrf
  <div class="col-md-2">
    <label class="form-label">Tipo</label>
    <select name="type" class="form-select" required>
      <option value="labor">Mão de obra</option>
      <option value="part">Peça</option>
    </select>
  </div>
  <div class="col-md-4">
    <label class="form-label">Descrição</label>
    <input name="description" class="form-control" required>
  </div>
  <div class="col-md-2">
    <label class="form-label">Qtd</label>
    <input type="number" name="qty" class="form-control" min="1" step="1" value="1" required>
  </div>
  <div class="col-md-2">
    <label class="form-label">Preço unit.</label>
    <input type="number" name="unit_price" class="form-control" min="0" step="0.01" required>
  </div>
  <div class="col-md-2">
    <button class="btn btn-outline-secondary w-100">Adicionar</button>
  </div>
</form>

<div class="table-responsive">
<table class="table table-sm align-middle">
  <thead class="table-light"><tr><th>Tipo</th><th>Descrição</th><th class="text-end">Qtd</th><th class="text-end">Unit.</th><th class="text-end">Desc</th><th class="text-end">Total</th><th></th></tr></thead>
  <tbody>
  @foreach($os->items as $it)
    <tr>
      <td>{{ $it->type==='labor' ? 'M.O.' : 'Peça' }}</td>
      <td>{{ $it->description }}</td>
      <td class="text-end">{{ $it->qty }}</td>
      <td class="text-end">R$ {{ number_format($it->unit_price,2,',','.') }}</td>
      <td class="text-end">R$ {{ number_format($it->discount,2,',','.') }}</td>
      <td class="text-end fw-bold">R$ {{ number_format($it->total,2,',','.') }}</td>
      <td class="text-end">
        <form method="POST" action="{{ route('os.items.remove',[$os,$it]) }}" onsubmit="return confirm('Remover item?')" class="d-inline">
          @csrf @method('DELETE')
          <button class="btn btn-sm btn-outline-danger">Remover</button>
        </form>
      </td>
    </tr>
  @endforeach
  </tbody>
  <tfoot class="table-light">
    <tr><th colspan="5" class="text-end">Peças</th><th class="text-end">R$ {{ number_format($os->parts_total,2,',','.') }}</th><th></th></tr>
    <tr><th colspan="5" class="text-end">Mão de obra</th><th class="text-end">R$ {{ number_format($os->labor_total,2,',','.') }}</th><th></th></tr>
    <tr><th colspan="5" class="text-end">Desconto</th><th class="text-end">- R$ {{ number_format($os->discount,2,',','.') }}</th><th></th></tr>
    <tr><th colspan="5" class="text-end">Total</th><th class="text-end">R$ {{ number_format($os->total,2,',','.') }}</th><th></th></tr>
  </tfoot>
</table>
</div>
@endsection
