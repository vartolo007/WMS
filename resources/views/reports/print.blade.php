{{-- Screen 18 (design batch 6): A4 print copy of a report. Same partials as the screen, plain text instead of badges. --}}
@php
    $company = auth()->user()->company;
    $period = $report->period();
    $applied = collect([
        $product ? 'المادة: '.$product->name : null,
        $party ? ($party->type->label()).': '.$party->name : null,
        $report->hasGrouping() ? 'التجميع: '.['product' => 'حسب المادة', 'party' => 'حسب الطرف', 'warehouse' => 'حسب المستودع'][$filters->groupBy] : null,
    ])->filter()->implode(' · ');
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $report->label() }} · {{ $company->name }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=ibm-plex-sans-arabic:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css'])
        @include('layouts.partials.theme')
        <style>
            @page { size: A4; margin: 0; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            thead { display: table-header-group; }
            tr { break-inside: avoid; }
            /* A printed table must fit the page: compact cells, wrapping text, no inner scrollbar. */
            main .overflow-x-auto { overflow: visible !important; }
            main table { font-size: 12.5px !important; }
            main th, main td { padding: 6px 7px !important; white-space: normal !important; }
        </style>
    </head>
    <body class="bg-canvas font-sans text-ink antialiased print:bg-white">
        <div class="mx-auto flex max-w-[794px] justify-end gap-2 px-4 py-4 print:hidden">
            <x-ui.button type="button" onclick="window.print()">طباعة</x-ui.button>
            <x-ui.button variant="secondary" :href="route('reports.show', ['report' => $report, ...request()->except('page')])">رجوع</x-ui.button>
        </div>

        <main class="mx-auto flex min-h-[1123px] w-full max-w-[794px] flex-col gap-5 bg-white px-6 py-10 text-[13px] shadow-lg print:min-h-0 print:max-w-none print:px-[60px] print:py-14 print:shadow-none sm:px-[60px] sm:py-14">
            <header class="flex items-start justify-between gap-4 border-b-[3px] border-primary pb-5">
                <div class="flex items-center gap-3.5">
                    @if ($company->logoUrl())
                        <img src="{{ $company->logoUrl() }}" alt="" class="h-14 w-14 rounded-[10px] object-contain">
                    @else
                        <span class="flex h-14 w-14 items-center justify-center rounded-[10px] bg-primary text-[26px] font-bold text-white">{{ mb_substr($company->name, 0, 1) }}</span>
                    @endif
                    <div class="flex flex-col gap-0.5">
                        <b class="text-[22px]">{{ $company->name }}</b>
                        <span class="text-muted">{{ $warehouseName }}</span>
                    </div>
                </div>
                <div class="flex flex-col items-end gap-0.5">
                    <b class="text-xl">{{ $report->label() }}</b>
                    <span class="tabular-nums text-muted">
                        @if ($period === 'day')
                            {{ \App\Support\Format::date(\Carbon\Carbon::parse($filters->dateTo)) }}
                        @else
                            {{ $filters->dateFrom ? \App\Support\Format::date(\Carbon\Carbon::parse($filters->dateFrom)) : 'من البداية' }} ← {{ \App\Support\Format::date(\Carbon\Carbon::parse($filters->dateTo)) }}
                        @endif
                    </span>
                </div>
            </header>

            @if ($applied !== '')
                <div class="text-muted">{{ $applied }}</div>
            @endif

            @if ($missing)
                <p class="text-muted">اختر {{ $missing === 'product' ? 'المادة' : 'الطرف' }} من شاشة التقارير قبل الطباعة.</p>
            @else
                @include('reports.partials.'.$report->view(), ['print' => true])
            @endif

            <div class="flex-1"></div>

            <footer class="flex justify-between border-t border-line pt-2.5 text-[11px] text-subtle">
                <span>طُبع {{ now()->format('Y-m-d H:i') }} · {{ auth()->user()->name }}</span>
                <span>صفحة 1 من 1</span>
            </footer>
        </main>

        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
    </body>
</html>
