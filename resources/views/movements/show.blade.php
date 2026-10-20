{{-- Screen 9 (design batch 4): movement details with the returns of an invoice, and the cancel action (FR-17). --}}
<x-app-layout :title="$movement->displayNumber()">
    <div class="flex flex-col gap-5">
        <div class="flex flex-col gap-2">
            <span class="text-sm text-muted"><a href="{{ route('movements.index') }}" class="font-medium text-primary hover:underline">الحركات</a> ‹ <span dir="ltr" class="inline-block">{{ $movement->displayNumber() }}</span></span>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 dir="ltr" @class(['text-2xl font-bold lg:text-[28px]', 'line-through' => $movement->isCancelled()])>{{ $movement->displayNumber() }}</h1>
                    <x-ui.badge :tone="$movement->type->tone()" shape="tag">{{ $movement->type->label() }}</x-ui.badge>
                    <x-ui.badge :tone="$movement->status->tone()">{{ $movement->status->label() }}</x-ui.badge>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($canReturn)
                        <x-ui.button variant="secondary" :href="route('movements.return', $movement)">إنشاء مرتجع</x-ui.button>
                    @endif
                    <x-ui.button variant="secondary" :href="route('movements.print', $movement)" target="_blank" rel="noopener">طباعة</x-ui.button>
                    @if ($canCancel)
                        <x-ui.confirm :title="'إلغاء الحركة '.$movement->displayNumber().'؟'" :action="route('movements.cancel', $movement)" confirm-label="نعم، ألغِ الحركة" cancel-label="تراجع">
                            <x-slot:trigger>
                                <x-ui.button type="button" variant="secondary" class="!border-danger-border !text-danger">إلغاء الحركة</x-ui.button>
                            </x-slot:trigger>
                            {{ $movement->type->cancelEffect($movement->itemsSummary(), $movement->warehouse->name, $movement->targetWarehouse?->name) }}
                        </x-ui.confirm>
                    @elseif ($cancelBlockedByReturn)
                        <span class="text-[13px] text-muted">ألغِ المرتجع أولاً لتقدر تلغي الفاتورة.</span>
                    @endif
                </div>
            </div>
        </div>

        @if ($errors->has('cancel'))
            <x-ui.alert>{{ $errors->first('cancel') }}</x-ui.alert>
        @endif

        @if ($movement->isCancelled())
            <div class="rounded-[10px] border border-line bg-canvas px-4 py-3 text-sm leading-7 text-muted">
                أُلغيت هالحركة بتاريخ <b class="tabular-nums text-ink">{{ \App\Support\Format::date($movement->cancelled_at) }} · {{ $movement->cancelled_at->format('H:i') }}</b>
                بواسطة <b class="text-ink">{{ $movement->canceller?->name }}</b>. محفوظة للتدقيق، ومستثناة من الأرصدة والتقارير.
            </div>
        @endif

        <dl class="grid grid-cols-2 gap-4 rounded-xl border border-line bg-white p-5 text-[15px] sm:grid-cols-3 lg:grid-cols-5">
            <div class="flex flex-col gap-1"><dt class="text-[13px] text-muted">التاريخ</dt><dd class="font-semibold tabular-nums">{{ \App\Support\Format::date($movement->date) }}</dd></div>
            <div class="flex flex-col gap-1"><dt class="text-[13px] text-muted">المستودع</dt><dd class="font-semibold">{{ $movement->warehouse->name }}</dd></div>
            <div class="flex flex-col gap-1"><dt class="text-[13px] text-muted">{{ $movement->type->partyLabel() }}</dt><dd class="font-semibold">{{ $movement->type->counterpartLabel($movement->party?->name, $movement->targetWarehouse?->name) }}</dd></div>
            @if ($movement->relatedMovement)
                <div class="flex flex-col gap-1"><dt class="text-[13px] text-muted">الفاتورة الأصلية</dt><dd class="font-semibold"><a href="{{ route('movements.show', $movement->relatedMovement) }}" dir="ltr" class="inline-block text-primary hover:underline">{{ $movement->relatedMovement->displayNumber() }}</a></dd></div>
            @endif
            @if ($movement->document_no)
                <div class="flex flex-col gap-1"><dt class="text-[13px] text-muted">رقم الوثيقة</dt><dd class="font-semibold" dir="ltr">{{ $movement->document_no }}</dd></div>
            @endif
            <div class="flex flex-col gap-1"><dt class="text-[13px] text-muted">سجّلها</dt><dd class="font-semibold">{{ $movement->creator->name }}</dd></div>
            @if ($movement->type !== \App\Enums\MovementType::Transfer)
                <div class="flex flex-col gap-1"><dt class="text-[13px] text-muted">الدفع</dt><dd class="font-semibold">نقدي</dd></div>
            @endif
        </dl>

        <div @class(['grid items-start gap-5', 'lg:grid-cols-[minmax(0,1fr)_400px]' => $movement->type->returnType() !== null])>
        <div class="overflow-hidden rounded-xl border border-line bg-white">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-[15px]">
                    <thead>
                        <tr>
                            <x-ui.th>الرمز</x-ui.th>
                            <x-ui.th>اسم المادة</x-ui.th>
                            <x-ui.th numeric>الكمية</x-ui.th>
                            @if ($returnLines->isNotEmpty())<x-ui.th numeric>مرتجع</x-ui.th>@endif
                            <x-ui.th numeric>سعر الوحدة</x-ui.th>
                            <x-ui.th numeric>القيمة</x-ui.th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($movement->items as $item)
                            <tr>
                                <x-ui.td class="text-muted"><span dir="ltr" class="inline-block">{{ $item->product->barcode ?? '—' }}</span></x-ui.td>
                                <x-ui.td><b class="font-semibold">{{ $item->product->name }}</b> <span class="text-[13px] text-muted">· {{ $item->product->unitLabel() }}</span></x-ui.td>
                                <x-ui.td numeric>{{ \App\Support\Format::quantity($item->quantity) }}</x-ui.td>
                                @if ($returnLines->isNotEmpty())
                                    @php($returned = $returnLines->get($item->product_id)['returned'] ?? '0')
                                    <x-ui.td numeric>
                                        @if (\App\Support\Money::isPositive($returned))
                                            <span class="font-semibold text-red-700">{{ $item->product->displayQuantity($returned) }}</span>
                                        @else
                                            <span class="text-subtle">—</span>
                                        @endif
                                    </x-ui.td>
                                @endif
                                <x-ui.td numeric>{{ \App\Support\Format::money($item->unit_price) }}</x-ui.td>
                                <x-ui.td numeric class="font-semibold">{{ \App\Support\Format::money($item->line_total) }}</x-ui.td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="ms-auto flex w-full flex-col gap-2.5 border-t border-line p-5 tabular-nums sm:w-[360px]">
                @if ($movement->type === \App\Enums\MovementType::Transfer)
                    {{-- A transfer moves no cash: the value is only an estimate at the last purchase price (SRS 3.4.7). --}}
                    <div class="flex items-end justify-between">
                        <span class="flex flex-col"><b>القيمة التقديرية</b><span class="text-[13px] text-muted">بآخر سعر شراء، للتقييم فقط</span></span>
                        <b class="text-[22px] font-bold"><x-ui.money :amount="$movement->subtotal" /></b>
                    </div>
                @else
                    <div class="flex justify-between text-[15px]"><span class="text-muted">المجموع</span><b><x-ui.money :amount="$movement->subtotal" /></b></div>
                    <div class="flex justify-between text-[15px]"><span class="text-muted">الحسم</span><b dir="ltr">−<x-ui.money :amount="$movement->discount" /></b></div>
                    <div class="h-px bg-line"></div>
                    <div class="flex items-end justify-between">
                        <span class="flex flex-col"><b>الصافي</b><span class="text-[13px] text-muted">{{ $movement->type->netLabel() }}</span></span>
                        <b class="text-[28px] font-bold text-primary"><x-ui.money :amount="$movement->total" /></b>
                    </div>
                @endif
            </div>
        </div>

        @if ($movement->type->returnType() !== null)
            <div class="overflow-hidden rounded-xl border border-line bg-white">
                <div class="flex items-center justify-between border-b border-line px-5 py-3.5">
                    <b class="text-base">المرتجعات على هالفاتورة</b>
                    <span class="text-[13px] text-muted">{{ $returns->count() }}</span>
                </div>
                @forelse ($returns as $return)
                    <div @class(['flex items-center gap-3 border-t border-line-soft px-5 py-3.5 text-[15px] tabular-nums first:border-t-0', 'text-subtle line-through' => $return->isCancelled()])>
                        <a href="{{ route('movements.show', $return) }}" dir="ltr" class="font-semibold text-primary hover:underline">{{ $return->displayNumber() }}</a>
                        <span class="text-sm text-muted">{{ \App\Support\Format::date($return->date) }}</span>
                        <span class="flex-1"></span>
                        <b><x-ui.money :amount="$return->total" /></b>
                    </div>
                @empty
                    <div class="px-5 py-4 text-sm text-muted">ما في مرتجعات على هالفاتورة.</div>
                @endforelse
                <div class="border-t border-line-soft bg-canvas px-5 py-3 text-[13px] leading-7 text-muted">إذا الفاتورة عليها مرتجع مؤكد، لازم تلغي المرتجع أولاً قبل إلغائها.</div>
            </div>
        @endif
        </div>
    </div>
</x-app-layout>
