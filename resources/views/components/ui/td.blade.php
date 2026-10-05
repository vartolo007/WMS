{{-- Body cell: numbers left-aligned with tabular figures (design batch 1). --}}
@props(['numeric' => false])

<td {{ $attributes->class([
    'border-b border-line-soft px-4 py-3 align-middle',
    'text-left tabular-nums' => $numeric,
]) }}>{{ $slot }}</td>
