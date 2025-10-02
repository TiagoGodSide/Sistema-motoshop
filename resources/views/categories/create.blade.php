@extends('layouts.app')
@section('title','Nova Categoria')

@section('content')
<h4 class="mb-3"><i class="bi bi-tag"></i> Nova Categoria</h4>

@include('partials.flash')

<form method="POST" action="{{ route('categories.store') }}" class="row g-3" style="max-width:520px">
  @csrf
  <div class="col-12">
    <label class="form-label">Nome *</label>
    <input name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  <div class="col-12 d-flex gap-2">
    <a href="{{ route('categories.index') }}" class="btn btn-light">Cancelar</a>
    <button class="btn btn-dark">Salvar</button>
  </div>
</form>
@endsection
