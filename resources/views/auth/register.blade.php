<x-guest-layout width="max-w-[460px]">
    <x-slot:headerAction>
        عندك حساب؟ <a href="{{ route('login') }}" class="font-medium text-primary hover:underline">سجّل دخول</a>
    </x-slot:headerAction>

    <form method="POST" action="{{ route('register') }}" class="flex flex-col gap-[18px]">
        @csrf

        <div class="flex flex-col gap-1.5">
            <h1 class="text-2xl font-bold sm:text-[28px]">سجّل شركتك</h1>
            <span class="text-[15px] text-muted">حساب صاحب الشركة. حسابات المستودعات بتنشئها بعدين من صفحة المستودعات.</span>
        </div>

        <div class="flex items-start gap-3 rounded-[10px] bg-amber-100 px-4 py-3.5 text-sm leading-7 text-amber-900">
            <span class="inline-flex h-6 flex-none items-center rounded-full bg-white px-2.5 text-xs font-bold text-amber-800">تجريبي</span>
            <span>بتبدأ بفترة تجريبية مجانية <b>{{ config('wms.trial_days') }} يوم</b> بكل الميزات. ما في بطاقة دفع؛ قبل نهايتها منتواصل معك للتفعيل.</span>
        </div>

        <x-ui.input name="company_name" label="اسم الشركة" size="lg" required autofocus autocomplete="organization" />
        <x-ui.input name="name" label="اسم صاحب الشركة" size="lg" required autocomplete="name" />
        <x-ui.input name="email" type="email" label="البريد الإلكتروني" size="lg" ltr required autocomplete="username" />

        <div class="grid gap-3 sm:grid-cols-2">
            <x-ui.input name="password" type="password" label="كلمة المرور" size="lg" required autocomplete="new-password" />
            <x-ui.input name="password_confirmation" type="password" label="تأكيد كلمة المرور" size="lg" required autocomplete="new-password" />
        </div>

        <x-ui.button size="lg" class="mt-1 w-full">إنشاء الحساب وبدء التجربة</x-ui.button>
    </form>

    <x-slot:aside>
        <div class="hidden w-[520px] flex-none flex-col justify-center gap-7 border-s border-line bg-canvas p-14 lg:flex">
            <div class="text-[22px] font-bold">بعد التسجيل</div>
            <div class="flex flex-col gap-[22px]">
                @foreach ([
                    ['أضف مستودعاتك', 'مثلاً مستودع المدينة ومستودع الريف، لكل واحد حساب دخول.'],
                    ['اختر لونك وعملتك', 'الشعار واللون ورمز العملة بيظهروا بكل الشاشات والمطبوعات.'],
                    ['سجّل أول فاتورة', 'بالباركود أو بالبحث بالاسم.'],
                ] as [$step, $hint])
                    <div class="flex gap-3.5">
                        <span class="flex h-7 w-7 flex-none items-center justify-center rounded-full bg-primary text-sm font-bold text-white">{{ $loop->iteration }}</span>
                        <div class="flex flex-col gap-0.5">
                            <b class="text-base">{{ $step }}</b>
                            <span class="text-sm leading-7 text-muted">{{ $hint }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </x-slot:aside>
</x-guest-layout>
