<?php

namespace App\Http\Requests;

class WargmCheckRequest extends ScumRequest
{
    public function rules(): array
    {
        return [
            'steam_id' => 'required|string|max:32',
        ];
    }
}
