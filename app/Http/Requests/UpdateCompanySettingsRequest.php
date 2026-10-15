<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateSettings', $this->user()->company);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'primary_color' => ['required', Rule::in(config('wms.palette'))],
            'currency_symbol' => ['nullable', 'string', 'max:10'],
            'markup_percent' => ['required', 'numeric', 'decimal:0,2', 'between:100,1000'],
            // `mimes` checks the real content type, not only the extension; SVG is not in the list (SRS 4.4).
            'logo' => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'اسم الشركة',
            'primary_color' => 'اللون الأساسي',
            'currency_symbol' => 'رمز العملة',
            'markup_percent' => 'نسبة التسعير',
            'logo' => 'الشعار',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'primary_color.in' => 'اختر لون من القائمة.',
            'markup_percent.numeric' => 'نسبة التسعير لازم تكون رقم.',
            'markup_percent.decimal' => 'نسبة التسعير حتى منزلتين عشريتين.',
            'markup_percent.between' => 'نسبة التسعير لازم تكون بين 100 و1000.',
            'logo.image' => 'الشعار لازم يكون صورة PNG أو JPG أو WEBP.',
            'logo.mimes' => 'الشعار لازم يكون PNG أو JPG أو WEBP.',
            'logo.max' => 'حجم الشعار لازم يكون أقل من 1 ميغا.',
        ];
    }
}
