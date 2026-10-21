{{-- Product ledger (SRS 3.6.4): opening balance, movements with a running balance, final balance and value totals. --}}
@php
    $rows = $result['rows'];
    $qty = fn (string $value) => \App\Support\Money::isPositive($value) ? \App\Support\Format::quantity($value) : '—';
    $unit = \App\Support\Units::label($product->unit);
@endphp

<div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-muted">
    <span>المادة: <b class="text-ink">{{ $product->name }}</b> · {{ $unit }}@if ($product->barcode) · <span dir="ltr" class="inline-block">{{ $product->barcode }}</span>@endif</span>
    <span>الفترة: <b class="text-ink tabular-nums">{{ $filters->dateFrom ? \App\Support\Format::date(\Carbon\Carbon::parse($filters->dateFrom)) : 'من البداية' }} ← {{ \App\Support\Format::date(\Carbon\Carbon::parse($filters->dateTo)) }}</b></span>
    <span>المستودع: <b class="text-ink">{{ $warehouseName }}</b></span>
</div>

<x-ui.table>
    <thead>
        <tr>
            <x-ui.th>التاريخ</x-ui.th>
            <x-ui.th>النوع</x-ui.th>
            <x-ui.th>الرقم</x-ui.th>
            <x-ui.th>الطرف / المقابل</x-ui.th>
            <x-ui.th numeric>إدخال</x-ui.th>
            <x-ui.th numeric>إخراج</x-ui.th>
            <x-ui.th numeric>الرصيد</x-ui.th>
        </tr>
    </thead>
    <tbody>
        @if ($filters->dateFrom)
            <tr class="bg-primary-light font-bold">
                <x-ui.td class="whitespace-nowrap font-normal text-muted">{{ \App\Support\Format::date(\Carbon\Carbon::parse($filters->dateFrom)) }}</x-ui.td>
                <x-ui.td colspan="5">رصيد أول المدة</x-ui.td>
                <x-ui.td numeric>{{ \App\Support\Format::quantity($result['opening']) }}</x-ui.td>
            </tr>
        @endif
        @foreach ($rows as $row)
            <tr>
                <x-ui.td class="whitespace-nowrap text-muted">{{ \App\Support\Format::date(\Carbon\Carbon::parse($row['date'])) }}</x-ui.td>
                <x-ui.td class="whitespace-nowrap">
                    @if ($print) {{ $row['type']->label() }} @else <x-ui.badge :tone="$row['type']->tone()" shape="tag">{{ $row['type']->label() }}</x-ui.badge> @endif
                </x-ui.td>
                <x-ui.td class="whitespace-nowrap font-medium">
                    @if ($print)
                        <span dir="ltr" class="inline-block">{{ $row['number'] }}</span>
                    @else
                        <a href="{{ route('movements.show', $row['movement_id']) }}" dir="ltr" class="inline-block text-primary hover:underline">{{ $row['number'] }}</a>
                    @endif
                </x-ui.td>
                <x-ui.td>{{ $row['counterpart'] }}</x-ui.td>
                <x-ui.td numeric class="font-semibold">{{ $qty($row['qty_in']) }}</x-ui.td>
                <x-ui.td numeric class="font-semibold">{{ $qty($row['qty_out']) }}</x-ui.td>
                <x-ui.td numeric class="font-semibold">{{ \App\Support\Format::quantity($row['running']) }}</x-ui.td>
            </tr>
        @endforeach
        @if ($rows->isEmpty())
            <tr><x-ui.td colspan="7" class="py-8 text-center text-muted">ما في حركات على هالمادة بهالفترة.</x-ui.td></tr>
        @endif
        <tr class="bg-canvas font-bold">
            <x-ui.td class="whitespace-nowrap border-t-2 !border-line-strong font-normal text-muted">{{ \App\Support\Format::date(\Carbon\Carbon::parse($filters->dateTo)) }}</x-ui.td>
            <x-ui.td colspan="3" class="border-t-2 !border-line-strong">الرصيد النهائي</x-ui.td>
            <x-ui.td numeric class="border-t-2 !border-line-strong">{{ \App\Support\Format::quantity($result['in_qty']) }}</x-ui.td>
            <x-ui.td numeric class="border-t-2 !border-line-strong">{{ \App\Support\Format::quantity($result['out_qty']) }}</x-ui.td>
            <x-ui.td numeric class="border-t-2 !border-line-strong text-[17px] text-primary">{{ \App\Support\Format::quantity($result['closing']) }}</x-ui.td>
        </tr>
        <tr class="bg-canvas text-sm">
            <x-ui.td colspan="4" class="text-muted">مجموع القيم الصافية <span class="text-[13px]">(بدون النقل بين المستودعات)</span></x-ui.td>
            <x-ui.td numeric class="font-semibold">{{ \App\Support\Format::money($result['in_value']) }}</x-ui.td>
            <x-ui.td numeric class="font-semibold">{{ \App\Support\Format::money($result['out_value']) }}</x-ui.td>
            <x-ui.td></x-ui.td>
        </tr>
    </tbody>
</x-ui.table>

@unless ($print)
    {{ $rows->links() }}
@endunless
