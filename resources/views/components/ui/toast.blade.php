{{-- Success toast from design batch 1: floats at the top and hides after 4 seconds. Reads session('status'). --}}
@php
    $known = [
        'profile-updated' => 'تم حفظ معلوماتك.',
        'password-updated' => 'تم تغيير كلمة المرور.',
        'verification-link-sent' => 'تم إرسال رابط تحقق جديد إلى بريدك.',
    ];
    $message = session('status') ? ($known[session('status')] ?? session('status')) : null;
@endphp

@if ($message)
    <div
        x-data="{ show: true }"
        x-init="setTimeout(() => show = false, 4000)"
        x-show="show"
        x-transition.opacity
        role="status"
        class="fixed inset-x-4 top-4 z-50 mx-auto flex max-w-md items-start gap-3 rounded-[10px] border border-green-200 bg-white px-4 py-3.5 shadow-lg"
    >
        <span class="mt-0.5 h-5 w-5 flex-none rounded-full bg-success"></span>
        <span class="flex-1 text-[15px] font-semibold text-ink">{{ $message }}</span>
        <button type="button" @click="show = false" class="text-sm text-muted hover:text-ink" aria-label="إغلاق">✕</button>
    </div>
@endif
