<!DOCTYPE html>
<html lang="ar" dir="rtl">
<body style="margin:0;padding:24px;background:whitesmoke;font-family:Tahoma,Arial,sans-serif;color:black;direction:rtl;text-align:right">
    <div style="max-width:560px;margin:0 auto;background:white;border-radius:10px;padding:24px">
        <h2 style="margin:0 0 12px;color:{{ $color }}">استعادة كلمة المرور</h2>
        <p style="margin:0 0 8px;font-size:15px;line-height:1.9">أهلاً {{ $name }}،</p>
        <p style="margin:0 0 18px;font-size:15px;line-height:1.9">وصلنا طلب لتغيير كلمة المرور لحسابك. اضغط الزر لتختار كلمة مرور جديدة:</p>
        <p style="margin:0 0 18px">
            <a href="{{ $url }}" style="display:inline-block;background:{{ $color }};color:white;text-decoration:none;padding:10px 18px;border-radius:8px;font-size:14px">اختر كلمة مرور جديدة</a>
        </p>
        <p style="margin:0 0 8px;color:dimgray;font-size:13px;line-height:1.8">الرابط صالح لمدة {{ $minutes }} دقيقة. إذا ما طلبت تغيير كلمة المرور، تجاهل هالرسالة وما رح يتغيّر شي.</p>
        <p style="margin:16px 0 0;color:gray;font-size:12px;line-height:1.8;direction:ltr;text-align:right;word-break:break-all">{{ $url }}</p>
        <p style="margin:18px 0 0;color:gray;font-size:12px">{{ config('app.name') }}</p>
    </div>
</body>
</html>
