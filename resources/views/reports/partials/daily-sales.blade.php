{{-- Net sales of the day per warehouse = sales − sale returns, after the discounts (SRS 3.6.3): what should be in the till. --}}
<x-ui.table>
    <thead>
        <tr>
            <x-ui.th>المستودع</x-ui.th>
            <x-ui.th numeric>عدد الفواتير</x-ui.th>
            <x-ui.th numeric>المبيعات</x-ui.th>
            <x-ui.th numeric>المرتجعات</x-ui.th>
            <x-ui.th numeric>الصافي</x-ui.th>
        </tr>
    </thead>
    <tbody>
        @foreach ($result['rows'] as $row)
            <tr>
                <x-ui.td class="font-semibold">{{ $row['warehouse']->name }}</x-ui.td>
                <x-ui.td numeric>{{ $row['invoices'] }}</x-ui.td>
                <x-ui.td numeric>{{ \App\Support\Format::money($row['sales']) }}</x-ui.td>
                <x-ui.td numeric><span @class(['text-red-700' => \App\Support\Money::isPositive($row['returns']), 'text-subtle' => ! \App\Support\Money::isPositive($row['returns'])])>{{ \App\Support\Format::money($row['returns']) }}</span></x-ui.td>
                <x-ui.td numeric class="font-bold">{{ \App\Support\Format::money($row['net']) }}</x-ui.td>
            </tr>
        @endforeach
        @if ($result['rows']->count() > 1)
            <tr class="bg-canvas font-bold">
                <x-ui.td class="border-t-2 !border-line-strong">الإجمالي</x-ui.td>
                <x-ui.td numeric class="border-t-2 !border-line-strong">{{ $result['totals']['invoices'] }}</x-ui.td>
                <x-ui.td numeric class="border-t-2 !border-line-strong">{{ \App\Support\Format::money($result['totals']['sales']) }}</x-ui.td>
                <x-ui.td numeric class="border-t-2 !border-line-strong">{{ \App\Support\Format::money($result['totals']['returns']) }}</x-ui.td>
                <x-ui.td numeric class="border-t-2 !border-line-strong text-[17px] text-primary"><x-ui.money :amount="$result['totals']['net']" /></x-ui.td>
            </tr>
        @endif
    </tbody>
</x-ui.table>
