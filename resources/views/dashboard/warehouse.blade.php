{{-- Screen 5 (design batch 2): the warehouse dashboard. Numbers come from DashboardService (the report engine). --}}
<x-app-layout title="لوحة التحكم">
    <div class="flex flex-col gap-6">
        <x-ui.page-header title="لوحة التحكم" :subtitle="\App\Support\Format::date(today(), withWeekday: true).' · '.auth()->user()->name">
            <x-ui.button :href="route('movements.create', ['type' => 'transfer'])" variant="secondary">+ نقل</x-ui.button>
            <x-ui.button :href="route('movements.create', ['type' => 'purchase'])" variant="secondary">+ فاتورة شراء</x-ui.button>
            <x-ui.button :href="route('movements.create', ['type' => 'sale'])">+ فاتورة بيع</x-ui.button>
        </x-ui.page-header>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="flex flex-col gap-1.5 rounded-xl border border-line bg-white p-5">
                <span class="text-sm text-muted">صافي مبيعات اليوم</span>
                <span class="text-3xl font-bold tabular-nums"><x-ui.money :amount="$sales['net']" /></span>
                <span class="text-[13px] tabular-nums text-muted">مبيعات <x-ui.money :amount="$sales['sales']" /> − مرتجعات <x-ui.money :amount="$sales['returns']" /></span>
            </div>
            <div class="flex flex-col gap-1.5 rounded-xl border border-line bg-white p-5">
                <span class="text-sm text-muted">حركات اليوم</span>
                <span class="text-3xl font-bold tabular-nums">{{ $movementCount }}</span>
                <span class="text-[13px] text-muted">{{ $movementBreakdown !== '' ? $movementBreakdown : 'ما في حركات لسا' }}</span>
            </div>
            <a href="{{ route('reports.show', \App\Enums\ReportKind::LowStock) }}" class="flex flex-col gap-1.5 rounded-xl border border-line bg-white p-5 hover:bg-canvas">
                <span class="text-sm text-muted">مواد منخفضة المخزون</span>
                <span @class(['text-3xl font-bold tabular-nums', 'text-warning' => $lowCount > 0])>{{ $lowCount }}</span>
                <span class="text-[13px] text-muted">تحت الحد الأدنى بمستودعك</span>
            </a>
        </div>

        <div class="grid gap-4 lg:grid-cols-[1.5fr_1fr]">
            <div class="overflow-hidden rounded-xl border border-line bg-white">
                <div class="flex items-center justify-between border-b border-line px-5 py-4">
                    <b class="text-[17px]">حركات اليوم</b>
                    <a href="{{ route('movements.index') }}" class="font-medium text-primary hover:underline">كل الحركات</a>
                </div>
                @if ($movements->isEmpty())
                    <div class="px-5 py-8 text-center text-sm text-muted">ما في حركات اليوم. ابدأ بفاتورة بيع أو شراء.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-sm">
                            <thead>
                                <tr class="bg-canvas text-[13px] text-muted">
                                    <th class="px-5 py-2.5 text-start font-medium">الرقم</th>
                                    <th class="px-3 py-2.5 text-start font-medium">الوقت</th>
                                    <th class="px-3 py-2.5 text-start font-medium">النوع</th>
                                    <th class="px-3 py-2.5 text-start font-medium">الطرف</th>
                                    <th class="px-5 py-2.5 text-left font-medium">الصافي</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($movements as $movement)
                                    <tr>
                                        <td class="whitespace-nowrap border-t border-line-soft px-5 py-3 font-medium"><a href="{{ route('movements.show', $movement) }}" dir="ltr" class="inline-block text-primary hover:underline">{{ $movement->displayNumber() }}</a></td>
                                        <td class="border-t border-line-soft px-3 py-3 tabular-nums text-muted">{{ $movement->created_at->format('H:i') }}</td>
                                        <td class="border-t border-line-soft px-3 py-3"><x-ui.badge :tone="$movement->type->tone()" shape="tag">{{ $movement->type->label() }}</x-ui.badge></td>
                                        <td class="border-t border-line-soft px-3 py-3">{{ $movement->type->counterpartLabel($movement->party?->name, $movement->targetWarehouse?->name) }}</td>
                                        <td class="whitespace-nowrap border-t border-line-soft px-5 py-3 text-left font-semibold tabular-nums">
                                            @if ($movement->type === \App\Enums\MovementType::Transfer)
                                                <span class="text-subtle">—</span>
                                            @else
                                                <bdi dir="ltr">{{ $movement->type->isReturn() ? '−' : '' }}<x-ui.money :amount="$movement->total" /></bdi>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="overflow-hidden rounded-xl border border-line bg-white">
                <div class="flex items-center justify-between border-b border-line px-5 py-4">
                    <b class="text-[17px]">مواد منخفضة المخزون</b>
                    <a href="{{ route('products.index', ['low' => 1]) }}" class="font-medium text-primary hover:underline">دليل المواد</a>
                </div>
                @forelse ($low as $row)
                    <div class="flex items-center gap-3 border-b border-line-soft px-5 py-3.5">
                        <div class="flex flex-1 flex-col gap-0.5">
                            <b class="text-[15px] font-semibold">{{ $row['product']->name }}</b>
                            <span class="text-[13px] text-muted">@if ($row['product']->location){{ $row['product']->location }} · @endif الحد الأدنى {{ $row['product']->displayQuantity($row['min_stock']) }}</span>
                        </div>
                        <span class="text-[17px] font-bold tabular-nums text-warning">{{ $row['product']->displayQuantity($row['remaining']) }} <span class="text-[13px] font-normal text-muted">{{ $row['product']->unitLabel() }}</span></span>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-sm text-muted">كل المواد فوق حدها الأدنى.</div>
                @endforelse
                @if ($lowCount > $low->count())
                    <div class="px-5 py-3 text-[13px] text-muted">و {{ $lowCount - $low->count() }} مواد غيرها — <a href="{{ route('reports.show', \App\Enums\ReportKind::LowStock) }}" class="font-medium text-primary hover:underline">التقرير الكامل</a></div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
