<!DOCTYPE html>
<html lang="ar" dir="rtl">
<body style="margin:0;padding:24px;background:whitesmoke;font-family:Tahoma,Arial,sans-serif;color:black;direction:rtl;text-align:right">
    <div style="max-width:560px;margin:0 auto;background:white;border-radius:10px;padding:24px">
        <h2 style="margin:0 0 10px;color:firebrick">{{ $alertSubject }}</h2>
        <p style="margin:0;font-size:15px;line-height:1.9">{{ $alertBody }}</p>
        <p style="margin:18px 0 0;color:gray;font-size:12px">{{ config('app.name') }} · {{ now()->format('Y-m-d H:i') }}</p>
    </div>
</body>
</html>
