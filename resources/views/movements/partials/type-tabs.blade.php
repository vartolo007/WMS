{{-- Type tabs of the new-movement screens (design screen 7). $active: the current MovementType. --}}
@php
    $dots = ['blue' => 'bg-blue-500', 'success' => 'bg-green-600', 'warning' => 'bg-amber-500', 'danger' => 'bg-red-500', 'purple' => 'bg-purple-500'];
    $tabs = [\App\Enums\MovementType::Purchase, \App\Enums\MovementType::Sale, \App\Enums\MovementType::PurchaseReturn, \App\Enums\MovementType::SaleReturn, \App\Enums\MovementType::Transfer];
@endphp

<nav class="flex max-w-full gap-1 overflow-x-auto rounded-[10px] bg-line-soft p-1" aria-label="نوع الحركة">
    @foreach ($tabs as $tab)
        <a href="{{ $tab->isReturn() ? route('returns.create', ['type' => $tab->value]) : route('movements.create', ['type' => $tab->value]) }}"
           @class([
               'flex h-10 items-center gap-2 whitespace-nowrap rounded-[7px] px-[18px] text-[15px]',
               'bg-white font-bold text-primary shadow-sm' => $tab === $active,
               'font-medium text-ink' => $tab !== $active,
           ])
           @if ($tab === $active) aria-current="page" @endif>
            <span class="h-2 w-2 rounded-sm {{ $dots[$tab->tone()] }}"></span>{{ $tab->tabLabel() }}
        </a>
    @endforeach
</nav>
