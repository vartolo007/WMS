{{-- Inline banner from design batch 1 (general form error above the form, or a success note). --}}
@props(['tone' => 'danger', 'title' => null])

@php
    $styles = [
        'danger' => ['border-danger-border bg-danger-soft text-red-800', 'bg-danger'],
        'success' => ['border-green-200 bg-green-50 text-green-800', 'bg-success'],
        'warning' => ['border-amber-200 bg-amber-50 text-amber-900', 'bg-amber-600'],
    ];
    [$box, $dot] = $styles[$tone];
@endphp

<div role="alert" {{ $attributes->class("flex items-start gap-2.5 rounded-lg border px-3.5 py-3 text-sm leading-6 {$box}") }}>
    <span class="mt-1 h-4 w-4 flex-none rounded-full {{ $dot }}"></span>
    <div class="flex flex-col gap-0.5">
        @if ($title)
            <span class="font-semibold">{{ $title }}</span>
        @endif
        <span>{{ $slot }}</span>
    </div>
</div>
