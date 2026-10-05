<x-app-layout title="مستودع جديد">
    <div class="flex max-w-2xl flex-col gap-6">
        <x-ui.page-header title="مستودع جديد" />

        <form method="POST" action="{{ route('warehouses.store') }}" class="flex flex-col gap-5 rounded-xl border border-line bg-white p-6">
            @csrf

            @include('warehouses.partials.form', ['warehouse' => null])

            <div class="flex gap-2">
                <x-ui.button>إنشاء</x-ui.button>
                <x-ui.button variant="secondary" :href="route('warehouses.index')">إلغاء</x-ui.button>
            </div>
        </form>
    </div>
</x-app-layout>
