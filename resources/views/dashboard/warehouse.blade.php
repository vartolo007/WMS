<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">لوحة التحكم — {{ Auth::user()->name }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6 text-gray-500 text-sm">
                حركات اليوم والمواد المنخفضة تظهر هنا بعد إضافة الحركات والتقارير.
            </div>
        </div>
    </div>
</x-app-layout>
