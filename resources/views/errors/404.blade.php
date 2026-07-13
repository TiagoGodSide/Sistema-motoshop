@extends('layouts.app')

@section('title', 'Página não encontrada')

@section('content')
<div class="container text-center mt-5">
    <h1 class="display-4">404</h1>
    <p class="lead">Página não encontrada.</p>
    <a href="{{ url('/') }}" class="btn btn-primary">Voltar para o início</a>
</div>
@endsection