<?php

namespace App\Http\Requests;

use App\Support\Rules\PartyRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Data shape of a customer / supplier (FR-08, SRS 7). Create and update differ only in permission.
 * The rules themselves live in `PartyRules`, shared with the CSV import.
 */
abstract class PartyRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return PartyRules::rules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return PartyRules::attributes();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return PartyRules::messages();
    }
}
