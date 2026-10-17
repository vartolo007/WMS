<?php

namespace App\Http\Requests;

use App\Enums\MovementType;
use App\Models\Movement;
use App\Models\Product;
use App\Support\Money;
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
    /** The types this screen can create. Returns and transfers join in later phases. */
    private const TYPES = [MovementType::Purchase, MovementType::Sale];

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
        return [
            'type' => ['required', Rule::in(array_map(fn (MovementType $type) => $type->value, self::TYPES))],
            'warehouse_id' => ['required', 'integer'],
            'party_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'document_no' => ['nullable', 'string', 'max:100'],
            'discount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999.999'],
            'items.*.unit_price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
        ];
    }

    /**
     * A unit that takes no fractions (box, piece) takes whole quantities only (SRS 3.4.2, 13.2.14).
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $items = (array) $this->input('items', []);
            $products = Product::query()->whereIn('id', array_filter(array_column($items, 'product_id'), 'is_numeric'))->get()->keyBy('id');

            foreach ($items as $index => $item) {
                $product = $products->get($item['product_id'] ?? null);
                $quantity = (string) ($item['quantity'] ?? '');

                if ($product === null || ! is_numeric($quantity) || $product->allowsFraction()) {
                    continue;
                }

                if (Money::compare($quantity, (string) (int) $quantity) !== 0) {
                    $validator->errors()->add("items.{$index}.quantity", 'هالوحدة ما بتقبل كسور، اكتب عدد صحيح.');
                }
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'نوع الحركة',
            'warehouse_id' => 'المستودع',
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
