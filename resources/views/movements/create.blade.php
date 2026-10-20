{{-- Screen 7 (design batch 3): new purchase / sale invoice or transfer. Returns have their own screens (returns/, movements/return). --}}
@php
    $isSale = $type === \App\Enums\MovementType::Sale;
    $isTransfer = $type === \App\Enums\MovementType::Transfer;
    $showBalance = $type->reducesSourceStock();
    $isOwner = auth()->user()->isOwner();
    $symbol = auth()->user()->company->currency_symbol;

    $select = 'h-10 w-full appearance-none rounded-lg border border-line-strong bg-white ps-3 pe-9 text-[15px] text-ink focus:border-primary focus:outline-none focus:ring-[3px] focus:ring-primary-ring';
    $field = 'h-10 w-full rounded-lg border border-line-strong bg-white px-3 text-[15px] text-ink focus:border-primary focus:outline-none focus:ring-[3px] focus:ring-primary-ring';
    $locked = 'flex h-10 items-center rounded-lg border border-line bg-canvas px-3 text-[15px]';
    $rowColumns = match (true) {
        $isTransfer => 'lg:grid-cols-[110px_minmax(0,1fr)_150px_170px_48px]',
        $isSale => 'lg:grid-cols-[110px_minmax(0,1fr)_130px_150px_170px_170px_48px]',
        default => 'lg:grid-cols-[110px_minmax(0,1fr)_150px_170px_170px_48px]',
    };
    $note = match (true) {
        $isTransfer => 'الكمية بتطلع من مستودعك وبتدخل المستودع المستقبِل فوراً، بدون قيمة نقدية. الحفظ ممنوع إذا الكمية أكبر من الرصيد.',
        $isSale => 'السعر بيتعبّى بالسعر المقترح وفيك تعدّله. الحفظ ممنوع إذا الكمية أكبر من الرصيد.',
        default => 'الكمية بتنضاف لرصيد المستودع عند الحفظ. سعر الشراء الجديد بيحدّث السعر المقترح حسب نسبة التسعير.',
    };
@endphp

