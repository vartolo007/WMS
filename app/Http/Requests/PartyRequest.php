<?php

namespace App\Http\Requests;

use App\Enums\PartyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Data shape of a customer / supplier (FR-08, SRS 7). Create and update differ only in permission.
 */
abstract class PartyRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
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
    public function attributes(): array
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
    public function messages(): array
    {
        return [
            'name.required' => 'الاسم إلزامي.',
            'phone.regex' => 'اكتب رقم هاتف صحيح، أرقام فقط.',
        ];
    }
}
