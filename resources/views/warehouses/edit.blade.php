<x-app-layout :title="'تعديل '.$warehouse->name">
    <div class="flex max-w-2xl flex-col gap-6">
        <x-ui.page-header :title="'تعديل '.$warehouse->name" />

        <form method="POST" action="{{ route('warehouses.update', $warehouse) }}" class="flex flex-col gap-5 rounded-xl border border-line bg-white p-6">
            @csrf
            @method('PUT')

            @include('warehouses.partials.form', ['warehouse' => $warehouse])

            <div class="flex gap-2">
                <x-ui.button>حفظ</x-ui.button>
                <x-ui.button variant="secondary" :href="route('warehouses.index')">إلغاء</x-ui.button>
            </div>
        </form>
    </div>
</x-app-layout>
