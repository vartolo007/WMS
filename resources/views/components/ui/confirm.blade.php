{{--
    Confirmation dialog from design batch 1: focus starts on the cancel button, Esc or a click outside closes it.
    Slots: `trigger` (what opens it), default slot (explanation), optional `fields` (inputs inside the form).
--}}
@props([
    'title',
    'action',
    'method' => 'POST',
    'confirmLabel' => 'تأكيد',
    'cancelLabel' => 'تراجع',
    'tone' => 'danger',
])

<div x-data="{ open: false }" class="inline-block">
    <span @click="open = true">{{ $trigger }}</span>

    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            x-transition.opacity
            @keydown.escape.window="open = false"
            class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 p-4"
        >
            <div
                role="dialog"
                aria-modal="true"
                @click.outside="open = false"
                x-init="$watch('open', value => value && $nextTick(() => $el.querySelector('[data-cancel]').focus()))"
                class="flex w-full max-w-[400px] flex-col gap-3 rounded-xl bg-white p-6 text-start shadow-2xl"
            >
                <div class="text-lg font-bold text-ink">{{ $title }}</div>
                <div class="text-sm leading-7 text-muted">{{ $slot }}</div>

                <form method="POST" action="{{ $action }}" class="mt-2 flex flex-wrap gap-2">
                    @csrf
                    @if (strtoupper($method) !== 'POST')
                        @method($method)
                    @endif
                    @isset($fields)
                        <div class="basis-full">{{ $fields }}</div>
                    @endisset
                    <x-ui.button :variant="$tone === 'danger' ? 'danger' : 'primary'">{{ $confirmLabel }}</x-ui.button>
                    <x-ui.button variant="secondary" type="button" data-cancel @click="open = false">{{ $cancelLabel }}</x-ui.button>
                </form>
            </div>
        </div>
    </template>
</div>
