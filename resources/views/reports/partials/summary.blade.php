{{-- Totals grouped by product, party or warehouse. Quantities appear only per product: different units cannot be added. --}}
@php
    $byProduct = $filters->groupBy === 'product';
    $heading = ['product' => 'المادة', 'party' => 'الطرف', 'warehouse' => 'المستودع'][$filters->groupBy];
    $rows = $result['rows'];
@endphp

@if ($rows->isEmpty())
    <x-ui.empty-state title="ما في نتائج بهالفلاتر" description="جرّب فترة أوسع أو غيّر الفلاتر." />
@else
    <x-ui.table>
        <thead>
            <tr>
                <x-ui.th>{{ $heading }}</x-ui.th>
                <x-ui.th numeric>عدد الحركات</x-ui.th>
                @if ($byProduct)
                    <x-ui.th numeric>الكمية الداخلة</x-ui.th>
                    <x-ui.th numeric>الكمية الخارجة</x-ui.th>
                @endif
                <x-ui.th numeric>القيمة قبل الحسم</x-ui.th>
                <x-ui.th numeric>الصافي</x-ui.th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <x-ui.td class="font-semibold">
                        {{ $row['label'] ?? 'بدون طرف (نقدي)' }}
                        @if ($byProduct)<span class="text-[13px] font-normal text-muted">· {{ \App\Support\Units::label($row['unit']) }}</span>@endif
                    </x-ui.td>
                    <x-ui.td numeric>{{ $row['movements'] }}</x-ui.td>
                    @if ($byProduct)
                        <x-ui.td numeric>{{ \App\Support\Money::isPositive($row['qty_in']) ? \App\Support\Format::quantity($row['qty_in']) : '—' }}</x-ui.td>
                        <x-ui.td numeric>{{ \App\Support\Money::isPositive($row['qty_out']) ? \App\Support\Format::quantity($row['qty_out']) : '—' }}</x-ui.td>
                    @endif
                    <x-ui.td numeric class="text-muted">{{ \App\Support\Format::money($row['gross_value']) }}</x-ui.td>
                    <x-ui.td numeric class="font-bold">{{ \App\Support\Format::money($row['net_value']) }}</x-ui.td>
                </tr>
            @endforeach
            <tr class="bg-canvas font-bold">
                <x-ui.td class="border-t-2 !border-line-strong">الإجمالي</x-ui.td>
                <x-ui.td numeric class="border-t-2 !border-line-strong">{{ $result['totals']['movements'] }}</x-ui.td>
                @if ($byProduct)<x-ui.td class="border-t-2 !border-line-strong" colspan="2"></x-ui.td>@endif
                <x-ui.td numeric class="border-t-2 !border-line-strong text-muted">{{ \App\Support\Format::money($result['totals']['gross_value']) }}</x-ui.td>
                <x-ui.td numeric class="border-t-2 !border-line-strong text-[17px] text-primary"><x-ui.money :amount="$result['totals']['net_value']" /></x-ui.td>
            </tr>
        </tbody>
    </x-ui.table>
@endif
