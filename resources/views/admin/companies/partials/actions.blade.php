{{-- Subscription actions of one company (FR-02): activate, extend the trial, suspend. Used by the table and the mobile cards. --}}
@php
    $extensionDays = [7, 14, 30];
    $defaultDays = in_array((int) config('wms.trial_days'), $extensionDays, true) ? (int) config('wms.trial_days') : 14;
@endphp

<div class="inline-flex flex-wrap items-center gap-1.5">
    @if ($company->status !== \App\Enums\CompanyStatus::Active)
        <x-ui.confirm :title="'تفعيل '.$company->name.'؟'" :action="route('admin.companies.activate', $company)" method="PATCH" confirm-label="تفعيل" tone="primary">
            <x-slot:trigger>
                <x-ui.button size="sm" type="button">تفعيل</x-ui.button>
            </x-slot:trigger>
            الاشتراك بيصير فعّال، ومستخدمي الشركة بيقدروا يدخلوا ويشتغلوا فوراً.
        </x-ui.confirm>

        <x-ui.confirm :title="'تمديد تجربة '.$company->name" :action="route('admin.companies.extend-trial', $company)" method="PATCH" confirm-label="تمديد" tone="primary">
            <x-slot:trigger>
                <x-ui.button size="sm" variant="secondary" type="button">تمديد التجربة</x-ui.button>
            </x-slot:trigger>
            <x-slot:fields>
                <div x-data="{ days: {{ $defaultDays }} }" class="flex flex-col gap-3">
                    <div class="flex gap-2">
                        @foreach ($extensionDays as $days)
                            <label class="flex-1">
                                <input type="radio" name="days" value="{{ $days }}" x-model="days" class="peer sr-only">
                                <span class="flex h-11 cursor-pointer items-center justify-center rounded-lg border border-line-strong text-[15px] peer-checked:border-primary peer-checked:bg-primary-light peer-checked:font-bold peer-checked:text-primary peer-focus-visible:ring-[3px] peer-focus-visible:ring-primary-ring">{{ \App\Support\Format::days($days) }}</span>
                            </label>
                        @endforeach
                    </div>
                    {{-- The new end dates come from the server (Company::trialEndAfterExtending): the dialog only shows the chosen one. --}}
                    <div class="text-sm text-muted">
                        الانتهاء الجديد:
                        @foreach ($extensionDays as $days)
                            <b class="text-ink" x-show="days == {{ $days }}" @if ($days !== $defaultDays) x-cloak @endif>{{ \App\Support\Format::date($company->trialEndAfterExtending($days)) }}</b>
                        @endforeach
                    </div>
                </div>
            </x-slot:fields>
            التمديد بيبدأ من تاريخ الانتهاء الحالي، أو من اليوم إذا كانت منتهية. الحالة بترجع "تجريبي".
        </x-ui.confirm>
    @endif

    @if ($company->status !== \App\Enums\CompanyStatus::Suspended)
        <x-ui.confirm :title="'إيقاف '.$company->name.'؟'" :action="route('admin.companies.suspend', $company)" method="PATCH" confirm-label="نعم، أوقف الشركة">
            <x-slot:trigger>
                <x-ui.button size="sm" variant="ghost" type="button" class="!text-danger">إيقاف</x-ui.button>
            </x-slot:trigger>
            مستخدمي الشركة ما رح يقدروا يشتغلوا، وبتظهرلهم صفحة الاشتراك الموقوف. البيانات بتضل محفوظة وفيك تفعّلها بأي وقت.
        </x-ui.confirm>
    @endif
</div>
