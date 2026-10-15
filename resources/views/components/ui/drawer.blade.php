{{--
    Side drawer from design batch 5 (screen 11): 460px on the left edge, full screen on mobile.
    It reads an Alpine flag of the parent scope (`show`, default `open`); `title` is an Alpine expression.
    Esc, the backdrop and ✕ run `close` (default: `show = false`). Default slot = the form.
--}}
@props(['show' => 'open', 'close' => null, 'title' => "''"])

@php($closeExpression = $close ?? "{$show} = false")

<template x-teleport="body">
    <div
        x-show="{{ $show }}"
        x-cloak
        @keydown.escape.window="{{ $closeExpression }}"
        class="fixed inset-0 z-50"
    >
        <div x-show="{{ $show }}" x-transition.opacity class="absolute inset-0 bg-ink/35" @click="{{ $closeExpression }}"></div>

        <div
            x-show="{{ $show }}"
            x-transition:enter="transition duration-200"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            role="dialog"
            aria-modal="true"
            class="absolute inset-y-0 left-0 flex w-full flex-col bg-white shadow-2xl sm:w-[460px]"
        >
            <div class="flex items-center justify-between border-b border-line px-6 py-5">
                <b class="text-[19px] text-ink" x-text="{{ $title }}"></b>
                <button type="button" @click="{{ $closeExpression }}" class="flex h-9 w-9 items-center justify-center rounded-lg bg-canvas text-base text-muted hover:text-ink" aria-label="إغلاق">✕</button>
            </div>

            <div class="flex-1 overflow-y-auto p-6">
                {{ $slot }}
            </div>
        </div>
    </div>
</template>
