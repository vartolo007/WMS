{{-- Screen 7, return tabs, step 2 (design batch 3): return part of the original invoice. --}}
@php
    $isPurchaseReturn = $type === \App\Enums\MovementType::PurchaseReturn;
    $symbol = auth()->user()->company->currency_symbol;
    $locked = 'flex h-10 items-center rounded-lg border border-line bg-canvas px-3 text-[15px]';
    $rowColumns = $isPurchaseReturn
        ? 'lg:grid-cols-[100px_minmax(0,1fr)_100px_120px_110px_140px_140px_150px]'
        : 'lg:grid-cols-[100px_minmax(0,1fr)_100px_120px_140px_140px_150px]';
    $note = $isPurchaseReturn
        ? 'المرتجع ما بيتجاوز الكمية الأصلية ناقص اللي انرجع قبل، ولا رصيد المستودع الحالي. الحسم الأصلي بينخصم نسبياً.'
        : 'السعر ثابت من الفاتورة الأصلية. الكمية المتاحة = الأصلية − المرتجع سابقاً. الحسم الأصلي بينخصم نسبياً.';

    $config = $lines->map(function (array $line) use ($balances) {
        $product = $line['product'];
        $balance = $balances[$line['product_id']] ?? null;

        return [
            'productId' => $line['product_id'],
            'name' => $product->name,
            'barcode' => $product->barcode,
            'unit' => $product->unitLabel(),
            'allowsFraction' => $product->allowsFraction(),
            'originalText' => $product->displayQuantity($line['quantity']),
            'returnable' => $line['returnable'],
            'returnableText' => $product->displayQuantity($line['returnable']),
            'exhausted' => ! \App\Support\Money::isPositive($line['returnable']),
            'price' => $line['unit_price'],
            'priceText' => \App\Support\Format::money($line['unit_price']),
            'balance' => $balance,
            'balanceText' => $balance === null ? null : $product->displayQuantity($balance),
        ];
    })->values();
@endphp

