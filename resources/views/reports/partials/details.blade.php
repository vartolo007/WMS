{{-- Every operation row of the filter, oldest first. $result: rows (paginator) + totals per type. $print: plain text, no links or badges. --}}
@php
    $qty = fn (string $value) => \App\Support\Money::isPositive($value) ? \App\Support\Format::quantity($value) : '—';
    $rows = $result['rows'];
@endphp

@if ($rows->isEmpty())
    <x-ui.empty-state title="ما في نتائج بهالفلاتر" description="جرّب فترة أوسع أو غيّر الفلاتر." />
@else
    <x-ui.table>
        <thead>
            <tr>
                <x-ui.th>التاريخ</x-ui.th>
                <x-ui.th>النوع</x-ui.th>
                <x-ui.th>الرقم</x-ui.th>
                <x-ui.th>المستودع</x-ui.th>
                <x-ui.th>الطرف / المقابل</x-ui.th>
                <x-ui.th>المادة</x-ui.th>
                <x-ui.th numeric>إدخال</x-ui.th>
                <x-ui.th numeric>إخراج</x-ui.th>
                <x-ui.th numeric>الصافي</x-ui.th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <x-ui.td class="whitespace-nowrap text-muted">{{ \App\Support\Format::date(\Carbon\Carbon::parse($row['date'])) }}</x-ui.td>
                    <x-ui.td class="whitespace-nowrap">
                        @if ($print)
                            {{ $row['type']->label() }}
                        @else
                            <x-ui.badge :tone="$row['type']->tone()" shape="tag">{{ $row['type']->label() }}</x-ui.badge>
                        @endif
                    </x-ui.td>
                    <x-ui.td class="whitespace-nowrap font-medium">
                        @if ($print)
                            <span dir="ltr" class="inline-block">{{ $row['number'] }}</span>
                        @else
                            <a href="{{ route('movements.show', $row['movement_id']) }}" dir="ltr" class="inline-block text-primary hover:underline">{{ $row['number'] }}</a>
                        @endif
                    </x-ui.td>
                    <x-ui.td>{{ $row['warehouse_name'] }}</x-ui.td>
                    <x-ui.td>{{ $row['counterpart'] }}</x-ui.td>
                    <x-ui.td><b class="font-semibold">{{ $row['product_name'] }}</b> <span class="text-[13px] text-muted">· {{ \App\Support\Units::label($row['product_unit']) }}</span></x-ui.td>
                    <x-ui.td numeric class="font-semibold">{{ $qty($row['qty_in']) }}</x-ui.td>
                    <x-ui.td numeric class="font-semibold">{{ $qty($row['qty_out']) }}</x-ui.td>
                    <x-ui.td numeric>{{ \App\Support\Format::money($row['net_value']) }}</x-ui.td>
                </tr>
            @endforeach
        </tbody>
    </x-ui.table>

    @if (! empty($result['totals']))
        <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-muted">
            @foreach ($result['totals'] as $type => $total)
                <span>{{ \App\Enums\MovementType::from($type)->label() }}: <b class="text-ink">{{ $total['movements'] }}</b> حركة ·
                    <b class="text-ink tabular-nums"><x-ui.money :amount="$total['net_value']" /></b></span>
            @endforeach
        </div>
    @endif

    @unless ($print)
        {{ $rows->links() }}
    @endunless
@endif
