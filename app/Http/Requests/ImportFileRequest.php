<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The CSV a user uploads for an import: shape only (a file, CSV or text, up to 2 MB).
 * What is inside is checked by the import service, with the same rules as the forms.
 */
class ImportFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ImportController authorises per kind (products / parties)
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['file' => 'الملف'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'اختر ملف CSV.',
            'file.mimes' => 'الملف لازم يكون CSV (احفظه من Excel بصيغة CSV).',
            'file.max' => 'الملف أكبر من 2 ميغا.',
        ];
    }
}
