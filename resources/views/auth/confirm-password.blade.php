<x-guest-layout>
    <form method="POST" action="{{ route('password.confirm') }}" class="flex flex-col gap-5">
        @csrf

        <div class="flex flex-col gap-1.5">
            <h1 class="text-2xl font-bold sm:text-[28px]">تأكيد كلمة المرور</h1>
            <span class="text-[15px] leading-7 text-muted">هي منطقة محمية. أكّد كلمة المرور لتكمل.</span>
        </div>

        <x-ui.input name="password" type="password" label="كلمة المرور" size="lg" required autofocus autocomplete="current-password" />

        <x-ui.button size="lg" class="w-full">تأكيد</x-ui.button>
    </form>
</x-guest-layout>
