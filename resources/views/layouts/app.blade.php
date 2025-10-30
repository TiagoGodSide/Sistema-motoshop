<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Oficina & Loja de Motos')</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  @stack('styles')
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
  <div class="container">
    <a class="navbar-brand fw-semibold" href="{{ url('/') }}">
      <i class="bi bi-gear-wide-connected"></i> Oficina & Loja de Motos
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topnav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="topnav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link {{ request()->is('pos*')?'active':'' }}" href="{{ route('pos.index') }}"><i class="bi bi-upc-scan"></i> PDV</a></li>
        <li class="nav-item"><a class="nav-link {{ request()->is('products*')?'active':'' }}" href="{{ route('products.index') }}"><i class="bi bi-box-seam"></i> Produtos</a></li>
        <li class="nav-item"><a class="nav-link {{ request()->is('orders*')?'active':'' }}" href="{{ route('orders.index') }}"><i class="bi bi-receipt"></i> Pedidos</a></li>
      @auth
        @if(in_array(auth()->user()->role, ['admin','manager']))
        <li class="nav-item"><a class="nav-link {{ request()->is('categories*')?'active':'' }}" href="{{ route('categories.index') }}"><i class="bi bi-tags"></i> Categorias</a></li>
          @endif
      @endauth
        @auth
    @if(in_array(auth()->user()->role, ['admin','manager']))
        <li class="nav-item"><a class="nav-link {{ request()->is('employees*')?'active':'' }}" href="{{ route('employees.index') }}"><i class="bi bi-people"></i> Funcionários</a></li>
          @endif
    @endauth
    @auth
     @if(in_array(auth()->user()->role, ['admin','manager']))
        <li class="nav-item"><a class="nav-link {{ request()->is('reports*')?'active':'' }}" href="{{ route('reports.customers') }}">
          <i class="bi bi-graph-up"></i> Relatórios</a></li>
        @endif
      @endauth
        @php($cashOpen = \App\Models\CashRegister::where('status','open')->exists())
        <li class="nav-item">
          <a class="nav-link {{ request()->is('cash*')?'active':'' }}" href="{{ route('cash.index') }}">
            <i class="bi bi-cash-coin"></i> Caixa
            <span class="badge {{ $cashOpen ? 'bg-success' : 'bg-secondary' }} ms-1">
              {{ $cashOpen ? 'Aberto' : 'Fechado' }}
            </span>
          </a>
        </li>
      </ul>

      <div class="d-flex align-items-center gap-2">
        <span class="text-muted small">{{ auth()->user()->name ?? '' }}</span>
        <form method="POST" action="{{ route('logout') }}">@csrf
          <button class="btn btn-sm btn-outline-secondary">Sair</button>
        </form>
      </div>
    </div>
  </div>
</nav>

<main class="container py-3">
  {{-- ✅ Apenas UM include de flash e UM yield --}}
  @include('partials.flash')
  @yield('content')
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
