@props(['name', 'label', 'checked' => false, 'value' => '1'])

<label class="flex items-center gap-2 text-sm text-ink">
    <input
        type="checkbox"
        name="{{ $name }}"
        value="{{ $value }}"
        @checked(old($name, $checked))
        {{ $attributes->class('h-[18px] w-[18px] rounded border-line-strong text-primary focus:ring-primary-ring') }}
    >
    {{ $label }}
</label>
