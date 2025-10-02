@extends('layouts.app')
@section('title','Funcionários')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-people"></i> Funcionários</h4>
  <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#novoColab">
    <i class="bi bi-plus-lg"></i> Novo
  </button>
</div>

<form method="get" class="input-group mb-3" style="max-width:420px">
  <input name="q" class="form-control" value="{{ request('q') }}" placeholder="Buscar por nome/email">
  <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
</form>

<div class="table-responsive">
  <table class="table table-sm table-hover align-middle">
    <thead class="table-light">
      <tr>
        <th>Nome</th><th>Email</th><th>Função</th><th class="text-end">Editar</th>
      </tr>
    </thead>
    <tbody>
      @foreach($users as $u)
        <tr>
          <td>{{ $u->name }}</td>
          <td>{{ $u->email }}</td>
          <td>{{ $u->roles->pluck('name')->join(', ') }}</td>
          <td class="text-end">
            <form method="POST" action="{{ route('employees.update',$u) }}" class="d-inline">
              @csrf @method('PUT')
              <input type="text"  name="name"  value="{{ $u->name }}"  class="form-control form-control-sm d-inline-block" style="width:150px">
              <input type="email" name="email" value="{{ $u->email }}" class="form-control form-control-sm d-inline-block" style="width:200px">
              <select name="role" class="form-select form-select-sm d-inline-block" style="width:150px">
                @foreach(['admin','gerente','vendedor','estoque'] as $r)
                  <option value="{{ $r }}" {{ $u->roles->contains('name',$r)?'selected':'' }}>{{ ucfirst($r) }}</option>
                @endforeach
              </select>
              <button class="btn btn-sm btn-outline-secondary">Salvar</button>
            </form>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>

{{ $users->links() }}

{{-- MODAL NOVO FUNCIONÁRIO (markup completo, todas as tags fechadas) --}}
<div class="modal fade" id="novoColab" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="POST" action="{{ route('employees.store') }}">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Novo Funcionário</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body">
        @includeWhen($errors->any(),'partials.flash') {{-- mostra erros dentro do modal, se houver --}}
        <div class="mb-2">
          <label class="form-label">Nome *</label>
          <input class="form-control" name="name" value="{{ old('name') }}" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Email *</label>
          <input type="email" class="form-control" name="email" value="{{ old('email') }}" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Senha *</label>
          <input type="password" class="form-control" name="password" required>
          <div class="form-text">Mínimo 6 caracteres.</div>
        </div>
        <div class="mb-2">
          <label class="form-label">Função *</label>
          <select class="form-select" name="role" required>
            <option value="" disabled selected>Selecione</option>
            <option value="admin"   @selected(old('role')==='admin')>Admin</option>
            <option value="gerente" @selected(old('role')==='gerente')>Gerente</option>
            <option value="vendedor"@selected(old('role')==='vendedor')>Vendedor</option>
            <option value="estoque" @selected(old('role')==='estoque')>Estoque</option>
          </select>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-dark">Adicionar</button>
      </div>
    </form>
  </div>
</div>
@endsection
