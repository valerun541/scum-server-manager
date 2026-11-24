<?php

namespace App\Http\Requests;

class VipPlayerRequest extends ScumRequest
{
    public function rules(): array
    {
        return [
            'steam_id' => 'required|string|max:32',
            'days' => 'nullable|integer|min:1',
        ];
    }
}
