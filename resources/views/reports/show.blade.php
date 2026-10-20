{{-- Screens 16–17 (design batch 6): the reports centre. One engine; the list on the right picks the report. --}}
@php
    $isOwner = auth()->user()->isOwner();
    $groups = [
        'المبيعات' => [\App\Enums\ReportKind::DailySales, \App\Enums\ReportKind::SalesTotal, \App\Enums\ReportKind::SalesByProduct],
        'المشتريات' => [\App\Enums\ReportKind::PurchasesTotal, \App\Enums\ReportKind::PurchasesBySupplier],
        'الحركات' => [\App\Enums\ReportKind::DailyMovements, \App\Enums\ReportKind::Returns, \App\Enums\ReportKind::Transfers, \App\Enums\ReportKind::ProductLedger, \App\Enums\ReportKind::PartyMovement],
        'المخزون' => [\App\Enums\ReportKind::Stock, \App\Enums\ReportKind::LowStock, \App\Enums\ReportKind::Moved, \App\Enums\ReportKind::Stagnant],
    ];
    $field = 'h-10 w-full rounded-lg border border-line-strong bg-white px-3 text-[15px] text-ink focus:border-primary focus:outline-none focus:ring-[3px] focus:ring-primary-ring';
    $select = 'h-10 w-full appearance-none rounded-lg border border-line-strong bg-white ps-3 pe-9 text-[15px] text-ink focus:border-primary focus:outline-none focus:ring-[3px] focus:ring-primary-ring';
    $period = $report->period();
@endphp

