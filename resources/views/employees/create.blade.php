@extends('layouts.app')
@section('title','Novo Funcionário')

@section('content')
<h4 class="mb-3"><i class="bi bi-person-plus"></i> Novo Funcionário</h4>

@include('partials.flash')

<form method="POST" action="{{ route('employees.store') }}" class="row g-3" style="max-width:640px">
  @csrf
  <div class="col-md-6">
    <label class="form-label">Nome *</label>
    <input name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  <div class="col-md-6">
    <label class="form-label">Email *</label>
    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  <div class="col-md-6">
    <label class="form-label">Senha *</label>
    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
    <div class="form-text">Mínimo 6 caracteres (ajuste a policy se quiser).</div>
  </div>

  <div class="col-md-6">
    <label class="form-label">Papel *</label>
    <select name="role" class="form-select @error('role') is-invalid @enderror" required>
      @foreach(['admin'=>'Admin','gerente'=>'Gerente','vendedor'=>'Vendedor','estoque'=>'Estoque'] as $val=>$label)
        <option value="{{ $val }}" @selected(old('role')===$val)>{{ $label }}</option>
      @endforeach
    </select>
    @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  <div class="col-12 d-flex gap-2">
    <a href="{{ route('employees.index') }}" class="btn btn-light">Cancelar</a>
    <button class="btn btn-dark">Salvar</button>
  </div>
</form>
@endsection
