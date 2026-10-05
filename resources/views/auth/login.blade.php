<x-guest-layout>
    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-5">
        @csrf

        <div class="flex flex-col gap-1.5">
            <h1 class="text-2xl font-bold sm:text-[28px]">تسجيل الدخول</h1>
            <span class="text-[15px] text-muted">لصاحب الشركة وحسابات المستودعات</span>
        </div>

        @if (session('status'))
            <x-ui.alert tone="success">{{ session('status') }}</x-ui.alert>
        @endif

        {{-- Authentication failures stay general on purpose (design + security): no hint which field was wrong. --}}
        @error('login')
            <x-ui.alert>{{ $message }}</x-ui.alert>
        @enderror

        <x-ui.input name="email" type="email" label="البريد الإلكتروني" size="lg" ltr required autofocus autocomplete="username" placeholder="name@company.com" />

        <x-ui.input name="password" type="password" label="كلمة المرور" size="lg" ltr required autocomplete="current-password" :invalid="$errors->has('login')">
            <x-slot:aside>
                <a href="{{ route('password.request') }}" class="text-sm font-medium text-primary hover:underline">نسيت كلمة المرور؟</a>
            </x-slot:aside>
        </x-ui.input>

        <x-ui.checkbox name="remember" label="خلّيني مسجّل على هالجهاز" />

        <x-ui.button size="lg" class="w-full">دخول</x-ui.button>

        <div class="h-px bg-line"></div>

        <div class="text-center text-[15px] text-muted">
            ما عندك حساب؟ <a href="{{ route('register') }}" class="font-medium text-primary hover:underline">سجّل شركتك</a>
        </div>
    </form>
</x-guest-layout>