<x-app-layout :title="$report->label()">
    <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:gap-6">
        {{-- Report list: a column on desktop, a select on mobile --}}
        <aside class="flex w-full flex-none flex-col gap-3 lg:w-[240px]">
            <h1 class="text-2xl font-bold lg:mb-1 lg:text-[28px]">التقارير</h1>

            <select class="{{ $select }} lg:hidden" style="background-position: left 0.75rem center" aria-label="التقرير"
                    onchange="window.location = this.value">
                @foreach ($groups as $label => $kinds)
                    <optgroup label="{{ $label }}">
                        @foreach ($kinds as $kind)
                            <option value="{{ route('reports.show', $kind) }}" @selected($kind === $report)>{{ $kind->label() }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>

            <nav class="hidden flex-col gap-0.5 rounded-xl border border-line bg-white p-1.5 lg:flex" aria-label="التقارير">
                @foreach ($groups as $label => $kinds)
                    <div class="px-3 pb-1 pt-3 text-xs font-semibold text-subtle first:pt-1.5">{{ $label }}</div>
                    @foreach ($kinds as $kind)
                        <a href="{{ route('reports.show', $kind) }}" @class([
                            'flex flex-col gap-0.5 rounded-lg px-3 py-2.5',
                            'bg-primary-light' => $kind === $report,
                            'hover:bg-canvas' => $kind !== $report,
                        ]) @if ($kind === $report) aria-current="page" @endif>
                            <span @class(['text-[15px]', 'font-bold text-primary' => $kind === $report, 'font-medium text-ink' => $kind !== $report])>{{ $kind->label() }}</span>
                            <span class="text-xs text-muted">{{ $kind->description() }}</span>
                        </a>
                    @endforeach
                @endforeach
            </nav>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col gap-4">
            <div class="flex flex-wrap items-center justify-between gap-3 lg:mt-1.5">
                <b class="text-[22px]">{{ $report->label() }}</b>
                @unless ($missing)
                    <x-ui.button variant="secondary" :href="route('reports.print', ['report' => $report, ...request()->except('page')])" target="_blank" rel="noopener">طباعة</x-ui.button>
                @endunless
            </div>

            {{-- Filters --}}
            <form method="GET" action="{{ route('reports.show', $report) }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-line bg-white p-4 lg:px-5">
                @if ($period === 'day')
                    <label class="flex w-full flex-col gap-1.5 sm:w-auto"><span class="text-[13px] font-medium">اليوم</span><input type="date" name="date" value="{{ $filters->dateTo }}" class="{{ $field }}"></label>
                @else
                    <label class="flex w-[calc(50%-6px)] flex-col gap-1.5 sm:w-auto"><span class="text-[13px] font-medium">من @if ($period === 'asof')<span class="font-normal text-muted">(اختياري)</span>@endif</span><input type="date" name="from" value="{{ $filters->dateFrom }}" class="{{ $field }}"></label>
                    <label class="flex w-[calc(50%-6px)] flex-col gap-1.5 sm:w-auto"><span class="text-[13px] font-medium">{{ $period === 'asof' ? 'حتى تاريخ' : 'إلى' }}</span><input type="date" name="to" value="{{ $filters->dateTo }}" class="{{ $field }}"></label>
                @endif

                @if ($isOwner)
                    <label class="flex w-full flex-col gap-1.5 sm:w-[190px]">
                        <span class="text-[13px] font-medium">المستودع</span>
                        <select name="warehouse_id" style="background-position: left 0.75rem center" class="{{ $select }}">
                            <option value="">كل المستودعات</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected($filters->warehouseId === $warehouse->id)>{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif

                @if ($report->needsProduct())
                    <label class="flex w-full flex-col gap-1.5 sm:w-[210px]">
                        <span class="text-[13px] font-medium">المادة</span>
                        <select name="product_id" style="background-position: left 0.75rem center" class="{{ $select }}">
                            <option value="">اختر المادة…</option>
                            @foreach ($products as $item)
                                <option value="{{ $item->id }}" @selected($filters->productId === $item->id)>{{ $item->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif

                @if ($report->needsParty())
                    <label class="flex w-full flex-col gap-1.5 sm:w-[230px]">
                        <span class="text-[13px] font-medium">{{ $report->partyType()?->label() ?? 'العميل أو المورد' }}</span>
                        <select name="party_id" style="background-position: left 0.75rem center" class="{{ $select }}">
                            <option value="">اختر…</option>
                            @foreach ($parties as $item)
                                <option value="{{ $item->id }}" @selected($filters->partyId === $item->id)>{{ $item->name }}@unless ($report->partyType()) · {{ $item->type->label() }}@endunless</option>
                            @endforeach
                        </select>
                    </label>
                @endif

                @if ($report->hasGrouping())
                    <label class="flex w-full flex-col gap-1.5 sm:w-[190px]">
                        <span class="text-[13px] font-medium">التجميع</span>
                        <select name="group" style="background-position: left 0.75rem center" class="{{ $select }}">
                            <option value="product" @selected($filters->groupBy === 'product')>حسب المادة</option>
                            <option value="party" @selected($filters->groupBy === 'party')>حسب {{ $report === \App\Enums\ReportKind::SalesTotal ? 'العميل' : 'المورد' }}</option>
                            <option value="warehouse" @selected($filters->groupBy === 'warehouse')>حسب المستودع</option>
                        </select>
                    </label>
                @endif

                @if ($report->isStock() && $isOwner)
                    <x-ui.checkbox name="split" value="1" label="مقسّم حسب المستودع" :checked="$filters->splitByWarehouse" />
                @endif

                <x-ui.button>عرض التقرير</x-ui.button>
            </form>

            @if ($errors->any())
                <div role="alert" class="rounded-[10px] border border-danger-border bg-danger-soft px-3.5 py-3 text-sm leading-6 text-red-800">{{ $errors->first() }}</div>
            @endif

            @if ($missing)
                <x-ui.empty-state
                    :title="$missing === 'product' ? 'اختر المادة' : 'اختر '.($report->partyType()?->label() ?? 'الطرف')"
                    description="هالتقرير بيعرض حركة طرف أو مادة وحدة، اختارها من الفلاتر فوق واضغط «عرض التقرير»." />
            @else
                @include('reports.partials.'.$report->view(), ['print' => false])
            @endif
        </div>
    </div>
</x-app-layout>
