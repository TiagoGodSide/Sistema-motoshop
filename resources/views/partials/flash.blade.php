{{-- sucesso --}}
@if(session('ok'))
  <div class="alert alert-success alert-dismissible fade show my-2" role="alert">
    {{ session('ok') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
  </div>
@endif

{{-- erro --}}
@if(session('error'))
  <div class="alert alert-danger alert-dismissible fade show my-2" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
  </div>
@endif

{{-- validação --}}
@if ($errors->any())
  <div class="alert alert-danger alert-dismissible fade show my-2" role="alert">
    <strong>Ops!</strong> Verifique os campos abaixo.
    <ul class="mb-0">
      @foreach ($errors->all() as $err)
        <li>{{ $err }}</li>
      @endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
  </div>
@endif
