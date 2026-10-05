<x-guest-layout>
    <form method="POST" action="{{ route('password.store') }}" class="flex flex-col gap-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <h1 class="text-2xl font-bold sm:text-[28px]">كلمة مرور جديدة</h1>

        <x-ui.input name="email" type="email" label="البريد الإلكتروني" :value="$request->email" size="lg" ltr required autofocus autocomplete="username" />
        <x-ui.input name="password" type="password" label="كلمة المرور الجديدة" size="lg" required autocomplete="new-password" />
        <x-ui.input name="password_confirmation" type="password" label="تأكيد كلمة المرور" size="lg" required autocomplete="new-password" />

        <x-ui.button size="lg" class="w-full">حفظ كلمة المرور</x-ui.button>
    </form>
</x-guest-layout>
