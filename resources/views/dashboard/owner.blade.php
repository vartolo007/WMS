{{-- Screen 6 (design batch 2): the owner dashboard. The warehouse chosen in the top bar narrows every number. --}}
<x-app-layout title="لوحة التحكم">
    <div class="flex flex-col gap-6">
        <x-ui.page-header title="لوحة التحكم" :subtitle="\App\Support\Format::date(today(), withWeekday: true).' · '.($selected?->name ?? 'كل المستودعات')">
            @if ($hasWarehouses)
                <x-ui.button :href="route('movements.create', ['type' => 'transfer'])" variant="secondary">+ نقل</x-ui.button>
                <x-ui.button :href="route('movements.create', ['type' => 'purchase'])" variant="secondary">+ فاتورة شراء</x-ui.button>
                <x-ui.button :href="route('movements.create', ['type' => 'sale'])">+ فاتورة بيع</x-ui.button>
            @endif
        </x-ui.page-header>

        @if (! $hasWarehouses)
            <x-ui.empty-state title="أضف أول مستودع" description="كل مستودع إله حساب دخول خاص، وبتشتغل عليه الفواتير والنقل.">
                <x-ui.button :href="route('warehouses.index')">+ مستودع جديد</x-ui.button>
            </x-ui.empty-state>
        @else
            {{-- Net sales today: the total, then each warehouse (only the chosen one when a warehouse is chosen) --}}
            @php($perWarehouse = $selected ? collect() : $sales['rows']->take(4))
            <div @class(['grid overflow-hidden rounded-xl border border-line bg-white', 'lg:grid-cols-[1.2fr_repeat(2,1fr)]' => $perWarehouse->count() > 1])>
                <div class="flex flex-col gap-1.5 bg-primary-light p-6">
                    <span class="text-sm text-muted">صافي مبيعات اليوم · {{ $selected ? $selected->name : 'الإجمالي' }}</span>
                    <span class="text-[34px] font-bold tabular-nums text-primary"><x-ui.money :amount="$sales['totals']['net']" /></span>
                    <span class="text-[13px] text-muted">{{ $movementCount }} حركة@if ($perWarehouse->count() > 1) بـ {{ $perWarehouse->count() }} مستودعات@endif · مبيعات {{ \App\Support\Format::money($sales['totals']['sales']) }} · مرتجعات {{ \App\Support\Format::money($sales['totals']['returns']) }}</span>
                </div>
                @foreach ($perWarehouse as $row)
                    <div class="flex flex-col gap-1.5 border-t border-line p-6 lg:border-s lg:border-t-0">
                        <span class="text-sm text-muted">{{ $row['warehouse']->name }}</span>
                        <span class="text-[26px] font-bold tabular-nums"><bdi dir="ltr">{{ \App\Support\Format::money($row['net']) }}</bdi></span>
                        <span class="text-[13px] tabular-nums text-muted">مبيعات {{ \App\Support\Format::money($row['sales']) }} · مرتجعات {{ \App\Support\Format::money($row['returns']) }}</span>
                    </div>
                @endforeach
            </div>

            <div class="grid gap-4 lg:grid-cols-[1.6fr_1fr]">
                {{-- Low stock --}}
                <div class="overflow-hidden rounded-xl border border-line bg-white">
                    <div class="flex items-center justify-between border-b border-line px-5 py-4">
                        <b class="text-[17px]">مواد منخفضة المخزون <span class="text-sm font-medium text-muted">· {{ $lowCount }}</span></b>
                        <a href="{{ route('reports.show', ['report' => \App\Enums\ReportKind::LowStock, 'split' => 1]) }}" class="font-medium text-primary hover:underline">تقرير الجرد</a>
                    </div>
                    @if ($low->isEmpty())
                        <div class="px-5 py-8 text-center text-sm text-muted">كل المواد فوق حدها الأدنى.</div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse text-sm">
                                <thead>
                                    <tr class="bg-canvas text-[13px] text-muted">
                                        <th class="px-5 py-2.5 text-start font-medium">المادة</th>
                                        @unless ($selected)<th class="px-3 py-2.5 text-start font-medium">المستودع</th>@endunless
                                        <th class="px-3 py-2.5 text-left font-medium">الرصيد</th>
                                        <th class="px-5 py-2.5 text-left font-medium">الحد الأدنى</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($low as $row)
                                        <tr>
                                            <td class="border-t border-line-soft px-5 py-3 font-semibold">{{ $row['product']->name }}</td>
                                            @unless ($selected)<td class="border-t border-line-soft px-3 py-3">{{ $row['warehouse']->name }}</td>@endunless
                                            <td class="whitespace-nowrap border-t border-line-soft px-3 py-3 text-left font-bold tabular-nums text-warning">{{ $row['product']->displayQuantity($row['remaining']) }} {{ $row['product']->unitLabel() }}</td>
                                            <td class="border-t border-line-soft px-5 py-3 text-left tabular-nums text-muted">{{ $row['product']->displayQuantity($row['min_stock']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if ($lowCount > $low->count())
                            <div class="border-t border-line-soft px-5 py-3 text-[13px] text-muted">و {{ $lowCount - $low->count() }} غيرها — بالتقرير الكامل.</div>
                        @endif
                    @endif
                </div>

                <div class="flex flex-col gap-4">
                    {{-- Subscription --}}
                    <div class="flex flex-col gap-2.5 rounded-xl border border-line bg-white p-5">
                        <div class="flex items-center justify-between">
                            <b class="text-[17px]">الاشتراك</b>
                            <x-ui.badge :tone="$subscription['status']->tone()">{{ $subscription['status']->label() }}</x-ui.badge>
                        </div>
                        @if ($subscription['endsAt'])
                            <div class="text-[15px] leading-7">
                                @if ($subscription['expired'])
                                    انتهت التجربة في <b class="tabular-nums">{{ \App\Support\Format::date($subscription['endsAt']) }}</b>
                                @else
                                    التجربة بتنتهي <b class="tabular-nums">{{ \App\Support\Format::date($subscription['endsAt']) }}</b> · باقي {{ \App\Support\Format::days($subscription['daysLeft']) }}
                                @endif
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-line-soft">
                                <div class="h-full rounded-full bg-amber-600" style="width: {{ $subscription['expired'] ? 100 : $subscription['usedPercent'] }}%"></div>
                            </div>
                            <div class="text-[13px] leading-7 text-muted">للتفعيل تواصل معنا: <span dir="ltr" class="inline-block" style="unicode-bidi: isolate">{{ config('wms.support.phone') }}</span></div>
                        @else
                            <div class="text-[15px] leading-7 text-muted">اشتراك شركتك فعّال.</div>
                        @endif
                    </div>

                    {{-- Stagnant --}}
                    <div class="flex flex-col gap-1.5 rounded-xl border border-line bg-white p-5">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted">مواد راكدة</span>
                            <x-ui.badge tone="slate" shape="tag">راكدة</x-ui.badge>
                        </div>
                        <span class="text-3xl font-bold tabular-nums">{{ $stagnantCount }}</span>
                        <span class="text-[13px] text-muted">ما انباعت من {{ \App\Support\Format::days($stagnantDays) }} أو أكتر ·
                            <a href="{{ route('reports.show', \App\Enums\ReportKind::Stagnant) }}" class="font-medium text-primary hover:underline">عرض بتقرير الراكدة</a></span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
