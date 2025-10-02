<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title','Oficina & Loja de Motos')</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <style>
    :root { --brand:#00a99d; }
    .brand-text{ color:var(--brand); }
    .soft-card{ border:1px solid #e9ecef; border-radius:1rem; box-shadow:0 6px 20px rgba(0,0,0,.05); }
  </style>
  @stack('styles')
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
  <div class="container-fluid">
    <a class="navbar-brand" href="{{ route('pos.index') }}">
      <i class="bi bi-gear-fill brand-text"></i> Oficina & Loja de Motos
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topnav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="topnav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="{{ route('pos.index') }}"><i class="bi bi-upc-scan"></i> PDV</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('products.index') }}"><i class="bi bi-box-seam"></i> Produtos</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('orders.index') }}"><i class="bi bi-receipt-cutoff"></i> Pedidos</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('categories.index') }}"><i class="bi bi-tags"></i> Categorias</a></li>
        @can('employees.manage')
        <li class="nav-item"><a class="nav-link" href="{{ route('employees.index') }}"><i class="bi bi-people"></i> Funcionários</a></li>
        @endcan
        @php($cashOpen = \App\Models\CashRegister::where('status','open')->exists())
          <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('cash.*') ? 'active' : '' }}" href="{{ route('cash.index') }}">
              <i class="bi bi-cash-coin"></i> Caixa
              @if($cashOpen)
                <span class="badge bg-success ms-1">Aberto</span>
              @else
                <span class="badge bg-secondary ms-1">Fechado</span>
              @endif
            </a>
          </li>
      </ul>

      <ul class="navbar-nav ms-auto">
        <li class="nav-item d-flex align-items-center me-2 text-muted small">
          @auth {{ auth()->user()->name }} @endauth
        </li>
        <li class="nav-item">
          <form method="POST" action="{{ route('logout') }}"> @csrf
            <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-box-arrow-right"></i> Sair</button>
          </form>
        </li>
      </ul>
    </div>
  </div>
</nav>

<main class="container py-3">
  @includeWhen(session('ok') || $errors->any(), 'partials.flash')
  @yield('content')
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
