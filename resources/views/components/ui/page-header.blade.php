{{-- Page title (28/700) with an optional subtitle and actions (default slot). --}}
@props(['title', 'subtitle' => null])

<div class="flex flex-wrap items-end justify-between gap-4">
    <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-bold text-ink lg:text-[28px]">{{ $title }}</h1>
        @if ($subtitle)
            <span class="text-sm text-muted">{{ $subtitle }}</span>
        @endif
    </div>
    @if (trim($slot) !== '')
        <div class="flex items-center gap-2">{{ $slot }}</div>
    @endif
</div>
