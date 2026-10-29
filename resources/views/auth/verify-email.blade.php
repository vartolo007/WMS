<x-guest-layout title="تأكيد البريد">
    <div class="flex flex-col gap-5">
        <div class="flex flex-col gap-1.5">
            <h1 class="text-2xl font-bold sm:text-[28px]">تأكيد البريد</h1>
            <span class="text-[15px] leading-7 text-muted">بعتنالك رابط على بريدك. اضغط عليه لتأكيد الحساب، وإذا ما وصلك منبعتلك غيره.</span>
        </div>

        @if (session('status') == 'verification-link-sent')
            <x-ui.alert tone="success">تم إرسال رابط تحقق جديد إلى بريدك.</x-ui.alert>
        @endif

        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-ui.button size="lg" class="w-full">إعادة إرسال الرابط</x-ui.button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="text-center">
            @csrf
            <button type="submit" class="text-[15px] font-medium text-muted hover:text-ink">تسجيل الخروج</button>
        </form>
    </div>
</x-guest-layout>
