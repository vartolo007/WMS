<?php

namespace App\Http\Requests;

use App\Enums\MovementType;
use App\Http\Requests\Concerns\ChecksWholeQuantities;
use App\Models\Movement;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Data shape of a purchase or sale invoice (FR-10/11, SRS 7): required fields, numbers, decimals per unit.
 * What needs database state (stock, active records, party type, discount against the subtotal)
 * is MovementService's job, so each business message has one home.
 */
class StoreMovementRequest extends FormRequest
{
    use ChecksWholeQuantities;

    /** The types this screen creates; returns have their own screen (StoreReturnRequest). */
    private const TYPES = [MovementType::Purchase, MovementType::Sale, MovementType::Transfer];

    public function authorize(): bool
    {
        return Gate::allows('create', Movement::class);
    }

    protected function prepareForValidation(): void
    {
        // A warehouse user always works on their own warehouse, whatever the request says.
        if ($this->user()->isWarehouse()) {
            $this->merge(['warehouse_id' => $this->user()->id]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // A transfer has no party, document number, discount or typed price (SRS 3.4.7); it needs a target warehouse.
        $transfer = $this->input('type') === MovementType::Transfer->value;
        $notForTransfer = fn (array $rules) => $transfer ? ['prohibited'] : $rules;

        return [
            'type' => ['required', Rule::in(array_map(fn (MovementType $type) => $type->value, self::TYPES))],
            'warehouse_id' => ['required', 'integer'],
            'target_warehouse_id' => $transfer ? ['required', 'integer'] : ['prohibited'],
            'party_id' => $notForTransfer(['nullable', 'integer']),
            'date' => ['nullable', 'date_format:Y-m-d'],
            'document_no' => $notForTransfer(['nullable', 'string', 'max:100']),
            'discount' => $notForTransfer(['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99']),
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999.999'],
            'items.*.unit_price' => $notForTransfer(['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99']),
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
            'type' => 'نوع الحركة',
            'warehouse_id' => 'المستودع',
            'target_warehouse_id' => 'المستودع المستقبِل',
            'party_id' => 'الطرف',
            'date' => 'التاريخ',
            'document_no' => 'رقم الوثيقة',
            'discount' => 'الحسم',
            'items' => 'البنود',
            'items.*.product_id' => 'المادة',
            'items.*.quantity' => 'الكمية',
            'items.*.unit_price' => 'السعر',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'أضف بندًا واحدًا على الأقل.',
            'items.min' => 'أضف بندًا واحدًا على الأقل.',
            'warehouse_id.required' => 'اختر المستودع.',
            'target_warehouse_id.required' => 'اختر المستودع المستقبِل.',
            '*.prohibited' => 'هالحقل ما بينفع بحركة النقل.',
            'items.*.quantity.gt' => 'الكمية لازم تكون أكبر من صفر.',
            'items.*.quantity.decimal' => 'الكمية بحد أقصى 3 منازل عشرية.',
            'items.*.unit_price.decimal' => 'السعر بحد أقصى منزلتين عشريتين.',
            'items.*.unit_price.min' => 'السعر ما بيكون سالب.',
            'discount.decimal' => 'الحسم بحد أقصى منزلتين عشريتين.',
            'discount.min' => 'الحسم ما بيكون سالب.',
        ];
    }

    /**
     * The movement type chosen on the screen.
     */
    public function movementType(): MovementType
    {
        return MovementType::from($this->validated('type'));
    }

    /**
     * The payload MovementService expects.
     *
     * @return array<string, mixed>
     */
    public function movementData(): array
    {
        return collect($this->validated())->except('type')->all();
    }
}
