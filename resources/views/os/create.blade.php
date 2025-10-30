@extends('layouts.app')
@section('title','Nova OS')
@section('content')
<h4>Nova OS</h4>
<form method="POST" action="{{ route('os.store') }}" class="row g-3">
  @csrf
  <div class="col-md-4"><label class="form-label">Veículo</label><input name="vehicle" class="form-control" require></div>
  <div class="col-md-2"><label class="form-label">Placa</label><input name="plate" class="form-control" require></div>
  <div class="col-md-3"><label class="form-label">Previsão</label><input type="date" name="due_date" class="form-control" require></div>
  <div class="col-12"><label class="form-label">Observações</label><textarea name="notes" class="form-control" rows="3" require></textarea></div>
  <div class="col-12"><button class="btn btn-dark">Criar OS</button></div>
</form>
@endsection
