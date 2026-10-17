<?php

namespace App\Models;

use App\Enums\MovementStatus;
use App\Enums\MovementType;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Scopes\WarehouseScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Header of an invoice or transfer. Never edited or deleted after saving: it is cancelled (golden rule 9).
 * Totals are stored because they never change after saving (SRS 6.2).
 */
class Movement extends Model
{
    /** @use HasFactory<\Database\Factories\MovementFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'warehouse_id',
        'target_warehouse_id',
        'type',
        'number',
        'document_no',
        'status',
        'related_movement_id',
        'party_id',
        'date',
        'subtotal',
        'discount',
        'total',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new WarehouseScope(includeTarget: true));
    }

    /**
     * Decimals stay strings (golden rule 3): never cast to float.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'status' => MovementStatus::class,
            'date' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(MovementItem::class);
    }

    public function operations(): HasMany
    {
        return $this->hasMany(Operation::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'warehouse_id');
    }

    public function targetWarehouse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_warehouse_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function relatedMovement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_movement_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(self::class, 'related_movement_id');
    }

    public function isCancelled(): bool
    {
        return $this->status === MovementStatus::Cancelled;
    }

    /**
     * "S-0142".
     */
    public function displayNumber(): string
    {
        return $this->type->displayNumber($this->number);
    }
}
