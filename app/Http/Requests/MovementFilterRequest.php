<?php

namespace App\Http\Requests;

use App\Enums\MovementStatus;
use App\Enums\MovementType;
use App\Models\Movement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Filters of the movements list (screen 8). All optional; a malformed filter is a 422, never a 500.
 */
class MovementFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', Movement::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::enum(MovementType::class)],
            'status' => ['nullable', Rule::enum(MovementStatus::class)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'warehouse_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:50'],
        ];
    }
}
