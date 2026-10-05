<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">لوحة تحكم المنصة</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (\App\Enums\CompanyStatus::cases() as $status)
                <div class="bg-white shadow-sm sm:rounded-lg p-5">
                    <div class="text-sm text-gray-500">شركات {{ $status->label() }}</div>
                    <div class="mt-1 text-2xl font-bold text-gray-900">{{ $counts[$status->value] ?? 0 }}</div>
                </div>
            @endforeach

            <a href="{{ route('admin.companies.index') }}" class="bg-white shadow-sm sm:rounded-lg p-5 border-s-4 border-primary hover:bg-primary-light">
                <div class="text-sm text-gray-500">انتهت تجربتها</div>
                <div class="mt-1 text-2xl font-bold text-primary">{{ $expiredTrials }}</div>
            </a>
        </div>
    </div>
</x-app-layout>
