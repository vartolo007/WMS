{{-- Owner's warehouse selector (FR-05). The choice is kept in the session by WarehouseContextController. --}}
@php($fullWidth = $fullWidth ?? false)

<div x-data="{ open: false }" @click.outside="open = false" class="relative">
    <button type="button" @click="open = ! open" @class([
        'flex h-11 items-center gap-2.5 rounded-lg border border-line-strong bg-white px-3.5 text-[15px] lg:h-10',
        'w-full' => $fullWidth,
        'min-w-[220px]' => ! $fullWidth,
    ])>
        <span class="text-[13px] text-muted">المستودع:</span>
        <span class="flex-1 text-start font-semibold">{{ $currentWarehouse?->name ?? 'كل المستودعات' }}</span>
        <span class="-mt-1 h-[7px] w-[7px] -rotate-45 border-b-2 border-l-2 border-muted"></span>
    </button>

    <form x-show="open" x-cloak x-transition method="POST" action="{{ route('warehouse-context') }}"
          class="absolute top-12 z-40 flex flex-col rounded-[10px] border border-line bg-white p-1.5 shadow-xl {{ $fullWidth ? 'inset-x-0' : 'start-0 w-[260px]' }}">
        @csrf
        @php($options = collect([['id' => '', 'name' => 'كل المستودعات', 'note' => 'مجمّع', 'active' => $currentWarehouse === null]])
            ->concat($warehouseOptions->map(fn ($w) => [
                'id' => $w->id,
                'name' => $w->name,
                'note' => $w->is_active ? '' : 'موقوف',
                'active' => $currentWarehouse?->id === $w->id,
            ])))
        @foreach ($options as $option)
            <button type="submit" name="warehouse_id" value="{{ $option['id'] }}" @class([
                'flex items-center justify-between rounded-md px-3 py-2.5 text-start text-[15px]',
                'bg-primary-light font-semibold text-primary' => $option['active'],
                'text-ink hover:bg-canvas' => ! $option['active'],
            ])>
                <span>{{ $option['name'] }}</span>
                <span class="text-xs font-normal text-muted">{{ $option['note'] }}</span>
            </button>
        @endforeach
        @if ($warehouseOptions->isEmpty())
            <a href="{{ route('warehouses.create') }}" class="px-3 py-2.5 text-sm">+ أضف أول مستودع</a>
        @endif
    </form>
</div>
