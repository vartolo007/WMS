{{-- Customer / supplier movement (SRS 3.6.5): invoices, returns, net dealing, then every row. No debt: everything is cash. --}}
@php
    $summary = $result['summary'];
@endphp

<div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-muted">
    <span>{{ $party->type->label() }}: <b class="text-ink">{{ $party->name }}</b>@if ($party->phone) · <span dir="ltr" class="inline-block">{{ $party->phone }}</span>@endif</span>
    <span>الفترة: <b class="text-ink tabular-nums">{{ \App\Support\Format::date(\Carbon\Carbon::parse($filters->dateFrom)) }} ← {{ \App\Support\Format::date(\Carbon\Carbon::parse($filters->dateTo)) }}</b></span>
    <span>المستودع: <b class="text-ink">{{ $warehouseName }}</b></span>
</div>

<div class="grid gap-3 sm:grid-cols-3">
    <div class="flex flex-col gap-1 rounded-xl border border-line bg-white px-5 py-4">
        <span class="text-[13px] text-muted">الفواتير · {{ $summary['invoices_count'] }}</span>
        <b class="text-[22px] tabular-nums"><x-ui.money :amount="$summary['invoices_total']" /></b>
    </div>
    <div class="flex flex-col gap-1 rounded-xl border border-line bg-white px-5 py-4">
        <span class="text-[13px] text-muted">المرتجعات · {{ $summary['returns_count'] }}</span>
        <b class="text-[22px] tabular-nums text-red-700"><x-ui.money :amount="$summary['returns_total']" /></b>
    </div>
    <div class="flex flex-col gap-1 rounded-xl border border-line bg-primary-light px-5 py-4">
        <span class="text-[13px] text-muted">صافي التعامل</span>
        <b class="text-[22px] tabular-nums text-primary"><x-ui.money :amount="$summary['net']" /></b>
    </div>
</div>

@include('reports.partials.details', ['result' => ['rows' => $result['rows'], 'totals' => []]])

<span class="text-[13px] text-muted">البيع والشراء نقديان، فما في رصيد مستحق. الحركات الملغاة غير محسوبة.</span>
