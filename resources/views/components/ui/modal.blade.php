{{--
    Centered modal from design batch 5 (screen 12): 480px, form inside. Same contract as <x-ui.drawer>:
    it reads an Alpine flag of the parent scope (`show`, default `open`); `title` is an Alpine expression.
    Esc, the backdrop and ✕ run `close` (default: `show = false`). Default slot = the form.
--}}
@props(['show' => 'open', 'close' => null, 'title' => "''"])

@php($closeExpression = $close ?? "{$show} = false")

<template x-teleport="body">
    <div
        x-show="{{ $show }}"
        x-cloak
        @keydown.escape.window="{{ $closeExpression }}"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
    >
        <div x-show="{{ $show }}" x-transition.opacity class="absolute inset-0 bg-ink/45" @click="{{ $closeExpression }}"></div>

        <div
            x-show="{{ $show }}"
            x-transition
            role="dialog"
            aria-modal="true"
            class="relative flex max-h-full w-full max-w-[480px] flex-col gap-4 overflow-y-auto rounded-xl bg-white p-6 shadow-2xl"
        >
            <div class="flex items-center justify-between">
                <b class="text-[19px] text-ink" x-text="{{ $title }}"></b>
                <button type="button" @click="{{ $closeExpression }}" class="flex h-9 w-9 items-center justify-center rounded-lg bg-canvas text-base text-muted hover:text-ink" aria-label="إغلاق">✕</button>
            </div>

            {{ $slot }}
        </div>
    </div>
</template>