<x-app-layout title="حركة جديدة">
    @if ($warehouses->isEmpty())
        <div class="flex flex-col gap-6">
            <x-ui.page-header title="حركة جديدة" />
            <x-ui.empty-state title="أضف أول مستودع" description="كل حركة بتنحفظ على مستودع، وكل مستودع إله حساب دخول خاص.">
                <x-ui.button :href="route('warehouses.index')">+ مستودع جديد</x-ui.button>
            </x-ui.empty-state>
        </div>
    @else
        <div
            x-data="invoiceForm({{ Js::from([
                'type' => $type->value,
                'lookupUrl' => route('products.lookup'),
                'storeUrl' => route('movements.store'),
                'productsUrl' => route('products.store'),
                'partiesUrl' => route('parties.store'),
                'partyType' => $type->partyType()?->value,
                'parties' => $parties->map(fn ($party) => ['id' => $party->id, 'name' => $party->name])->values(),
                'units' => \App\Support\Units::options(),
                'warehouseId' => $warehouseId,
                'date' => today()->toDateString(),
                'checksStock' => $type->reducesSourceStock(),
                'warehouseNames' => $targetWarehouses->pluck('name', 'id'),
                'rememberWarehouse' => $isOwner,
            ]) }})"
            class="flex flex-col gap-5 pb-28 lg:pb-0"
        >
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h1 class="text-2xl font-bold lg:text-[28px]">حركة جديدة</h1>
                @include('movements.partials.type-tabs', ['active' => $type])
            </div>

            {{-- Header --}}
            <div class="grid grid-cols-2 gap-4 rounded-xl border border-line bg-white p-5 lg:grid-cols-4">
                <label class="flex flex-col gap-1.5">
                    <span class="text-[13px] font-medium">التاريخ</span>
                    <input type="date" x-model="date" class="{{ $field }}">
                </label>

                <div class="flex flex-col gap-1.5">
                    <span class="text-[13px] font-medium text-muted">رقم الحركة</span>
                    <span class="{{ $locked }} justify-between text-muted"><span class="text-[13px]">يتولّد عند الحفظ</span><span class="text-xs">تلقائي</span></span>
                </div>

                @if ($type === \App\Enums\MovementType::Purchase)
                    <label class="col-span-2 flex flex-col gap-1.5 lg:col-span-1">
                        <span class="text-[13px] font-medium">رقم الوثيقة <span class="font-normal text-muted">(فاتورة المورد)</span></span>
                        <input x-model="documentNo" maxlength="100" placeholder="مثال: SH-2291" dir="ltr" class="{{ $field }} text-right">
                        <span x-show="errors.document_no" x-text="errors.document_no" class="text-[13px] font-medium text-danger"></span>
                    </label>
                @endif

                @unless ($isTransfer)
                <label class="col-span-2 flex flex-col gap-1.5 lg:col-span-1">
                    <span class="text-[13px] font-medium">{{ $type->partyLabel() }}{{ $isSale ? ' (اختياري)' : ' *' }}</span>
                    <div class="flex gap-2">
                        <select x-model="partyId" style="background-position: left 0.75rem center" :class="errors.party_id ? 'border-danger bg-danger-soft' : ''" class="{{ $select }} min-w-0 flex-1">
                            <option value="">{{ $isSale ? 'بدون عميل (بيع نقدي)' : 'اختر المورد…' }}</option>
                            <template x-for="party in parties" :key="party.id"><option :value="party.id" x-text="party.name"></option></template>
                        </select>
                        <button type="button" @click.prevent="openQuickParty()" title="إضافة {{ $type->partyLabel() }} جديد" aria-label="إضافة {{ $type->partyLabel() }} جديد"
                                class="h-10 w-10 flex-none rounded-lg border border-line-strong bg-white text-xl font-semibold text-primary hover:bg-canvas">+</button>
                    </div>
                    <span x-show="errors.party_id" x-text="errors.party_id" class="text-[13px] font-medium text-danger"></span>
                </label>
                @endunless

                <label class="col-span-2 flex flex-col gap-1.5 lg:col-span-1">
                    <span class="text-[13px] font-medium">{{ $isTransfer ? 'المستودع المرسِل' : 'المستودع' }}</span>
                    @if ($isOwner)
                        <select x-model="warehouseId" @change="warehouseChanged()" style="background-position: left 0.75rem center" :class="errors.warehouse_id ? 'border-danger bg-danger-soft' : ''" class="{{ $select }}">
                            <option value="">اختر المستودع…</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                    @else
                        <span class="{{ $locked }}">{{ auth()->user()->name }}</span>
                    @endif
                    <span x-show="errors.warehouse_id" x-text="errors.warehouse_id" class="text-[13px] font-medium text-danger"></span>
                </label>

                @if ($isTransfer)
                    <label class="col-span-2 flex flex-col gap-1.5 lg:col-span-1">
                        <span class="text-[13px] font-medium">المستودع المستقبِل *</span>
                        <select x-model="targetWarehouseId" style="background-position: left 0.75rem center" :class="errors.target_warehouse_id ? 'border-danger bg-danger-soft' : ''" class="{{ $select }}">
                            <option value="">اختر المستودع…</option>
                            @foreach ($targetWarehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" :disabled="String(warehouseId) === '{{ $warehouse->id }}'">{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                        <span x-show="errors.target_warehouse_id" x-text="errors.target_warehouse_id" class="text-[13px] font-medium text-danger"></span>
                    </label>
                @endif
            </div>

            {{-- Barcode --}}
            <div class="sticky top-14 z-10 flex flex-col gap-2 rounded-xl border-2 border-primary bg-white px-4 py-3 lg:static lg:px-5 lg:py-4">
                <div class="flex items-center gap-3 lg:gap-4">
                    <label for="barcode" class="hidden flex-none flex-col gap-0.5 sm:flex">
                        <b class="text-base">الباركود</b>
                        <span class="text-xs text-muted">امسح أو اكتب الاسم + Enter</span>
                    </label>
                    <input id="barcode" x-ref="barcode" x-model="barcode" @keydown.enter.prevent="scan()" @input="barcodeError = ''"
                           placeholder="جاهز للمسح…" autocomplete="off" inputmode="search"
                           class="h-12 min-w-0 flex-1 rounded-[10px] border border-line-strong bg-canvas px-4 text-lg font-semibold tracking-wide focus:border-primary focus:outline-none focus:ring-[3px] focus:ring-primary-ring lg:h-14 lg:text-[22px]">
                    <button type="button" x-show="cameraSupported" x-cloak @click="openCamera()" title="مسح بالكاميرا" aria-label="مسح بالكاميرا"
                            class="flex h-12 flex-none items-center gap-2 rounded-lg border border-line-strong bg-white px-3.5 text-[15px] font-medium text-ink hover:bg-canvas lg:h-14">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 8a2 2 0 0 1 2-2h1.5l1-1.5h5l1 1.5H18a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8Z"/><circle cx="12" cy="12.5" r="3.5"/></svg>
                        <span class="hidden sm:inline">كاميرا</span>
                    </button>
                    <x-ui.button type="button" size="lg" class="!h-12 lg:!h-14" @click="scan()">إضافة</x-ui.button>
                </div>
                <div class="min-h-[18px] text-[13px]">
                    <span x-show="barcodeError" x-text="barcodeError" class="font-medium text-danger"></span>
                    <span x-show="barcodeFlash && !barcodeError" x-text="barcodeFlash" class="font-medium text-success"></span>
                    @if ($type === \App\Enums\MovementType::Purchase)
                        <button type="button" x-show="notFound && barcodeError" @click="openQuickProduct()" class="ms-3 font-semibold text-primary underline underline-offset-2">+ أضف هالمادة</button>
                    @endif
                </div>
            </div>

            {{-- Lines --}}
            <div class="overflow-hidden rounded-xl border border-line bg-white">
                <div class="hidden items-center gap-3 border-b border-line bg-canvas px-5 py-2.5 text-[13px] font-medium text-muted lg:grid {{ $rowColumns }}">
                    <span>الرمز</span>
                    <span>اسم المادة</span>
                    @if ($showBalance)<span class="text-left">الرصيد المتوفر</span>@endif
                    <span class="text-left">الكمية</span>
                    @unless ($isTransfer)
                        <span class="text-left">سعر الوحدة</span>
                        <span class="text-left">القيمة</span>
                    @endunless
                    <span></span>
                </div>

                <template x-for="line in lines" :key="line.key">
                    <div class="relative grid grid-cols-2 items-start gap-3 border-b border-line-soft px-4 py-3 tabular-nums lg:px-5 {{ $rowColumns }}"
                         :class="lineError(line) || priceError(line) ? 'bg-danger-soft' : ''">
                        <span class="hidden pt-2 text-sm text-muted lg:block" dir="ltr" x-text="line.barcode ?? '—'"></span>

                        <div class="col-span-2 flex flex-col pe-8 lg:col-span-1 lg:pe-0 lg:pt-1">
                            <b class="text-[15px] font-semibold" x-text="line.name"></b>
                            <span class="text-xs text-muted"><span x-text="line.unit"></span><template x-if="line.location"><span> · رف <span x-text="line.location"></span></span></template></span>
                        </div>

                        @if ($showBalance)
                            <div class="col-span-2 flex items-center justify-between lg:col-span-1 lg:block lg:pt-2 lg:text-left">
                                <span class="text-xs text-muted lg:hidden">الرصيد المتوفر</span>
                                <span class="font-semibold" :class="lineError(line) && !line.errors.quantity ? 'text-danger' : ''" x-text="formatBalance(line)"></span>
                            </div>
                        @endif

                        <label class="flex flex-col gap-1">
                            <span class="text-xs text-muted lg:hidden">الكمية</span>
                            <input x-model="line.quantity" @input="clearLineErrors(line)" @keydown.enter.prevent="focusBarcode()"
                                   inputmode="decimal" dir="ltr" autocomplete="off"
                                   :class="lineError(line) ? 'border-danger' : 'border-line-strong'"
                                   class="h-10 w-full rounded-lg border bg-white px-2.5 text-left text-[15px] focus:border-primary focus:outline-none focus:ring-[3px] focus:ring-primary-ring">
                            <span x-show="lineError(line)" x-text="lineError(line)" class="text-xs font-semibold text-danger"></span>
                        </label>

                        @unless ($isTransfer)
                        <label class="flex flex-col gap-1">
                            <span class="text-xs text-muted lg:hidden">سعر الوحدة</span>
                            <input x-model="line.price" @input="clearLineErrors(line); line.suggested = false"
                                   inputmode="decimal" dir="ltr" autocomplete="off" placeholder="0.00"
                                   :class="priceError(line) ? 'border-danger' : 'border-line-strong'"
                                   class="h-10 w-full rounded-lg border bg-white px-2.5 text-left text-[15px] focus:border-primary focus:outline-none focus:ring-[3px] focus:ring-primary-ring">
                            <span x-show="line.suggested" class="self-end rounded bg-primary-light px-1.5 py-px text-[11px] font-semibold text-primary">سعر مقترح</span>
                            <span x-show="priceError(line)" x-text="priceError(line)" class="text-xs font-semibold text-danger"></span>
                        </label>

                        <div class="col-span-2 flex items-center justify-between lg:col-span-1 lg:block lg:pt-2 lg:text-left">
                            <span class="text-xs text-muted lg:hidden">القيمة</span>
                            <span class="text-[15px] font-bold" x-text="lineValueText(line)"></span>
                        </div>
                        @endunless

                        <button type="button" @click="removeLine(line)" title="حذف السطر"
                                class="absolute left-2 top-2 h-9 w-9 rounded-lg text-lg text-subtle hover:bg-danger-soft hover:text-danger lg:static lg:mt-0.5">✕</button>
                    </div>
                </template>

                <div x-show="lines.length === 0" class="flex flex-col items-center gap-1.5 px-6 py-10 text-center">
                    <b class="text-base">ما في بنود لسا</b>
                    <span class="text-sm text-muted">امسح باركود أول مادة، أو اكتب اسمها واضغط Enter.</span>
                </div>
            </div>

            {{-- Totals --}}
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start">
                <div class="flex flex-1 flex-col gap-3">
                    <div x-show="formError" x-text="formError" role="alert"
                         class="rounded-[10px] border border-danger-border bg-danger-soft px-3.5 py-3 text-sm leading-6 text-red-800"></div>
                    <p class="text-[13px] leading-7 text-muted">{{ $note }}</p>
                    <p class="hidden gap-4 text-xs text-subtle lg:flex">
                        <span><code class="rounded bg-line-soft px-1.5 py-px">Enter</code> بالكمية ← يرجع للباركود</span>
                        <span><code class="rounded bg-line-soft px-1.5 py-px">Ctrl+S</code> حفظ</span>
                        <span><code class="rounded bg-line-soft px-1.5 py-px">F2</code> الباركود</span>
                    </p>
                </div>

                <div class="flex flex-col gap-3 rounded-xl border border-line bg-white p-5 tabular-nums lg:w-[440px] lg:flex-none">
                    @if ($isTransfer)
                        <div class="flex justify-between text-[15px]"><span class="text-muted">عدد البنود</span><b x-text="lines.length"></b></div>
                        <div class="flex justify-between gap-3 text-[15px]"><span class="text-muted">من ← إلى</span><b class="text-end" x-text="(warehouseNames[warehouseId] ?? '—') + ' ← ' + (warehouseNames[targetWarehouseId] ?? '—')"></b></div>
                    @else
                    <div class="flex justify-between text-[15px]"><span class="text-muted">المجموع</span><b x-text="subtotalText"></b></div>
                    <div class="flex items-center justify-between gap-3 text-[15px]">
                        <span class="text-muted">الحسم</span>
                        <div class="flex flex-col items-end gap-1">
                            <input x-model="discount" @input="errors.discount = ''" inputmode="decimal" dir="ltr" placeholder="0.00" autocomplete="off"
                                   :class="discountError ? 'border-danger' : 'border-line-strong'"
                                   class="h-9 w-[150px] rounded-lg border bg-white px-2.5 text-left text-[15px] focus:border-primary focus:outline-none focus:ring-[3px] focus:ring-primary-ring">
                            <span x-show="discountError" x-text="discountError" class="text-xs font-semibold text-danger"></span>
                        </div>
                    </div>
                    <div class="h-px bg-line"></div>
                    <div class="flex items-end justify-between">
                        <span class="flex flex-col"><b class="text-base">الصافي</b><span class="text-[13px] text-muted">{{ $type->netLabel() }}</span></span>
                        <span class="text-[34px] font-bold leading-tight text-primary"><span x-text="netText"></span>@if (filled($symbol)) <span class="text-[15px] font-medium text-muted">{{ $symbol }}</span>@endif</span>
                    </div>
                    @endif
                    <label class="flex items-center gap-2 text-sm text-ink">
                        <input type="checkbox" x-model="printAfter" class="h-[18px] w-[18px] rounded border-line-strong text-primary focus:ring-primary-ring">
                        اطبع بعد الحفظ
                    </label>
                    <div class="mt-1 hidden gap-2 lg:flex">
                        <x-ui.button type="button" size="lg" class="flex-1" ::disabled="saving" @click="save()"><span x-text="saving ? 'جاري الحفظ…' : (printAfter ? 'حفظ وطباعة' : 'حفظ الحركة')"></span></x-ui.button>
                        <x-ui.button type="button" size="lg" variant="secondary" @click="lines = []; focusBarcode()">إفراغ</x-ui.button>
                    </div>
                </div>
            </div>

            {{-- Camera scan: a full-screen viewfinder; the barcode it reads goes through the same scan() as a typed one --}}
            <div x-show="cameraOpen" x-cloak class="fixed inset-0 z-50 flex flex-col bg-black" role="dialog" aria-modal="true" aria-label="مسح بالكاميرا">
                <div class="flex items-center justify-between px-4 py-3 text-white">
                    <b class="text-base">وجّه الكاميرا على الباركود</b>
                    <button type="button" @click="closeCamera()" class="flex h-10 items-center rounded-lg bg-white/15 px-4 text-[15px] font-medium">إغلاق</button>
                </div>
                <div class="relative flex flex-1 items-center justify-center overflow-hidden">
                    <video x-ref="cameraVideo" playsinline muted class="h-full w-full object-cover"></video>
                    <div class="pointer-events-none absolute h-40 w-[80%] max-w-md rounded-2xl border-2 border-white/80"></div>
                </div>
                <p x-show="cameraError" x-text="cameraError" class="px-4 py-3 text-center text-sm text-red-200"></p>
            </div>

            @if ($type === \App\Enums\MovementType::Purchase)
                {{-- A product missing from the catalogue, added without leaving the invoice --}}
                <x-ui.modal show="quickProduct.open" title="'مادة جديدة'">
                    <form @submit.prevent="saveQuickProduct()" class="flex flex-col gap-4">
                        <label class="flex flex-col gap-1.5"><span class="text-[13px] font-medium">اسم المادة</span>
                            <input x-model="quickProduct.name" required maxlength="255" class="{{ $field }}" placeholder="مثال: زيت زيتون">
                            <span x-show="quickProduct.errors.name" x-text="quickProduct.errors.name" class="text-[13px] font-medium text-danger"></span></label>
                        <label class="flex flex-col gap-1.5"><span class="text-[13px] font-medium">الباركود <span class="font-normal text-muted">(اختياري)</span></span>
                            <input x-model="quickProduct.barcode" maxlength="64" dir="ltr" autocomplete="off" class="{{ $field }} text-right">
                            <span x-show="quickProduct.errors.barcode" x-text="quickProduct.errors.barcode" class="text-[13px] font-medium text-danger"></span></label>
                        <label class="flex flex-col gap-1.5"><span class="text-[13px] font-medium">الوحدة</span>
                            <select x-model="quickProduct.unit" style="background-position: left 0.75rem center" class="{{ $select }}">
                                @foreach (\App\Support\Units::options() as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <span x-show="quickProduct.errors.unit" x-text="quickProduct.errors.unit" class="text-[13px] font-medium text-danger"></span></label>
                        <label class="flex flex-col gap-1.5"><span class="text-[13px] font-medium">الموقع <span class="font-normal text-muted">(اختياري)</span></span>
                            <input x-model="quickProduct.location" maxlength="255" class="{{ $field }}" placeholder="مثال: رف A3"></label>
                        <div class="flex gap-2">
                            <x-ui.button ::disabled="quickProduct.saving">حفظ وإضافة للفاتورة</x-ui.button>
                            <x-ui.button type="button" variant="secondary" @click="quickProduct.open = false">إلغاء</x-ui.button>
                        </div>
                    </form>
                </x-ui.modal>
            @endif

            @unless ($isTransfer)
                {{-- A supplier / customer missing from the catalogue, added without leaving the invoice --}}
                <x-ui.modal show="quickParty.open" title="'{{ $type->partyLabel() }} جديد'">
                    <form @submit.prevent="saveQuickParty()" class="flex flex-col gap-4">
                        <label class="flex flex-col gap-1.5"><span class="text-[13px] font-medium">الاسم</span>
                            <input x-model="quickParty.name" required maxlength="255" class="{{ $field }}">
                            <span x-show="quickParty.errors.name" x-text="quickParty.errors.name" class="text-[13px] font-medium text-danger"></span></label>
                        <label class="flex flex-col gap-1.5"><span class="text-[13px] font-medium">الهاتف <span class="font-normal text-muted">(اختياري)</span></span>
                            <input x-model="quickParty.phone" maxlength="30" dir="ltr" inputmode="tel" class="{{ $field }} text-right">
                            <span x-show="quickParty.errors.phone" x-text="quickParty.errors.phone" class="text-[13px] font-medium text-danger"></span></label>
                        <div class="flex gap-2">
                            <x-ui.button ::disabled="quickParty.saving">حفظ</x-ui.button>
                            <x-ui.button type="button" variant="secondary" @click="quickParty.open = false">إلغاء</x-ui.button>
                        </div>
                    </form>
                </x-ui.modal>
            @endunless

            {{-- Mobile: net + save stay in reach --}}
            <div class="fixed inset-x-0 bottom-0 z-20 flex items-center gap-3 border-t border-line bg-white px-4 py-3 tabular-nums lg:hidden">
                <div class="flex flex-1 flex-col leading-tight">
                    @if ($isTransfer)
                        <span class="text-xs text-muted">عدد البنود</span>
                        <span class="text-xl font-bold text-primary" x-text="lines.length"></span>
                    @else
                        <span class="text-xs text-muted">الصافي · {{ $type->netLabel() }}</span>
                        <span class="text-xl font-bold text-primary"><span x-text="netText"></span>@if (filled($symbol)) <span class="text-xs font-medium text-muted">{{ $symbol }}</span>@endif</span>
                    @endif
                </div>
                <x-ui.button type="button" size="lg" ::disabled="saving" @click="save()"><span x-text="saving ? 'جاري الحفظ…' : (printAfter ? 'حفظ وطباعة' : 'حفظ')"></span></x-ui.button>
            </div>
        </div>
    @endif
</x-app-layout>
