{{-- Select from design batch 1. $options: [value => label]. Chevron sits on the left (end side in RTL). --}}
@props(['name', 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null, 'optional' => false, 'hint' => null])

@php
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $error = $errors->first($errorKey);
    $current = (string) old($errorKey, $value);
@endphp

<label class="flex flex-col gap-1.5">
    @if ($label)
        <span class="text-[13px] font-medium text-ink">
            {{ $label }}
            @if ($optional)
                <span class="font-normal text-muted">(اختياري)</span>
            @endif
        </span>
    @endif

    <select
        name="{{ $name }}"
        style="background-position: left 0.75rem center"
        {{ $attributes->class([
            'h-10 w-full appearance-none rounded-lg border bg-white ps-3 pe-9 text-[15px] text-ink',
            'focus:border-primary focus:outline-none focus:ring-[3px] focus:ring-primary-ring',
            'border-danger bg-danger-soft' => $error,
            'border-line-strong' => ! $error,
        ]) }}
    >
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($current === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>

    @if ($error)
        <span class="text-[13px] font-medium text-danger">{{ $error }}</span>
    @elseif ($hint)
        <span class="text-xs text-muted">{{ $hint }}</span>
    @endif
</label>
