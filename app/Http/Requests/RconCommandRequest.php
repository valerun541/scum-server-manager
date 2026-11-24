<?php

namespace App\Http\Requests;

class RconCommandRequest extends ScumRequest
{
    public function rules(): array
    {
        return [
            'command' => 'required|string|max:2000',
        ];
    }
}
