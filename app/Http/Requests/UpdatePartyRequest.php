<?php

namespace App\Http\Requests;

class UpdatePartyRequest extends PartyRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('party'));
    }
}
