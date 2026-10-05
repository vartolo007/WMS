{{-- Button from design batch 1. Renders <a> when href is given. --}}
@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'type' => 'submit'])

@php
    $sizes = [
        'lg' => 'h-12 px-6 text-base rounded-lg',
        'md' => 'h-10 px-[18px] text-[15px] rounded-lg',
        'sm' => 'h-8 px-3 text-[13px] rounded-md',
    ];

    $variants = [
        'primary' => 'bg-primary text-white font-semibold hover:bg-primary-dark',
        'secondary' => 'border border-line-strong bg-white text-ink font-medium hover:bg-canvas',
        'danger' => 'bg-danger text-white font-semibold hover:bg-danger-dark',
        'ghost' => 'bg-transparent text-primary font-semibold hover:bg-canvas',
    ];

    $classes = 'inline-flex items-center justify-center gap-2 whitespace-nowrap transition '
        .'focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-ring focus-visible:ring-offset-2 '
        .'disabled:cursor-not-allowed disabled:border-0 disabled:bg-line disabled:text-subtle '
        .$sizes[$size].' '.$variants[$variant];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
