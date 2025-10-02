@if(session('ok'))
  <div class="alert alert-success">{{ session('ok') }}</div>
@endif

@if($errors->any())
  <div class="alert alert-danger">
    <strong>Ops!</strong> Verifique os campos abaixo.
    <ul class="mb-0">
      @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
    </ul>
  </div>
@endif
