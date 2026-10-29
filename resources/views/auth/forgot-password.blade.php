<x-guest-layout title="نسيت كلمة المرور">
    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-5">
        @csrf

        <div class="flex flex-col gap-1.5">
            <h1 class="text-2xl font-bold sm:text-[28px]">نسيت كلمة المرور؟</h1>
            <span class="text-[15px] leading-7 text-muted">اكتب بريدك ومنبعتلك رابط لتختار كلمة مرور جديدة.</span>
        </div>

        @if (session('status'))
            <x-ui.alert tone="success">{{ session('status') }}</x-ui.alert>
        @endif

        <x-ui.input name="email" type="email" label="البريد الإلكتروني" size="lg" ltr required autofocus autocomplete="username" placeholder="name@company.com" />

        <x-ui.button size="lg" class="w-full">إرسال رابط إعادة التعيين</x-ui.button>

        <div class="text-center text-[15px] text-muted">
            <a href="{{ route('login') }}" class="font-medium text-primary hover:underline">رجوع لتسجيل الدخول</a>
        </div>
    </form>
</x-guest-layout>
