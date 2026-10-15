<?php

namespace App\Http\Requests;

use App\Models\Party;

class StorePartyRequest extends PartyRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Party::class);
    }
}
