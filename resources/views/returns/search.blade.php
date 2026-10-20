{{-- Screen 7, return tabs, step 1 (design batch 3): find the original invoice. --}}
<x-app-layout title="حركة جديدة">
    <div class="flex flex-col gap-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-bold lg:text-[28px]">حركة جديدة</h1>
            @include('movements.partials.type-tabs', ['active' => $type])
        </div>

        <div class="flex flex-col gap-4 rounded-xl border border-line bg-white p-5">
            <div class="flex flex-col gap-0.5">
                <b class="text-base">الفاتورة الأصلية</b>
                <span class="text-xs text-muted">الخطوة 1: ابحث عن {{ $type->originalType()->label() }} برقمها أو باسم {{ $type->partyLabel() }}</span>
            </div>

            <form method="GET" action="{{ route('returns.create') }}" class="flex gap-3">
                <input type="hidden" name="type" value="{{ $type->value }}">
                <div class="flex-1">
                    <x-ui.input name="q" type="search" :value="$term" size="lg" autofocus autocomplete="off"
                                :placeholder="$type === \App\Enums\MovementType::SaleReturn ? 'رقم فاتورة البيع، مثال: S-0142' : 'رقم فاتورة الشراء، مثال: P-0087'" />
                </div>
                <x-ui.button size="lg">بحث</x-ui.button>
            </form>

            @if ($invoices->isEmpty())
                <div class="rounded-[10px] border border-line px-4 py-4 text-sm text-muted">ما في فاتورة بهالرقم.</div>
            @else
                <div class="flex flex-col overflow-hidden rounded-[10px] border border-line">
                    @foreach ($invoices as $invoice)
                        @php($open = $returnable[$invoice->id])
                        <a @if ($open) href="{{ route('movements.return', $invoice) }}" @endif
                           @class([
                               'flex flex-wrap items-center gap-x-5 gap-y-1 border-b border-line-soft px-4 py-3 text-[15px] last:border-b-0',
                               'hover:bg-canvas' => $open,
                               'cursor-not-allowed text-subtle' => ! $open,
                           ])>
                            <b dir="ltr" class="w-20 text-right">{{ $invoice->displayNumber() }}</b>
                            <span class="min-w-0 flex-1 truncate">{{ $invoice->type->counterpartLabel($invoice->party?->name, null) }}</span>
                            @unless ($open)
                                <span class="text-xs">أُرجعت بالكامل</span>
                            @endunless
                            <span class="text-muted tabular-nums">{{ \App\Support\Format::date($invoice->date) }}</span>
                            <span class="w-36 text-left font-semibold tabular-nums"><x-ui.money :amount="$invoice->total" /></span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
