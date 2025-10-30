@extends('layouts.app')
@section('title','Categorias')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4><i class="bi bi-tags"></i> Categorias</h4>
</div>

{{-- NOVA CATEGORIA --}}
<form method="POST" action="{{ route('categories.store') }}" class="row g-2 mb-3" autocomplete="off">
  @csrf
  <div class="col-sm-6 col-md-4">
    <input type="text" name="name" class="form-control" placeholder="Nova categoria" required>
  </div>
  <div class="col-auto">
    <button class="btn btn-dark">Adicionar</button>
  </div>
</form>

{{-- LISTA --}}
<div class="table-responsive">
  <table class="table table-sm table-hover align-middle">
    <thead class="table-light">
      <tr>
        <th>Nome</th>
        <th>Status</th>
        <th class="text-end">Ações</th>
      </tr>
    </thead>
    <tbody>
      @forelse($categories as $c)
        <tr>
          <td>{{ $c->name }}</td>
          <td>
            <span class="badge {{ $c->is_active ? 'bg-success' : 'bg-secondary' }}">
              {{ $c->is_active ? 'Ativa' : 'Inativa' }}
            </span>
          </td>
          <td class="text-end">
            <form method="POST" action="{{ route('categories.update',$c) }}" class="d-inline">
              @csrf @method('PUT')
              <input type="text" name="name" value="{{ $c->name }}"
                     class="form-control form-control-sm d-inline-block" style="width:220px">
              <button class="btn btn-sm btn-outline-secondary">Salvar</button>
            </form>

            <form method="POST" action="{{ route('categories.toggle',$c) }}" class="d-inline ms-1">
              @csrf
              <button class="btn btn-sm btn-outline-{{ $c->is_active ? 'danger' : 'success' }}">
                {{ $c->is_active ? 'Inativar' : 'Ativar' }}
              </button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="3" class="text-center text-muted">Nenhuma categoria.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
