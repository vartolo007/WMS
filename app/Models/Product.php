<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\Format;
use App\Support\Units;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared catalogue of the company (FR-07). Never deleted: deactivate instead (T12).
 */
class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'barcode',
        'name',
        'unit',
        'location',
        'custom_sale_price',
        'min_stock',
        'is_active',
    ];

    /**
     * Decimals stay strings (golden rule 3): never cast to float.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'custom_sale_price' => 'decimal:2',
            'min_stock' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Partial match on name or barcode. LIKE wildcards typed by the user are taken literally.
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $like = '%'.addcslashes($term, '\\%_').'%';

        $query->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('barcode', 'like', $like));
    }

    public function allowsFraction(): bool
    {
        return Units::allowsFraction($this->unit);
    }

    /**
     * A quantity of this product as shown on screen: three decimals for units that take fractions,
     * a whole number for the others (SRS 13.2.14).
     */
    public function displayQuantity(string $quantity): string
    {
        $formatted = Format::quantity($quantity);

        // "1,250.000" stays; for a unit without fractions "1,250.000" becomes "1,250".
        return $this->allowsFraction() ? $formatted : substr($formatted, 0, -4);
    }

    public function unitLabel(): string
    {
        return Units::label($this->unit);
    }
}
