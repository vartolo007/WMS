<x-app-layout title="لوحة تحكم المنصة">
    <div class="flex flex-col gap-6">
        <x-ui.page-header title="لوحة تحكم المنصة" :subtitle="\App\Support\Format::date(today(), withWeekday: true)" />

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (\App\Enums\CompanyStatus::cases() as $status)
                <div class="flex flex-col gap-2 rounded-xl border border-line bg-white p-5">
                    <x-ui.badge :tone="$status->tone()" class="self-start">{{ $status->label() }}</x-ui.badge>
                    <div class="text-[28px] font-bold tabular-nums">{{ $counts[$status->value] ?? 0 }}</div>
                </div>
            @endforeach

            <a href="{{ route('admin.companies.index') }}" class="flex flex-col gap-2 rounded-xl border border-line bg-white p-5 hover:border-line-strong">
                <span class="text-[13px] font-medium text-muted">انتهت تجربتها</span>
                <div class="text-[28px] font-bold tabular-nums text-warning">{{ $expiredTrials }}</div>
            </a>
        </div>
    </div>
</x-app-layout>
