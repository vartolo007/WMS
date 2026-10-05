{{-- Shared fields for create / edit. $warehouse is null on create. --}}
<div>
    <x-input-label for="name" value="اسم المستودع" />
    <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name', $warehouse?->name)" required autofocus />
    <x-input-error :messages="$errors->get('name')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="email" :value="__('Email')" />
    <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" dir="ltr" :value="old('email', $warehouse?->email)" required autocomplete="off" />
    <x-input-error :messages="$errors->get('email')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="password" :value="__('Password')" />
    <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" :required="$warehouse === null" autocomplete="new-password" />
    @if ($warehouse)
        <p class="mt-1 text-xs text-gray-500">اتركها فارغة إذا لا تريد تغييرها.</p>
    @endif
    <x-input-error :messages="$errors->get('password')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
    <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" :required="$warehouse === null" autocomplete="new-password" />
</div>
