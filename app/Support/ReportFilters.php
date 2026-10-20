<?php

namespace App\Support;

use App\Enums\MovementType;
use App\Enums\StockFilter;
use App\Models\User;

/**
 * The variables of the report engine (SRS 3.6.1). Dates are `Y-m-d` strings, inclusive on both ends.
 */
final readonly class ReportFilters
{
    /**
     * @param  list<MovementType>  $types  empty = every type
     * @param  string  $groupBy  product | party | warehouse (summary reports)
     */
    public function __construct(
        public array $types = [],
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public ?int $warehouseId = null,
        public ?int $partyId = null,
        public ?int $productId = null,
        public ?StockFilter $stockFilter = null,
        public string $groupBy = 'product',
        public bool $splitByWarehouse = false,
    ) {}

    /**
     * A warehouse user's reports are always limited to their own warehouse (SRS 3.6.1).
     */
    public function forUser(User $user): self
    {
        return $user->isWarehouse() ? $this->with(warehouseId: $user->id) : $this;
    }

    public function with(mixed ...$changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }
}
