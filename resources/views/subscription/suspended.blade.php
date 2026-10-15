{{-- Screen 20 (design batch 7): the only page open to users of a suspended company (FR-03). --}}
<x-standalone-layout title="الاشتراك موقوف">
    <div class="flex w-full max-w-[560px] flex-col gap-[18px] rounded-2xl border border-line bg-white p-6 sm:p-10">
        <x-ui.badge tone="danger" class="self-start">موقوف</x-ui.badge>

        <h1 class="text-[28px] font-bold leading-[1.4]">اشتراك شركتك موقوف</h1>

        <p class="text-base leading-[1.9] text-ink">
            بياناتك كلها محفوظة — الحركات والمواد والتقارير — وبترجع فوراً بعد التفعيل.
        </p>

        @if ($isOwner)
            <div class="flex flex-col gap-2.5 rounded-xl bg-canvas px-5 py-[18px]">
                <b class="text-[15px]">للتفعيل تواصل معنا</b>

                @isset($support['phone'])
                    <div class="flex flex-wrap justify-between gap-x-4 text-[15px]">
                        <span class="text-muted">الهاتف / واتساب</span>
                        <b dir="ltr" class="tabular-nums" style="unicode-bidi: isolate">{{ $support['phone'] }}</b>
                    </div>
                @endisset
                @isset($support['email'])
                    <div class="flex flex-wrap justify-between gap-x-4 text-[15px]">
                        <span class="text-muted">البريد</span>
                        <b dir="ltr" style="unicode-bidi: isolate">{{ $support['email'] }}</b>
                    </div>
                @endisset
                @isset($support['hours'])
                    <div class="flex flex-wrap justify-between gap-x-4 text-[15px]">
                        <span class="text-muted">ساعات الدوام</span>
                        <b>{{ $support['hours'] }}</b>
                    </div>
                @endisset
            </div>
        @else
            <div class="rounded-xl bg-canvas px-5 py-[18px] text-[15px] leading-7">
                <b>تواصل مع صاحب الشركة</b> لتفعيل الاشتراك.
            </div>
        @endif

        <div class="flex flex-col gap-2 sm:flex-row">
            @if ($isOwner && $whatsappUrl)
                <x-ui.button :href="$whatsappUrl" class="h-11 px-5">راسلنا على واتساب</x-ui.button>
            @endif
            <form method="POST" action="{{ route('logout') }}" class="contents">
                @csrf
                <x-ui.button variant="secondary" class="h-11 px-[18px]">تسجيل الخروج</x-ui.button>
            </form>
        </div>
    </div>
</x-standalone-layout>
