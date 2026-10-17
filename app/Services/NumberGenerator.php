<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Models\Movement;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Next movement number per (company, type) (T11): lock the company row, then MAX + 1.
 * The lock makes two simultaneous saves wait for each other, so numbers never repeat.
 */
class NumberGenerator
{
    public function next(int $companyId, MovementType $type): int
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Movement numbers must be generated inside the movement transaction.');
        }

        DB::table('companies')->where('id', $companyId)->lockForUpdate()->value('id');

        $max = Movement::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('type', $type)
            ->max('number');

        return ((int) $max) + 1;
    }
}
