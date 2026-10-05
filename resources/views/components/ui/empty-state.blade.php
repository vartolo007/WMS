{{-- Empty state from design batch 1: why the list is empty + the next action (default slot). --}}
@props(['title', 'description' => null])

<div {{ $attributes->class('flex flex-col items-center gap-2.5 rounded-xl border border-dashed border-line-strong bg-white px-6 py-10 text-center') }}>
    <span class="h-11 w-11 rounded-[10px] border border-line bg-canvas"></span>
    <div class="text-[17px] font-semibold text-ink">{{ $title }}</div>
    @if ($description)
        <div class="max-w-xs text-sm leading-7 text-muted">{{ $description }}</div>
    @endif
    @if (trim($slot) !== '')
        <div class="mt-1.5">{{ $slot }}</div>
    @endif
</div>