<x-app-layout title="حركة جديدة">
    <div
        x-data="returnForm({{ Js::from([
            'storeUrl' => route('movements.return.store', $original),
            'date' => today()->toDateString(),
            'originalSubtotal' => (string) $original->subtotal,
            'originalTotal' => (string) $original->total,
            'checksStock' => $type->reducesSourceStock(),
            'lines' => $config,
        ]) }})"
        class="flex flex-col gap-5 pb-28 lg:pb-0"
    >
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-bold lg:text-[28px]">حركة جديدة</h1>
            @include('movements.partials.type-tabs', ['active' => $type])
        </div>

        {{-- Header: everything but the date comes from the original invoice --}}
        <div class="grid grid-cols-2 gap-4 rounded-xl border border-line bg-white p-5 lg:grid-cols-5">
            <label class="flex flex-col gap-1.5">
                <span class="text-[13px] font-medium">التاريخ</span>
                <input type="date" x-model="date" class="h-10 w-full rounded-lg border border-line-strong bg-white px-3 text-[15px] text-ink focus:border-primary focus:outline-none focus:ring-[3px] focus:ring-primary-ring">
            </label>
            <div class="flex flex-col gap-1.5">
                <span class="text-[13px] font-medium text-muted">رقم الحركة</span>
                <span class="{{ $locked }} justify-between text-muted"><span class="text-[13px]">يتولّد عند الحفظ</span><span class="text-xs">تلقائي</span></span>
            </div>
            <div class="flex flex-col gap-1.5">
                <span class="text-[13px] font-medium text-muted">{{ $type->partyLabel() }}</span>
                <span class="{{ $locked }}">{{ $original->type->counterpartLabel($original->party?->name, null) }}</span>
            </div>
            <div class="flex flex-col gap-1.5">
                <span class="text-[13px] font-medium text-muted">المستودع</span>
                <span class="{{ $locked }}">{{ $original->warehouse->name }}</span>
            </div>
            <div class="col-span-2 flex flex-col gap-1.5 lg:col-span-1">
                <span class="text-[13px] font-medium text-muted">الفاتورة الأصلية</span>
                <a href="{{ route('movements.show', $original) }}" class="{{ $locked }} justify-between gap-2 hover:bg-white">
                    <b dir="ltr">{{ $original->displayNumber() }}</b>
                    <span class="text-xs text-muted tabular-nums">{{ \App\Support\Format::date($original->date) }}</span>
                </a>
            </div>
        </div>

        {{-- Lines --}}
        <div class="overflow-hidden rounded-xl border border-line bg-white">
            <div class="hidden items-center gap-3 border-b border-line bg-canvas px-5 py-2.5 text-[13px] font-medium text-muted lg:grid {{ $rowColumns }}">
                <span>الرمز</span>
                <span>اسم المادة</span>
                <span class="text-left">الكمية الأصلية</span>
                <span class="text-left">المتاح للإرجاع</span>
                @if ($isPurchaseReturn)<span class="text-left">الرصيد المتوفر</span>@endif
                <span class="text-left">الكمية المرتجعة</span>
                <span class="text-left">سعر الوحدة</span>
                <span class="text-left">القيمة</span>
            </div>

            <template x-for="line in lines" :key="line.productId">
                <div class="grid grid-cols-2 items-start gap-3 border-b border-line-soft px-4 py-3 tabular-nums lg:px-5 {{ $rowColumns }}"
                     :class="lineError(line) ? 'bg-danger-soft' : ''">
                    <span class="hidden pt-2 text-sm text-muted lg:block" dir="ltr" x-text="line.barcode ?? '—'"></span>

                    <div class="col-span-2 flex flex-col lg:col-span-1 lg:pt-1">
                        <b class="text-[15px] font-semibold" x-text="line.name"></b>
                        <span class="text-xs text-muted" x-text="line.unit"></span>
                    </div>

                    <div class="flex items-center justify-between lg:block lg:pt-2 lg:text-left">
                        <span class="text-xs text-muted lg:hidden">الكمية الأصلية</span>
                        <span class="text-muted" x-text="line.originalText"></span>
                    </div>

                    <div class="flex items-center justify-between lg:block lg:pt-2 lg:text-left">
                        <span class="text-xs text-muted lg:hidden">المتاح للإرجاع</span>
                        <button type="button" @click="fillAll(line)" :disabled="line.exhausted" title="إرجاع كل المتاح"
                                class="font-semibold text-primary underline-offset-2 hover:underline disabled:text-subtle disabled:no-underline" x-text="line.returnableText"></button>
                    </div>

                    @if ($isPurchaseReturn)
                        <div class="flex items-center justify-between lg:block lg:pt-2 lg:text-left">
                            <span class="text-xs text-muted lg:hidden">الرصيد المتوفر</span>
                            <span class="font-semibold" x-text="line.balanceText ?? '—'"></span>
                        </div>
                    @endif

                    <label class="col-span-2 flex flex-col gap-1 lg:col-span-1">
                        <span class="text-xs text-muted lg:hidden">الكمية المرتجعة</span>
                        <input x-model="line.quantity" @input="line.errors = {}; formError = ''" :disabled="line.exhausted"
                               inputmode="decimal" dir="ltr" autocomplete="off" placeholder="0"
                               :class="lineError(line) ? 'border-danger' : 'border-line-strong'"
                               class="h-10 w-full rounded-lg border bg-white px-2.5 text-left text-[15px] focus:border-primary focus:outline-none focus:ring-[3px] focus:ring-primary-ring disabled:bg-canvas">
                        <span x-show="lineError(line)" x-text="lineError(line)" class="text-xs font-semibold text-danger"></span>
                    </label>

                    <div class="flex items-center justify-between lg:block lg:pt-2 lg:text-left">
                        <span class="text-xs text-muted lg:hidden">سعر الوحدة</span>
                        <span class="text-muted" x-text="line.priceText"></span>
                    </div>

                    <div class="flex items-center justify-between lg:block lg:pt-2 lg:text-left">
                        <span class="text-xs text-muted lg:hidden">القيمة</span>
                        <span class="text-[15px] font-bold" x-text="lineValueText(line)"></span>
                    </div>
                </div>
            </template>
        </div>

        {{-- Totals --}}
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start">
            <div class="flex flex-1 flex-col gap-3">
                <div x-show="formError" x-text="formError" role="alert"
                     class="rounded-[10px] border border-danger-border bg-danger-soft px-3.5 py-3 text-sm leading-6 text-red-800"></div>
                <p class="text-[13px] leading-7 text-muted">{{ $note }}</p>
                <p class="hidden text-xs text-subtle lg:block"><code class="rounded bg-line-soft px-1.5 py-px">Ctrl+S</code> حفظ</p>
            </div>

            <div class="flex flex-col gap-3 rounded-xl border border-line bg-white p-5 tabular-nums lg:w-[440px] lg:flex-none">
                <div class="flex justify-between text-[15px]"><span class="text-muted">المجموع بالأسعار الأصلية</span><b x-text="subtotalText"></b></div>
                <div class="flex justify-between text-[15px]"><span class="text-muted">حصة الحسم الأصلي</span><b dir="ltr" x-text="'−' + discountText"></b></div>
                <div class="h-px bg-line"></div>
                <div class="flex items-end justify-between">
                    <span class="flex flex-col"><b class="text-base">الصافي</b><span class="text-[13px] text-muted">{{ $type->netLabel() }}</span></span>
                    <span class="text-[34px] font-bold leading-tight text-primary"><span x-text="netText"></span>@if (filled($symbol)) <span class="text-[15px] font-medium text-muted">{{ $symbol }}</span>@endif</span>
                </div>
                <div class="mt-1 hidden gap-2 lg:flex">
                    <x-ui.button type="button" size="lg" class="flex-1" ::disabled="saving" @click="save()"><span x-text="saving ? 'جاري الحفظ…' : 'حفظ الحركة'"></span></x-ui.button>
                    <x-ui.button size="lg" variant="secondary" :href="route('movements.show', $original)">إلغاء</x-ui.button>
                </div>
            </div>
        </div>

        {{-- Mobile: net + save stay in reach --}}
        <div class="fixed inset-x-0 bottom-0 z-20 flex items-center gap-3 border-t border-line bg-white px-4 py-3 tabular-nums lg:hidden">
            <div class="flex flex-1 flex-col leading-tight">
                <span class="text-xs text-muted">الصافي · {{ $type->netLabel() }}</span>
                <span class="text-xl font-bold text-primary"><span x-text="netText"></span>@if (filled($symbol)) <span class="text-xs font-medium text-muted">{{ $symbol }}</span>@endif</span>
            </div>
            <x-ui.button type="button" size="lg" ::disabled="saving" @click="save()"><span x-text="saving ? 'جاري الحفظ…' : 'حفظ'"></span></x-ui.button>
        </div>
    </div>
</x-app-layout>
