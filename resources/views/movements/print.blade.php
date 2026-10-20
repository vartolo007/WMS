{{-- Screen 10 (design batch 4): A4 print copy of a movement. Standalone page, opens in a new tab and prints. --}}
@php
    $company = $movement->company;
    $user = auth()->user();
    $symbol = $company->currency_symbol;
    [$leftSignature, $rightSignature] = $movement->type->signatures();
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $movement->type->label() }} {{ $movement->displayNumber() }} · {{ $company->name }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=ibm-plex-sans-arabic:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css'])
        @include('layouts.partials.theme')
        <style>
            @page { size: A4; margin: 0; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        </style>
    </head>
    <body class="bg-canvas font-sans text-ink antialiased print:bg-white">
        <div class="mx-auto flex max-w-[794px] justify-end gap-2 px-4 py-4 print:hidden">
            <x-ui.button type="button" onclick="window.print()">طباعة</x-ui.button>
            <x-ui.button variant="secondary" :href="route('movements.show', $movement)">رجوع</x-ui.button>
            <x-ui.button variant="secondary" :href="$movement->type->createUrl()">+ حركة جديدة</x-ui.button>
        </div>

        <main class="relative mx-auto flex min-h-[1123px] w-full max-w-[794px] flex-col gap-7 overflow-hidden bg-white px-6 py-10 text-[13px] shadow-lg print:min-h-0 print:max-w-none print:px-[60px] print:py-14 print:shadow-none sm:px-[60px] sm:py-14">
            @if ($movement->isCancelled())
                <div class="pointer-events-none absolute inset-0 flex items-center justify-center" aria-hidden="true">
                    <span class="-rotate-[25deg] rounded-xl border-8 border-danger/30 px-10 py-3 text-[110px] font-bold leading-none text-danger/30">ملغاة</span>
                </div>
            @endif

            <header class="flex items-start justify-between gap-4 border-b-[3px] border-primary pb-5">
                <div class="flex items-center gap-3.5">
                    @if ($company->logoUrl())
                        <img src="{{ $company->logoUrl() }}" alt="" class="h-14 w-14 rounded-[10px] object-contain">
                    @else
                        <span class="flex h-14 w-14 items-center justify-center rounded-[10px] bg-primary text-[26px] font-bold text-white">{{ mb_substr($company->name, 0, 1) }}</span>
                    @endif
                    <div class="flex flex-col gap-0.5">
                        <b class="text-[22px]">{{ $company->name }}</b>
                        <span class="text-muted">{{ $movement->warehouse->name }}</span>
                    </div>
                </div>
                <div class="flex flex-col items-end gap-0.5">
                    <b class="text-xl">{{ $movement->type->label() }}</b>
                    <span dir="ltr" class="text-[15px] font-semibold">{{ $movement->displayNumber() }}</span>
                </div>
            </header>

            <div class="grid grid-cols-3 gap-3">
                <div class="flex flex-col gap-0.5"><span class="text-muted">التاريخ</span><b class="tabular-nums">{{ \App\Support\Format::date($movement->date) }}</b></div>
                <div class="flex flex-col gap-0.5"><span class="text-muted">{{ $movement->type->partyLabel() }}</span><b>{{ $movement->type->counterpartLabel($movement->party?->name, $movement->targetWarehouse?->name) }}</b></div>
                @if ($movement->type !== \App\Enums\MovementType::Transfer)
                    <div class="flex flex-col gap-0.5"><span class="text-muted">الدفع</span><b>نقدي</b></div>
                @endif
                @if ($movement->relatedMovement)
                    <div class="flex flex-col gap-0.5"><span class="text-muted">الفاتورة الأصلية</span><b dir="ltr" class="text-right">{{ $movement->relatedMovement->displayNumber() }}</b></div>
                @endif
                @if ($movement->document_no)
                    <div class="flex flex-col gap-0.5"><span class="text-muted">رقم الوثيقة</span><b dir="ltr" class="text-right">{{ $movement->document_no }}</b></div>
                @endif
            </div>

            <table class="w-full border-collapse tabular-nums">
                <thead>
                    <tr class="border-y border-ink">
                        <th class="px-1.5 py-2 text-start font-semibold">#</th>
                        <th class="px-1.5 py-2 text-start font-semibold">الرمز</th>
                        <th class="px-1.5 py-2 text-start font-semibold">المادة</th>
                        <th class="px-1.5 py-2 text-start font-semibold">الوحدة</th>
                        <th class="px-1.5 py-2 text-left font-semibold">الكمية</th>
                        <th class="px-1.5 py-2 text-left font-semibold">السعر</th>
                        <th class="px-1.5 py-2 text-left font-semibold">القيمة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($movement->items as $item)
                        <tr class="border-b border-line-strong">
                            <td class="px-1.5 py-2">{{ $loop->iteration }}</td>
                            <td class="px-1.5 py-2" dir="ltr">{{ $item->product->barcode ?? '—' }}</td>
                            <td class="px-1.5 py-2">{{ $item->product->name }}</td>
                            <td class="px-1.5 py-2">{{ $item->product->unitLabel() }}</td>
                            <td class="px-1.5 py-2 text-left">{{ \App\Support\Format::quantity($item->quantity) }}</td>
                            <td class="px-1.5 py-2 text-left">{{ \App\Support\Format::money($item->unit_price) }}</td>
                            <td class="px-1.5 py-2 text-left">{{ \App\Support\Format::money($item->line_total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="ms-auto flex w-[300px] flex-col gap-1.5 text-sm tabular-nums">
                @if ($movement->type === \App\Enums\MovementType::Transfer)
                    <div class="flex justify-between border-t-2 border-ink pt-2 text-[15px] font-bold">
                        <span>القيمة التقديرية (للتقييم فقط)</span>
                        <span>{{ \App\Support\Format::money($movement->subtotal, $symbol) }}</span>
                    </div>
                @else
                    <div class="flex justify-between"><span>المجموع</span><span>{{ \App\Support\Format::money($movement->subtotal) }}</span></div>
                    <div class="flex justify-between"><span>الحسم</span><span>{{ \App\Support\Format::money($movement->discount) }}</span></div>
                    <div class="flex justify-between border-t-2 border-ink pt-2 text-[17px] font-bold">
                        <span>الصافي · {{ $movement->type->netLabel() }}</span>
                        <span>{{ \App\Support\Format::money($movement->total, $symbol) }}</span>
                    </div>
                @endif
            </div>

            <div class="flex-1"></div>

            <div class="grid grid-cols-2 gap-12 text-muted">
                <div class="border-t border-subtle pt-1.5">{{ $leftSignature }}</div>
                <div class="border-t border-subtle pt-1.5">{{ $rightSignature }}</div>
            </div>

            <footer class="flex justify-between border-t border-line pt-2.5 text-[11px] text-subtle">
                <span>طُبعت {{ now()->format('Y-m-d H:i') }} · {{ $user->name }}</span>
                <span>صفحة 1 من 1</span>
            </footer>
        </main>

        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
    </body>
</html>
