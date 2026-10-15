{{-- "Trial ends" cell: date plus a note (days left / days over). Active companies have no trial to show. --}}
@php
    use App\Enums\CompanyStatus;
    use App\Support\Format;

    $expired = $company->isTrialExpired();
    $left = $company->trialDaysLeft();
@endphp

@if ($company->status === CompanyStatus::Active)
    <span class="text-muted">—</span>
@else
    <div @class(['flex flex-col', 'font-semibold text-danger' => $expired, 'text-muted' => ! $expired])>
        <span @class(['text-amber-800' => $company->status === CompanyStatus::Trial && ! $expired && $left <= 3])>{{ Format::date($company->trial_ends_at) }}</span>
        <span class="text-xs font-semibold">
            @if ($company->status === CompanyStatus::Trial && $expired)
                انتهت من {{ Format::days($company->trialDaysOver()) }}
            @elseif ($company->status === CompanyStatus::Trial)
                {{ $left === 0 ? 'تنتهي اليوم' : 'باقي '.Format::days($left) }}
            @elseif ($company->trial_ends_at->lt(today()))
                انتهت التجربة
            @endif
        </span>
    </div>
@endif
