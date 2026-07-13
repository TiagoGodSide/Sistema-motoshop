@props([
    'type' => 'success'
])

<div class="alert alert-{{ $type }}">
    {{ $slot }}
</div>