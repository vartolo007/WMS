{{-- Party fields inside the modal. Values are filled by recordForm; after a failed save old() fills them. --}}
<fieldset class="flex flex-col gap-1.5">
    <legend class="mb-1.5 text-[13px] font-medium text-ink">النوع</legend>
    <div class="flex gap-2">
        @foreach (\App\Enums\PartyType::cases() as $case)
            <label class="flex-1">
                <input type="radio" name="type" value="{{ $case->value }}" class="peer sr-only" @checked(old('type', 'customer') === $case->value)>
                <span class="flex h-11 cursor-pointer items-center justify-center rounded-lg border border-line-strong text-[15px] text-ink peer-checked:border-primary peer-checked:bg-primary-light peer-checked:font-semibold peer-checked:text-primary peer-focus-visible:ring-[3px] peer-focus-visible:ring-primary-ring">{{ $case->label() }}</span>
            </label>
        @endforeach
    </div>
    @error('type')
        <span class="text-[13px] font-medium text-danger">{{ $message }}</span>
    @enderror
</fieldset>

<x-ui.input name="name" label="الاسم" required placeholder="مثال: محمد الأحمد" />
<x-ui.input name="phone" label="الهاتف" ltr optional placeholder="0944 000 000" inputmode="tel" autocomplete="off" />
