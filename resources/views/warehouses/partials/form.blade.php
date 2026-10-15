{{-- Shared fields for create / edit. $warehouse is null on create. --}}
<x-ui.input name="name" label="اسم المستودع" :value="$warehouse?->name" required autofocus placeholder="مثال: مستودع المدينة" />
<x-ui.input name="email" type="email" label="بريد الحساب" :value="$warehouse?->email" ltr required autocomplete="off" hint="للدخول بحساب المستودع" />
<x-ui.input name="password" type="password" label="كلمة المرور" :required="$warehouse === null" autocomplete="new-password"
            :hint="$warehouse ? 'اتركها فارغة إذا ما بدك تغيّرها' : '8 أحرف على الأقل. بلّغها لمسؤول المستودع.'" />
<x-ui.input name="password_confirmation" type="password" label="تأكيد كلمة المرور" :required="$warehouse === null" autocomplete="new-password" />
