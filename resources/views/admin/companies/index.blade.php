<x-app-layout title="الشركات">
    <div class="flex flex-col gap-6">
        <x-ui.page-header title="الشركات" subtitle="الاشتراكات وحالاتها. الشركات اللي انتهت تجربتها مميّزة للتواصل معها." />

        @if ($errors->any())
            <x-ui.alert>{{ $errors->first() }}</x-ui.alert>
        @endif

        @if ($companies->isEmpty())
            <x-ui.empty-state title="ما في شركات بعد" description="أول ما تسجّل شركة من صفحة التسجيل بتظهر هون." />
        @else
            <x-ui.table>
                <thead>
                    <tr>
                        <x-ui.th>الشركة</x-ui.th>
                        <x-ui.th>صاحب الشركة</x-ui.th>
                        <x-ui.th>الحالة</x-ui.th>
                        <x-ui.th>نهاية التجربة</x-ui.th>
                        <x-ui.th></x-ui.th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($companies as $company)
                        <tr @class(['bg-amber-50' => $company->isTrialExpired()])>
                            <x-ui.td class="font-medium">{{ $company->name }}</x-ui.td>
                            <x-ui.td>
                                {{ $company->owner?->name }}
                                <div class="text-[13px] text-muted" dir="ltr">{{ $company->owner?->email }}</div>
                            </x-ui.td>
                            <x-ui.td><x-ui.badge :tone="$company->status->tone()">{{ $company->status->label() }}</x-ui.badge></x-ui.td>
                            <x-ui.td class="whitespace-nowrap text-muted">
                                {{ \App\Support\Format::date($company->trial_ends_at) }}
                                @if ($company->isTrialExpired())
                                    <div class="text-[13px] font-medium text-warning">انتهت التجربة</div>
                                @endif
                            </x-ui.td>
                            <x-ui.td class="text-left">
                                <div class="inline-flex flex-wrap items-center gap-1.5">
                                    @if ($company->status !== \App\Enums\CompanyStatus::Active)
                                        <form method="POST" action="{{ route('admin.companies.activate', $company) }}">
                                            @csrf @method('PATCH')
                                            <x-ui.button size="sm">تفعيل</x-ui.button>
                                        </form>
                                    @endif

                                    @if ($company->status !== \App\Enums\CompanyStatus::Active)
                                        <form method="POST" action="{{ route('admin.companies.extend-trial', $company) }}" class="inline-flex items-center gap-1">
                                            @csrf @method('PATCH')
                                            <input type="number" name="days" min="1" max="365" value="{{ config('wms.trial_days') }}" dir="ltr" aria-label="عدد الأيام"
                                                   class="h-8 w-16 rounded-md border-line-strong px-2 text-[13px] tabular-nums focus:border-primary focus:ring-primary-ring">
                                            <x-ui.button size="sm" variant="secondary">تمديد</x-ui.button>
                                        </form>
                                    @endif

                                    @if ($company->status !== \App\Enums\CompanyStatus::Suspended)
                                        <x-ui.confirm :title="'إيقاف '.$company->name.'؟'" :action="route('admin.companies.suspend', $company)" method="PATCH" confirm-label="نعم، أوقف الشركة">
                                            <x-slot:trigger>
                                                <x-ui.button size="sm" variant="ghost" type="button" class="!text-danger">إيقاف</x-ui.button>
                                            </x-slot:trigger>
                                            صاحب الشركة وكل مستودعاتها ما رح يقدروا يدخلوا. البيانات ما بتنحذف، وبترجع كاملة عند التفعيل.
                                        </x-ui.confirm>
                                    @endif
                                </div>
                            </x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>

            {{ $companies->links() }}
        @endif
    </div>
</x-app-layout>
