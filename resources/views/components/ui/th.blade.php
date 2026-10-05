{{-- Header cell: text to the right, numbers to the left (design batch 1). --}}
@props(['numeric' => false])

<th {{ $attributes->class([
    'whitespace-nowrap border-b border-line bg-canvas px-4 py-3 text-[13px] font-medium text-muted',
    'text-left' => $numeric,
    'text-start' => ! $numeric,
]) }}>{{ $slot }}</th>
