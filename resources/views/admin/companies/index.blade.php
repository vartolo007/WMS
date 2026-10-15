{{-- Screen 19 (design batch 7): companies and subscriptions. Also the platform dashboard (FR-24): the cards are the statistics. --}}
@php
    use App\Enums\CompanyFilter;
    use App\Enums\CompanyStatus;
    use App\Support\Format;

    $countColor = ['neutral' => 'text-ink', 'warning' => 'text-amber-800', 'danger' => 'text-danger', 'success' => 'text-green-800'];
    $listUrl = fn (CompanyFilter $f) => route('admin.companies.index', array_filter(['status' => $f === CompanyFilter::All ? null : $f->value, 'q' => $search]));
@endphp

<x-app-layout title="الشركات">
    <div class="flex flex-col gap-6">
        <x-ui.page-header title="الشركات" subtitle="الاشتراكات وحالاتها. الشركات اللي انتهت تجربتها بتظهر أول القائمة للتواصل معها." />

        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
            @foreach (CompanyFilter::cases() as $card)
                <a href="{{ $listUrl($card) }}" @class([
                    'flex flex-col gap-1 rounded-xl bg-white px-5 py-4',
                    'border-2 border-ink' => $filter === $card,
                    'border border-line hover:border-line-strong' => $filter !== $card,
                ]) @if ($filter === $card) aria-current="true" @endif>
                    <span class="text-sm text-muted">{{ $card->label() }}</span>
                    <b class="text-[26px] tabular-nums {{ $countColor[$card->tone()] }}">{{ $counts[$card->value] }}</b>
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.companies.index') }}" class="flex items-center gap-3 sm:max-w-xl">
            @if ($filter !== CompanyFilter::All)
                <input type="hidden" name="status" value="{{ $filter->value }}">
            @endif
            <div class="min-w-0 flex-1">
                <x-ui.input name="q" type="search" :value="$search" placeholder="بحث باسم الشركة أو صاحبها أو البريد" autocomplete="off" />
            </div>
            <x-ui.button variant="secondary">بحث</x-ui.button>
        </form>

        @if ($errors->any())
            <x-ui.alert>{{ $errors->first() }}</x-ui.alert>
        @endif

        @if ($companies->isEmpty())
            <x-ui.empty-state title="ما في شركات بهالفلتر" description="امسح البحث أو اختر «كل الشركات».">
                <x-ui.button variant="secondary" :href="route('admin.companies.index')">عرض الكل</x-ui.button>
            </x-ui.empty-state>
        @else
            {{-- Desktop: table. An expired trial gets a light red row with a side bar. --}}
            <x-ui.table class="hidden md:block">
                <thead>
                    <tr>
                        <x-ui.th>الشركة</x-ui.th>
                        <x-ui.th>صاحبها</x-ui.th>
                        <x-ui.th>تاريخ التسجيل</x-ui.th>
                        <x-ui.th>الحالة</x-ui.th>
                        <x-ui.th>انتهاء التجربة</x-ui.th>
                        <x-ui.th></x-ui.th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($companies as $company)
                        <tr @class(['bg-danger-soft' => $company->isTrialExpired()])>
                            <x-ui.td :class="$company->isTrialExpired() ? 'border-s-4 border-s-danger' : ''">
                                <span class="flex items-center gap-2.5">
                                    {{-- The initial takes the company's own colour (one of the approved palette). --}}
                                    <span class="flex h-8 w-8 flex-none items-center justify-center rounded-lg text-sm font-bold text-white" style="background: {{ $company->primary_color }}">{{ mb_substr($company->name, 0, 1) }}</span>
                                    <b class="font-semibold">{{ $company->name }}</b>
                                </span>
                            </x-ui.td>
                            <x-ui.td>
                                <div class="flex flex-col">
                                    <span>{{ $company->owner?->name ?? '—' }}</span>
                                    <span class="text-xs text-muted" dir="ltr" style="text-align: right">{{ $company->owner?->email }}</span>
                                </div>
                            </x-ui.td>
                            <x-ui.td class="whitespace-nowrap text-muted">{{ Format::date($company->created_at) }}</x-ui.td>
                            <x-ui.td><x-ui.badge :tone="$company->status->tone()">{{ $company->status->label() }}</x-ui.badge></x-ui.td>
                            <x-ui.td class="whitespace-nowrap">@include('admin.companies.partials.trial-end')</x-ui.td>
                            <x-ui.td class="text-left">@include('admin.companies.partials.actions')</x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>

            {{-- Mobile: one card per company --}}
            <ul class="flex flex-col gap-3 md:hidden">
                @foreach ($companies as $company)
                    <li @class([
                        'flex flex-col gap-3 rounded-[10px] border bg-white p-4',
                        'border-s-4 border-danger-border border-s-danger bg-danger-soft' => $company->isTrialExpired(),
                        'border-line' => ! $company->isTrialExpired(),
                    ])>
                        <div class="flex items-start justify-between gap-3">
                            <span class="flex items-center gap-2.5">
                                <span class="flex h-8 w-8 flex-none items-center justify-center rounded-lg text-sm font-bold text-white" style="background: {{ $company->primary_color }}">{{ mb_substr($company->name, 0, 1) }}</span>
                                <b class="font-semibold">{{ $company->name }}</b>
                            </span>
                            <x-ui.badge :tone="$company->status->tone()">{{ $company->status->label() }}</x-ui.badge>
                        </div>
                        <div class="flex flex-col text-sm">
                            <span>{{ $company->owner?->name ?? '—' }}</span>
                            <span class="text-xs text-muted" dir="ltr" style="text-align: right">{{ $company->owner?->email }}</span>
                        </div>
                        <div class="flex justify-between gap-3 text-sm">
                            <span class="text-muted">سجّلت {{ Format::date($company->created_at) }}</span>
                            @include('admin.companies.partials.trial-end')
                        </div>
                        @include('admin.companies.partials.actions')
                    </li>
                @endforeach
            </ul>

            {{ $companies->links() }}
        @endif
    </div>
</x-app-layout>
