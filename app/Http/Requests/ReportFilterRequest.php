<?php

namespace App\Http\Requests;

use App\Enums\ReportKind;
use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Filters of the reports centre (screen 16). All optional: each report fills in its own defaults, and the
 * missing product / party of the reports that need one is a prompt on the page, not an error.
 * References must belong to the user's company (SRS 7).
 */
class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', ReportKind::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $company = $this->user()->company_id;

        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'warehouse_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('company_id', $company)->where('role', Role::Warehouse->value)],
            'party_id' => ['nullable', 'integer', Rule::exists('parties', 'id')->where('company_id', $company)],
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('company_id', $company)],
            'group' => ['nullable', Rule::in(['product', 'party', 'warehouse'])],
            'split' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'date' => 'اليوم',
            'from' => 'من',
            'to' => 'إلى',
            'warehouse_id' => 'المستودع',
            'party_id' => 'الطرف',
            'product_id' => 'المادة',
            'group' => 'التجميع',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to.after_or_equal' => 'تاريخ النهاية قبل تاريخ البداية.',
            '*.exists' => 'القيمة المختارة غير موجودة.',
        ];
    }
}
