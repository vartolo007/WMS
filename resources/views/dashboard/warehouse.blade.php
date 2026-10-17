<x-app-layout title="لوحة التحكم">
    <div class="flex flex-col gap-6">
        <x-ui.page-header title="لوحة التحكم" :subtitle="\App\Support\Format::date(today(), withWeekday: true).' · '.auth()->user()->name">
            <x-ui.button :href="route('movements.create', ['type' => 'purchase'])" variant="secondary">+ فاتورة شراء</x-ui.button>
            <x-ui.button :href="route('movements.create', ['type' => 'sale'])">+ فاتورة بيع</x-ui.button>
        </x-ui.page-header>

        <x-ui.empty-state title="ما في حركات اليوم" description="حركات اليوم والمواد المنخفضة بيظهروا هون بعد إضافة الحركات." />
    </div>
</x-app-layout>
