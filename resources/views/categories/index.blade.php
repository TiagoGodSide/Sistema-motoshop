@extends('layouts.app')
@section('title','Categorias')
@section('content')

<h4><i class="bi bi-tags"></i> Categorias</h4>

<form method="post" action="{{ route('categories.store') }}" class="row g-2 mb-3">@csrf
  <div class="col-md-6"><input name="name" class="form-control" placeholder="Nova categoria"></div>
  <div class="col-md-2"><button class="btn btn-dark">Adicionar</button></div>
</form>

<div class="table-responsive">
  <table class="table table-sm align-middle table-hover">
    <thead class="table-light"><tr><th>Nome</th><th>Status</th><th></th></tr></thead>
    <tbody>
      @foreach($categories as $c)
        <tr>
          <td>{{ $c->name }}</td>
          <td>{{ $c->is_active ? 'Ativa' : 'Inativa' }}</td>
          <td class="text-end">
            <form method="POST" action="{{ route('categories.update',$c) }}" class="d-inline">@csrf @method('PUT')
              <input name="name" value="{{ $c->name }}" class="form-control form-control-sm d-inline-block" style="width:150px">
              <button class="btn btn-sm btn-outline-secondary">Salvar</button>
            </form>
            <form method="POST" action="{{ route('categories.destroy',$c) }}" class="d-inline">@csrf @method('DELETE')
              <button class="btn btn-sm btn-outline-danger">Inativar</button>
            </form>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>

{{ $categories->links() }}

@endsection
