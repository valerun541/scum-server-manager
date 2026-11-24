<?php

namespace App\Http\Requests;

class TeleportSaveRequest extends ScumRequest
{
    public function rules(): array
    {
        return [
            'locations_json' => 'nullable|string|max:100000',
        ];
    }
}
