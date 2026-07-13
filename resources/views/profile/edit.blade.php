@extends('layouts.app')

@section('title', 'Meu Perfil')

@section('content')
<div class="container">
    <h4 class="mb-4">
        <i class="bi bi-person"></i> Meu Perfil
    </h4>

    {{-- Atualizar dados --}}
    <div class="card mb-4">
        <div class="card-body">
            <h6 class="mb-3">Dados pessoais</h6>
            @include('profile.partials.update-profile-information-form')
        </div>
    </div>

    {{-- Atualizar senha --}}
    <div class="card">
        <div class="card-body">
            <h6 class="mb-3">Alterar senha</h6>
            @include('profile.partials.update-password-form')
        </div>
    </div>
</div>
@endsection
