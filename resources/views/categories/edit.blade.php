@extends('layouts.app')
@section('title','Editar Categoria')

@section('content')
<h4 class="mb-3"><i class="bi bi-pencil-square"></i> Editar Categoria</h4>

@include('partials.flash')

<form method="POST" action="{{ route('categories.update',$category) }}" class="row g-3" style="max-width:520px">
  @csrf @method('PUT')

  <div class="col-12">
    <label class="form-label">Nome *</label>
    <input name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name',$category->name) }}" required>
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  <div class="col-12 d-flex gap-2">
    <a href="{{ route('categories.index') }}" class="btn btn-light">Voltar</a>
    <button class="btn btn-dark">Salvar alterações</button>
  </div>
</form>
@endsection
