@props([
    'type' => 'button',
    'variant' => 'primary'
])

<button type="{{ $type }}" class="btn btn-{{ $variant }}">
    {{ $slot }}
</button>