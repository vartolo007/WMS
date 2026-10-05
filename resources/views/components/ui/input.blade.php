{{--
    Text input from design batch 1: label, optional marker, hint, error from $errors, currency suffix.
    `ltr` for emails / codes (left-to-right, right aligned); `numeric` for numbers (left aligned, tabular).
    Named slot `aside` sits at the end of the label row (e.g. "forgot password?").
--}}
@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'optional' => false,
    'suffix' => null,
    'ltr' => false,
    'numeric' => false,
    'size' => 'md',
    'invalid' => false,
    'bag' => 'default',
])

@php
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $error = $errors->getBag($bag)->first($errorKey);
    $hasError = $error || $invalid;
    $current = $type === 'password' ? null : old($errorKey, $value);
@endphp

<label class="flex flex-col gap-1.5">
    @if ($label || isset($aside))
        <span class="flex items-center justify-between gap-2">
            <span class="text-[13px] font-medium text-ink">
                {{ $label }}
                @if ($optional)
                    <span class="font-normal text-muted">(اختياري)</span>
                @endif
            </span>
            {{ $aside ?? '' }}
        </span>
    @endif

    <span @class([
        'flex items-center rounded-lg border bg-white transition',
        'focus-within:border-primary focus-within:ring-[3px] focus-within:ring-primary-ring',
        'h-11' => $size === 'lg',
        'h-10' => $size !== 'lg',
        'border-danger bg-danger-soft' => $hasError,
        'border-line-strong' => ! $hasError,
    ])>
        <input
            type="{{ $type }}"
            name="{{ $name }}"
            @if ($current !== null) value="{{ $current }}" @endif
            @if ($ltr || $numeric) dir="ltr" @endif
            @if ($hasError) aria-invalid="true" @endif
            {{ $attributes->class([
                'h-full min-w-0 flex-1 border-0 bg-transparent px-3 text-[15px] text-ink placeholder:text-subtle focus:outline-none focus:ring-0',
                'text-right' => $ltr,
                'text-left tabular-nums' => $numeric,
            ]) }}
        >
        @if ($suffix)
            <span class="border-s border-line px-3 text-sm text-muted">{{ $suffix }}</span>
        @endif
    </span>

    @if ($error)
        <span class="text-[13px] font-medium text-danger">{{ $error }}</span>
    @elseif ($hint)
        <span class="text-xs text-muted">{{ $hint }}</span>
    @endif
</label>
