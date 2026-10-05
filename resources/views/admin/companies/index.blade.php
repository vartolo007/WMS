<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">الشركات</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-3 rounded-md bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="p-3 rounded-md bg-red-50 text-red-800 text-sm">{{ $errors->first() }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm text-start">
                    <thead class="bg-gray-50 text-gray-600">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium">الشركة</th>
                            <th class="px-4 py-3 text-start font-medium">صاحب الشركة</th>
                            <th class="px-4 py-3 text-start font-medium">الحالة</th>
                            <th class="px-4 py-3 text-start font-medium">نهاية التجربة</th>
                            <th class="px-4 py-3 text-start font-medium">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($companies as $company)
                            <tr @class(['bg-amber-50' => $company->isTrialExpired()])>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $company->name }}</td>
                                <td class="px-4 py-3 text-gray-700">
                                    {{ $company->owner?->name }}
                                    <div class="text-xs text-gray-500" dir="ltr">{{ $company->owner?->email }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'px-2 py-1 rounded-full text-xs',
                                        'bg-gray-100 text-gray-700' => $company->status === \App\Enums\CompanyStatus::Trial,
                                        'bg-green-100 text-green-800' => $company->status === \App\Enums\CompanyStatus::Active,
                                        'bg-red-100 text-red-800' => $company->status === \App\Enums\CompanyStatus::Suspended,
                                    ])>{{ $company->status->label() }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    <span dir="ltr">{{ $company->trial_ends_at->format('Y-m-d') }}</span>
                                    @if ($company->isTrialExpired())
                                        <div class="text-xs text-amber-700">انتهت التجربة</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        @if ($company->status !== \App\Enums\CompanyStatus::Active)
                                            <form method="POST" action="{{ route('admin.companies.activate', $company) }}">
                                                @csrf @method('PATCH')
                                                <x-primary-button>تفعيل</x-primary-button>
                                            </form>
                                        @endif

                                        @if ($company->status !== \App\Enums\CompanyStatus::Suspended)
                                            <form method="POST" action="{{ route('admin.companies.suspend', $company) }}">
                                                @csrf @method('PATCH')
                                                <x-danger-button>إيقاف</x-danger-button>
                                            </form>
                                        @endif

                                        @if ($company->status !== \App\Enums\CompanyStatus::Active)
                                            <form method="POST" action="{{ route('admin.companies.extend-trial', $company) }}" class="flex items-center gap-1">
                                                @csrf @method('PATCH')
                                                <x-text-input type="number" name="days" min="1" max="365" class="w-20 text-sm" :value="config('wms.trial_days')" required />
                                                <x-secondary-button type="submit">تمديد</x-secondary-button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-500">لا توجد شركات بعد.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $companies->links() }}
        </div>
    </div>
</x-app-layout>
