<?php

namespace App\Support\Rules;

use App\Enums\PartyType;
use Illuminate\Validation\Rule;

/**
 * The data-shape rules of a customer / supplier (FR-08, SRS 7), shared by the form and the CSV import.
 */
class PartyRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(PartyType::class)],
            // Digits (any script), spaces, + - ( ) only.
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[\d\s+\-()]+$/u'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'name' => 'الاسم',
            'type' => 'النوع',
            'phone' => 'الهاتف',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => 'الاسم إلزامي.',
            'phone.regex' => 'اكتب رقم هاتف صحيح، أرقام فقط.',
        ];
    }
}
