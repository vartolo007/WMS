<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">لوحة التحكم</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="text-gray-900 font-semibold">{{ $company->name }}</div>
                <div class="mt-1 text-sm text-gray-600">
                    حالة الاشتراك: {{ $company->status->label() }}
                    @if ($company->status === \App\Enums\CompanyStatus::Trial)
                        — تنتهي التجربة في <span dir="ltr">{{ $company->trial_ends_at->format('Y-m-d') }}</span>
                    @endif
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6 text-gray-500 text-sm">
                صافي مبيعات اليوم والمواد المنخفضة والراكدة تظهر هنا بعد إضافة الحركات والتقارير.
            </div>
        </div>
    </div>
</x-app-layout>
