{{-- Screen 9 (design batch 4): movement details. Returns on an invoice (Phase 4) and cancel (Phase 6) come later. --}}
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
                <x-ui.button variant="secondary" :href="route('movements.print', $movement)" target="_blank" rel="noopener">طباعة</x-ui.button>
            </div>
        </div>

        <dl class="grid grid-cols-2 gap-4 rounded-xl border border-line bg-white p-5 text-[15px] sm:grid-cols-3 lg:grid-cols-5">
            <div class="flex flex-col gap-1"><dt class="text-[13px] text-muted">التاريخ</dt><dd class="font-semibold tabular-nums">{{ \App\Support\Format::date($movement->date) }}</dd></div>
            <div class="flex flex-col gap-1"><dt class="text-[13px] text-muted">المستودع</dt><dd class="font-semibold">{{ $movement->warehouse->name }}</dd></div>
            <div class="flex flex-col gap-1"><dt class="text-[13px] text-muted">{{ $movement->type->partyLabel() }}</dt><dd class="font-semibold">{{ $movement->type->counterpartLabel($movement->party?->name, $movement->targetWarehouse?->name) }}</dd></div>
            @if ($movement->document_no)
                <div class="flex flex-col gap-1"><dt class="text-[13px] text-muted">رقم الوثيقة</dt><dd class="font-semibold" dir="ltr">{{ $movement->document_no }}</dd></div>
            @endif
            <div class="flex flex-col gap-1"><dt class="text-[13px] text-muted">سجّلها</dt><dd class="font-semibold">{{ $movement->creator->name }}</dd></div>
            <div class="flex flex-col gap-1"><dt class="text-[13px] text-muted">الدفع</dt><dd class="font-semibold">نقدي</dd></div>
        </dl>

        <div class="overflow-hidden rounded-xl border border-line bg-white">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-[15px]">
                    <thead>
                        <tr>
                            <x-ui.th>الرمز</x-ui.th>
                            <x-ui.th>اسم المادة</x-ui.th>
                            <x-ui.th numeric>الكمية</x-ui.th>
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
                                <x-ui.td numeric>{{ \App\Support\Format::money($item->unit_price) }}</x-ui.td>
                                <x-ui.td numeric class="font-semibold">{{ \App\Support\Format::money($item->line_total) }}</x-ui.td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="ms-auto flex w-full flex-col gap-2.5 border-t border-line p-5 tabular-nums sm:w-[360px]">
                <div class="flex justify-between text-[15px]"><span class="text-muted">المجموع</span><b><x-ui.money :amount="$movement->subtotal" /></b></div>
                <div class="flex justify-between text-[15px]"><span class="text-muted">الحسم</span><b dir="ltr">−<x-ui.money :amount="$movement->discount" /></b></div>
                <div class="h-px bg-line"></div>
                <div class="flex items-end justify-between">
                    <span class="flex flex-col"><b>الصافي</b><span class="text-[13px] text-muted">{{ $movement->type->netLabel() }}</span></span>
                    <b class="text-[28px] font-bold text-primary"><x-ui.money :amount="$movement->total" /></b>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
