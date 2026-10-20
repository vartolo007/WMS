{{-- Stock reports (SRS 3.6.6): inventory, low, moved, stagnant. $result['rows'] is a paginator of arrays from ReportService::stock(). --}}
@php
    $rows = $result['rows'];
    $split = $filters->splitByWarehouse && $filters->warehouseId === null;
    $showOpening = $filters->dateFrom !== null;
    $isMoved = $report === \App\Enums\ReportKind::Moved;
    $isStagnant = $report === \App\Enums\ReportKind::Stagnant;
    $quantity = fn ($row, string $value) => $row['product']->displayQuantity($value);
@endphp

<div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-muted">
    <span>المستودع: <b class="text-ink">{{ $warehouseName }}</b>@if ($split) · مقسّم حسب المستودع @endif</span>
    <span>الرصيد حتى: <b class="text-ink tabular-nums">{{ \App\Support\Format::date(\Carbon\Carbon::parse($filters->dateTo)) }}</b></span>
    @if ($showOpening)<span>من: <b class="text-ink tabular-nums">{{ \App\Support\Format::date(\Carbon\Carbon::parse($filters->dateFrom)) }}</b></span>@endif
</div>

@if ($rows->isEmpty())
    <x-ui.empty-state title="ما في مواد بهالفلاتر" :description="$isStagnant ? 'ما في مادة إلها رصيد وما انباعت بهالفترة.' : 'جرّب تغيّر الفترة أو الفلاتر.'" />
@else
    <x-ui.table>
        <thead>
            <tr>
                <x-ui.th>المادة</x-ui.th>
                @if ($split)<x-ui.th>المستودع</x-ui.th>@endif
                @if ($showOpening)<x-ui.th numeric>أول المدة</x-ui.th>@endif
                <x-ui.th numeric>الداخل</x-ui.th>
                <x-ui.th numeric>الخارج</x-ui.th>
                @if ($isMoved)<x-ui.th numeric>المباع</x-ui.th>@endif
                <x-ui.th numeric>المتبقي</x-ui.th>
                <x-ui.th numeric>الحد الأدنى</x-ui.th>
                <x-ui.th>الحالة</x-ui.th>
                <x-ui.th>آخر بيع</x-ui.th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <x-ui.td><b class="font-semibold">{{ $row['product']->name }}</b> <span class="text-[13px] text-muted">· {{ $row['product']->unitLabel() }}</span></x-ui.td>
                    @if ($split)<x-ui.td>{{ $row['warehouse']->name }}</x-ui.td>@endif
                    @if ($showOpening)<x-ui.td numeric class="text-muted">{{ $quantity($row, $row['opening']) }}</x-ui.td>@endif
                    <x-ui.td numeric>{{ $quantity($row, $row['qty_in']) }}</x-ui.td>
                    <x-ui.td numeric>{{ $quantity($row, $row['qty_out']) }}</x-ui.td>
                    @if ($isMoved)<x-ui.td numeric class="font-semibold">{{ $quantity($row, $row['sold']) }}</x-ui.td>@endif
                    <x-ui.td numeric><span @class(['font-bold', 'text-warning' => $row['low']])>{{ $quantity($row, $row['remaining']) }}</span></x-ui.td>
                    <x-ui.td numeric class="text-muted">{{ $row['min_stock'] === null ? '—' : $quantity($row, $row['min_stock']) }}</x-ui.td>
                    <x-ui.td>
                        @if ($row['low'])
                            @if ($print) منخفضة @else <x-ui.badge tone="orange" shape="tag">منخفضة</x-ui.badge> @endif
                        @endif
                        @if ($row['stagnant'] && ($isStagnant || $showOpening))
                            @if ($print) راكدة @else <x-ui.badge tone="slate" shape="tag">راكدة</x-ui.badge> @endif
                        @endif
                    </x-ui.td>
                    <x-ui.td class="whitespace-nowrap">
                        @if ($row['last_sale'])
                            <span class="tabular-nums">{{ \App\Support\Format::date(\Carbon\Carbon::parse($row['last_sale'])) }}</span>
                            <span class="text-xs text-muted">· منذ {{ \App\Support\Format::days($row['days_since_sale']) }}</span>
                        @else
                            <span class="text-subtle">لم يُبع</span>
                        @endif
                    </x-ui.td>
                </tr>
            @endforeach
        </tbody>
    </x-ui.table>

    <span class="text-[13px] text-muted">
        المتبقي = أول المدة + الداخل − الخارج.
        @if ($isStagnant) راكدة = لها رصيد وما انباعت منها بالفترة المحددة. @endif
        @if ($report === \App\Enums\ReportKind::LowStock) منخفضة = الرصيد أقل من الحد الأدنى أو يساويه. @endif
    </span>

    @unless ($print)
        {{ $rows->links() }}
    @endunless
@endif
