<?php

namespace App\Models;

use App\Enums\PartyType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Customer or supplier of the company (FR-08). One table for both; never deleted (T12).
 */
class Party extends Model
{
    /** @use HasFactory<\Database\Factories\PartyFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'type',
        'is_active',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => PartyType::class,
            'is_active' => 'boolean',
        ];
    }

    public function scopeOfType(Builder $query, ?PartyType $type): void
    {
        if ($type !== null) {
            $query->where('type', $type);
        }
    }

    /**
     * Partial match on name or phone. LIKE wildcards typed by the user are taken literally.
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $like = '%'.addcslashes($term, '\\%_').'%';

        $query->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('phone', 'like', $like));
    }
}
