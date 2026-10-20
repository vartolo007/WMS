<?php

namespace App\Models;

use App\Enums\MovementStatus;
use App\Enums\MovementType;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Scopes\WarehouseScope;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
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
        'cancelled_at',
        'cancelled_by',
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
            'cancelled_at' => 'datetime',
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

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function relatedMovement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_movement_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(self::class, 'related_movement_id');
    }

    /**
     * The filters of the movements list (all optional). A warehouse restriction also matches the
     * receiving side of a transfer, so a warehouse sees its incoming transfers too.
     *
     * @param  array{type?: ?string, status?: ?string, from?: ?string, to?: ?string, q?: ?string}  $filters
     */
    public function scopeFilter(Builder $query, array $filters, ?int $warehouseId = null): void
    {
        $query
            ->when($filters['type'] ?? null, fn (Builder $q, string $type) => $q->where('type', $type))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($filters['from'] ?? null, fn (Builder $q, string $from) => $q->whereDate('date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, string $to) => $q->whereDate('date', '<=', $to))
            ->when($warehouseId, fn (Builder $q, int $id) => $q->where(
                fn (Builder $w) => $w->where('warehouse_id', $id)->orWhere('target_warehouse_id', $id)
            ))
            ->search($filters['q'] ?? null);
    }

    /**
     * By movement number ("S-0142", "s142", "142"), document number, or party name.
     * LIKE wildcards typed by the user are taken literally.
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $like = '%'.addcslashes($term, '\\%_').'%';

        $query->where(function (Builder $q) use ($term, $like) {
            $q->where('document_no', 'like', $like)
                ->orWhereHas('party', fn (Builder $party) => $party->where('name', 'like', $like));

            if (preg_match('/^([A-Za-z]{1,2})-?(\d+)$/', $term, $m) && ($type = MovementType::fromPrefix($m[1]))) {
                $q->orWhere(fn (Builder $n) => $n->where('type', $type)->where('number', (int) $m[2]));
            } elseif (ctype_digit($term)) {
                $q->orWhere('number', (int) $term);
            }
        });
    }

    /**
     * "6 لتر زيت و5 كيلو أرز": the first lines of the movement in words, for the cancel dialog.
     */
    public function itemsSummary(int $limit = 3): string
    {
        $items = $this->items->loadMissing('product');

        $text = $items->take($limit)
            ->map(fn (MovementItem $item) => Format::plain((string) $item->quantity).' '.$item->product->unitLabel().' '.$item->product->name)
            ->implode(' و');

        $more = $items->count() - $limit;

        return $more > 0 ? "{$text} و{$more} مواد أخرى" : $text;
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
