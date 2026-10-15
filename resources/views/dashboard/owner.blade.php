@php($current = app(\App\Support\CurrentWarehouse::class)->get())
<x-app-layout title="لوحة التحكم">
    <div class="flex flex-col gap-6">
        <x-ui.page-header title="لوحة التحكم" :subtitle="\App\Support\Format::date(today(), withWeekday: true).' · '.($current?->name ?? 'كل المستودعات')" />

        <div class="flex flex-wrap items-center gap-3 rounded-xl border border-line bg-white px-5 py-4">
            <span class="font-semibold">{{ $company->name }}</span>
            <x-ui.badge :tone="$company->status->tone()">{{ $company->status->label() }}</x-ui.badge>
            @if ($company->status === \App\Enums\CompanyStatus::Trial)
                <span class="text-sm text-muted">
                    @if ($company->isTrialExpired())
                        انتهت التجربة في {{ \App\Support\Format::date($company->trial_ends_at) }}
                    @else
                        تنتهي التجربة في {{ \App\Support\Format::date($company->trial_ends_at) }}
                    @endif
                </span>
            @endif
        </div>

        @if (! $hasWarehouses)
            <x-ui.empty-state title="أضف أول مستودع" description="كل مستودع إله حساب دخول خاص، وبتشتغل عليه الفواتير والنقل.">
                <x-ui.button :href="route('warehouses.index')">+ مستودع جديد</x-ui.button>
            </x-ui.empty-state>
        @else
            <x-ui.empty-state title="ما في حركات اليوم" description="صافي مبيعات اليوم والمواد المنخفضة والراكدة بيظهروا هون بعد إضافة الحركات." />
        @endif
    </div>
</x-app-layout>
