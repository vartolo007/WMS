{{-- Screen 8 (design batch 4): movements list with the cancel action (FR-17). --}}
@php
    $isOwner = auth()->user()->isOwner();
    $typeOptions = collect(\App\Enums\MovementType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all();
    $statusOptions = collect(\App\Enums\MovementStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all();
@endphp

<x-app-layout title="الحركات">
    <div class="flex flex-col gap-6">
        <x-ui.page-header title="الحركات">
            <x-ui.button :href="route('movements.create')">+ حركة جديدة</x-ui.button>
        </x-ui.page-header>

        @if ($errors->has('cancel'))
            <x-ui.alert>{{ $errors->first('cancel') }}</x-ui.alert>
        @endif

        <form method="GET" action="{{ route('movements.index') }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-line bg-white p-4 lg:px-5">
            <div class="w-full sm:w-[160px]"><x-ui.select name="type" label="النوع" :options="$typeOptions" :value="$filters['type'] ?? null" placeholder="كل الأنواع" /></div>
            <div class="w-[calc(50%-6px)] sm:w-[160px]"><x-ui.input name="from" type="date" label="من" :value="$filters['from'] ?? null" /></div>
            <div class="w-[calc(50%-6px)] sm:w-[160px]"><x-ui.input name="to" type="date" label="إلى" :value="$filters['to'] ?? null" /></div>
            @if ($isOwner)
                <div class="w-full sm:w-[170px]">
                    <x-ui.select name="warehouse_id" label="المستودع" :options="$warehouses->pluck('name', 'id')->all()" :value="$warehouseId" placeholder="كل المستودعات" />
                </div>
            @endif
            <div class="w-full sm:w-[130px]"><x-ui.select name="status" label="الحالة" :options="$statusOptions" :value="$filters['status'] ?? null" placeholder="الكل" /></div>
            <div class="min-w-[180px] flex-1"><x-ui.input name="q" type="search" label="بحث" :value="$filters['q'] ?? null" placeholder="رقم الحركة أو الطرف" autocomplete="off" /></div>
            <x-ui.button variant="secondary">تطبيق</x-ui.button>
            @if ($filtered)
                <x-ui.button :href="route('movements.index', ['warehouse_id' => ''])" variant="ghost">مسح الفلاتر</x-ui.button>
            @endif
        </form>

        @if ($movements->isEmpty())
            @if ($filtered)
                <x-ui.empty-state title="ما في حركات بهالفلتر" description="جرّب فترة أوسع أو امسح الفلاتر.">
                    <x-ui.button :href="route('movements.index', ['warehouse_id' => ''])" variant="secondary">مسح الفلاتر</x-ui.button>
                </x-ui.empty-state>
            @else
                <x-ui.empty-state title="ما في حركات بعد" description="أول فاتورة شراء بتدخّل المواد للمستودع، وبعدها بتقدر تبيع منها.">
                    <x-ui.button :href="route('movements.create', ['type' => 'purchase'])">+ فاتورة شراء</x-ui.button>
                </x-ui.empty-state>
            @endif
        @else
            <x-ui.table>
                <thead>
                    <tr>
                        <x-ui.th>رقم الحركة</x-ui.th>
                        <x-ui.th>التاريخ</x-ui.th>
                        <x-ui.th>النوع</x-ui.th>
                        @if ($isOwner)<x-ui.th>المستودع</x-ui.th>@endif
                        <x-ui.th>الطرف / المقابل</x-ui.th>
                        <x-ui.th numeric>البنود</x-ui.th>
                        <x-ui.th numeric>الصافي</x-ui.th>
                        <x-ui.th>الحالة</x-ui.th>
                        <x-ui.th></x-ui.th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($movements as $movement)
                        <tr @class(['text-subtle' => $movement->isCancelled()])>
                            <x-ui.td class="whitespace-nowrap font-medium">
                                <span dir="ltr" @class(['inline-block', 'line-through' => $movement->isCancelled()])>{{ $movement->displayNumber() }}</span>
                            </x-ui.td>
                            <x-ui.td class="whitespace-nowrap text-muted">{{ \App\Support\Format::date($movement->date) }}</x-ui.td>
                            <x-ui.td><x-ui.badge :tone="$movement->type->tone()" shape="tag">{{ $movement->type->label() }}</x-ui.badge></x-ui.td>
                            @if ($isOwner)<x-ui.td>{{ $movement->warehouse->name }}</x-ui.td>@endif
                            <x-ui.td><span @class(['line-through' => $movement->isCancelled()])>{{ $movement->type->counterpartLabel($movement->party?->name, $movement->targetWarehouse?->name) }}</span></x-ui.td>
                            <x-ui.td numeric>{{ $movement->items_count }}</x-ui.td>
                            <x-ui.td numeric class="font-semibold">
                                <x-ui.money :amount="$movement->total" :class="$movement->isCancelled() ? 'line-through' : ''" />
                            </x-ui.td>
                            <x-ui.td><x-ui.badge :tone="$movement->status->tone()">{{ $movement->status->label() }}</x-ui.badge></x-ui.td>
                            <x-ui.td class="whitespace-nowrap text-left">
                                <div class="inline-flex items-center gap-1">
                                    <x-ui.button size="sm" variant="ghost" :href="route('movements.show', $movement)">عرض</x-ui.button>
                                    @if (Gate::allows('cancel', $movement) && ! $movement->has_active_return)
                        <x-ui.confirm :title="'إلغاء الحركة '.$movement->displayNumber().'؟'" :action="route('movements.cancel', $movement)" confirm-label="نعم، ألغِ الحركة" cancel-label="تراجع">
                            <x-slot:trigger>
                                <x-ui.button size="sm" variant="ghost" type="button" class="!text-danger">إلغاء</x-ui.button>
                            </x-slot:trigger>
                            {{ $movement->type->cancelEffect($movement->itemsSummary(), $movement->warehouse->name, $movement->targetWarehouse?->name) }}
                        </x-ui.confirm>
                                    @endif
                                </div>
                            </x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>

            <div class="flex items-center justify-between text-sm text-muted">
                <span>{{ $movements->total() }} حركة</span>
            </div>
            {{ $movements->links() }}
        @endif
    </div>
</x-app-layout>
