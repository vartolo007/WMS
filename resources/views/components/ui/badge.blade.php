{{--
    Fixed-colour badges from design batch 1 (never the company colour).
    pill: status badges with a dot (subscription, active state). tag: rectangular (product flags, movement types).
--}}
@props(['tone' => 'neutral', 'shape' => 'pill'])

@php
    $tones = [
        'success' => ['bg-green-100 text-green-800', 'bg-success'],
        'warning' => ['bg-amber-100 text-amber-800', 'bg-amber-600'],
        'danger' => ['bg-red-100 text-red-800', 'bg-danger'],
        'orange' => ['bg-orange-100 text-orange-800', 'bg-warning'],
        'slate' => ['bg-slate-200 text-slate-600', 'bg-slate-500'],
        'neutral' => ['bg-canvas text-muted', 'bg-subtle'],
    ];
    [$colors, $dot] = $tones[$tone];
@endphp

@if ($shape === 'tag')
    <span {{ $attributes->class("inline-flex h-6 items-center rounded px-2 text-xs font-semibold {$colors}") }}>{{ $slot }}</span>
@else
    <span {{ $attributes->class("inline-flex h-[26px] items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 text-[13px] font-semibold {$colors}") }}>
        <span class="h-1.5 w-1.5 rounded-full {{ $dot }}"></span>{{ $slot }}
    </span>
@endif
