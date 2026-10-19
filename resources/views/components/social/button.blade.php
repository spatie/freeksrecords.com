@props(['href' => null, 'variant' => 'primary', 'type' => 'submit'])

@php
    $classes = ['button', 'secondary' => $variant === 'secondary', 'text-button' => $variant === 'text'];
@endphp

@if($href !== null)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
