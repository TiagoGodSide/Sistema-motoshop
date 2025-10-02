@extends('layouts.app')
@section('title','Editar Funcionário')

@section('content')
<h4 class="mb-3"><i class="bi bi-person-gear"></i> Editar Funcionário</h4>

@include('partials.flash')

<form method="POST" action="{{ route('employees.update',$employee) }}" class="row g-3" style="max-width:720px">
  @csrf @method('PUT')

  <div class="col-md-6">
    <label class="form-label">Nome *</label>
    <input name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name',$employee->name) }}" required>
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  <div class="col-md-6">
    <label class="form-label">Email *</label>
    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email',$employee->email) }}" required>
    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  <div class="col-md-6">
    <label class="form-label">Nova senha (opcional)</label>
    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="Deixe em branco para manter">
    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  <div class="col-md-6">
    <label class="form-label">Papel</label>
    <select name="role" class="form-select @error('role') is-invalid @enderror">
      <option value="">— Manter atual —</option>
      @foreach(['admin'=>'Admin','gerente'=>'Gerente','vendedor'=>'Vendedor','estoque'=>'Estoque'] as $val=>$label)
        <option value="{{ $val }}" @selected(old('role')===$val || $employee->roles->contains('name',$val))>{{ $label }}</option>
      @endforeach
    </select>
    @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  <div class="col-12 d-flex gap-2">
    <a href="{{ route('employees.index') }}" class="btn btn-light">Voltar</a>
    <button class="btn btn-dark">Salvar alterações</button>
  </div>
</form>
@endsection
