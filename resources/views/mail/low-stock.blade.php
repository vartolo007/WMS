<!DOCTYPE html>
<html lang="ar" dir="rtl">
<body style="margin:0;padding:24px;background:whitesmoke;font-family:Tahoma,Arial,sans-serif;color:black;direction:rtl;text-align:right">
    <div style="max-width:620px;margin:0 auto;background:white;border-radius:10px;padding:24px">
        <h2 style="margin:0 0 6px;color:{{ $company->primary_color }}">{{ $company->name }}</h2>
        <p style="margin:0 0 16px;color:dimgray;font-size:14px">
            {{ $rows->count() }} مواد تحت حدها الأدنى اليوم ({{ \App\Support\Format::date(today()) }}):
        </p>

        <table style="width:100%;border-collapse:collapse;font-size:14px">
            <thead>
                <tr style="background:whitesmoke;color:dimgray;font-size:13px">
                    <th style="padding:8px;text-align:right">المادة</th>
                    <th style="padding:8px;text-align:right">المستودع</th>
                    <th style="padding:8px;text-align:right">الرصيد</th>
                    <th style="padding:8px;text-align:right">الحد الأدنى</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($listed as $row)
                    <tr>
                        <td style="padding:8px;border-top:1px solid lightgray;font-weight:bold">{{ $row['product']->name }}</td>
                        <td style="padding:8px;border-top:1px solid lightgray">{{ $row['warehouse']?->name ?? '—' }}</td>
                        <td style="padding:8px;border-top:1px solid lightgray;font-weight:bold;color:darkorange">{{ $row['product']->displayQuantity($row['remaining']) }} {{ $row['product']->unitLabel() }}</td>
                        <td style="padding:8px;border-top:1px solid lightgray;color:dimgray">{{ $row['product']->displayQuantity($row['min_stock']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if ($more > 0)
            <p style="margin:12px 0 0;color:dimgray;font-size:13px">وغيرها {{ $more }} مواد بالتقرير الكامل.</p>
        @endif

        <p style="margin:20px 0 0">
            <a href="{{ $reportUrl }}" style="display:inline-block;background:{{ $company->primary_color }};color:white;text-decoration:none;padding:10px 18px;border-radius:8px;font-size:14px">افتح تقرير المواد المنخفضة</a>
        </p>

        <p style="margin:20px 0 0;color:gray;font-size:12px;line-height:1.8">
            بتوصلك هالرسالة مرة باليوم، وبس لما يكون في مواد منخفضة.
            لإيقافها: <a href="{{ $settingsUrl }}" style="color:gray">إعدادات الشركة</a>.
        </p>
    </div>
</body>
</html>
