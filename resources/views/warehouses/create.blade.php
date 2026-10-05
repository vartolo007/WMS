<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">مستودع جديد</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('warehouses.store') }}" class="bg-white shadow-sm sm:rounded-lg p-6">
                @csrf

                @include('warehouses.partials.form', ['warehouse' => null])

                <div class="flex items-center justify-end gap-3 mt-6">
                    <a href="{{ route('warehouses.index') }}" class="text-sm text-gray-600 underline">{{ __('Cancel') }}</a>
                    <x-primary-button>إنشاء</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
