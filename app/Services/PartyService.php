<?php

namespace App\Services;

use App\Models\Movement;
use App\Models\Party;
use Illuminate\Validation\ValidationException;

/**
 * A party that has movements keeps its type: a purchase needs a supplier and a sale a customer (SRS 7).
 */
class PartyService
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(Party $party, array $data): Party
    {
        // Every warehouse's movements count here, not only the current user's (no warehouse scope).
        if (isset($data['type']) && $data['type'] !== $party->type->value && Movement::withoutGlobalScopes()->where('party_id', $party->id)->exists()) {
            throw ValidationException::withMessages(['type' => 'ما بتقدر تغيّر النوع لأنه في حركات على هالطرف.']);
        }

        $party->update($data);

        return $party;
    }
}
