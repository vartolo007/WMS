<?php

namespace App\Models;

use App\Enums\MovementType;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Scopes\WarehouseScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The effect of one movement line on one warehouse's stock; the source of every balance and report.
 * Golden rule 1: only PostingService creates or removes these rows (a test enforces it).
 */
class Operation extends Model
{
    /** @use HasFactory<\Database\Factories\OperationFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'warehouse_id',
        'movement_id',
        'movement_item_id',
        'product_id',
        'party_id',
        'type',
        'date',
        'qty_in',
        'qty_out',
        'unit_price',
        'value',
        'net_value',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new WarehouseScope);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'date' => 'date',
            'qty_in' => 'decimal:3',
            'qty_out' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'value' => 'decimal:2',
            'net_value' => 'decimal:2',
        ];
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(Movement::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'warehouse_id');
    }
}
