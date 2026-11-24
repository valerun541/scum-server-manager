<?php

namespace App\Http\Requests;

class ScumDbWalletRequest extends ScumRequest
{
    public function rules(): array
    {
        return [
            'wallet' => 'required|numeric|min:0',
            'gold' => 'nullable|numeric|min:0',
        ];
    }
}
