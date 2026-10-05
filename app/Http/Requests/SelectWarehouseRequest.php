<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SelectWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isOwner();
    }

    /**
     * Empty = "all warehouses". Otherwise a warehouse account of the owner's own company.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')
                    ->where('company_id', $this->user()->company_id)
                    ->where('role', Role::Warehouse->value),
            ],
        ];
    }
}
