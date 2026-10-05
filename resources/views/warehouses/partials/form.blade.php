{{-- Shared fields for create / edit. $warehouse is null on create. --}}
<x-ui.input name="name" label="اسم المستودع" :value="$warehouse?->name" required autofocus placeholder="مثال: مستودع المدينة" />
<x-ui.input name="email" type="email" label="البريد الإلكتروني" :value="$warehouse?->email" ltr required autocomplete="off" hint="للدخول بحساب المستودع" />

<div class="grid gap-5 sm:grid-cols-2">
    <x-ui.input name="password" type="password" label="كلمة المرور" :required="$warehouse === null" autocomplete="new-password"
                :hint="$warehouse ? 'اتركها فارغة إذا ما بدك تغيّرها' : null" />
    <x-ui.input name="password_confirmation" type="password" label="تأكيد كلمة المرور" :required="$warehouse === null" autocomplete="new-password" />
</div>
