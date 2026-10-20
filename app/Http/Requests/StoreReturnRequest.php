<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ChecksWholeQuantities;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Data shape of a return (FR-15): the returned quantity per product. What needs the database (returnable
 * quantity, stock, the original invoice) is MovementService::return()'s job.
 */
class StoreReturnRequest extends FormRequest
{
    use ChecksWholeQuantities;

    public function authorize(): bool
    {
        return Gate::allows('createReturn', $this->route('movement'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999.999'],
        ];
    }

    /**
     * @return array<callable>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->checkWholeQuantities($validator)];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'date' => 'التاريخ',
            'items' => 'البنود',
            'items.*.product_id' => 'المادة',
            'items.*.quantity' => 'الكمية',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'أدخل كمية مرتجعة لبند واحد على الأقل.',
            'items.min' => 'أدخل كمية مرتجعة لبند واحد على الأقل.',
            'items.*.quantity.gt' => 'الكمية لازم تكون أكبر من صفر.',
            'items.*.quantity.decimal' => 'الكمية بحد أقصى 3 منازل عشرية.',
        ];
    }
}
