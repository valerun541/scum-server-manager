<?php

namespace App\Http\Requests;

class BlacklistSaveRequest extends ScumRequest
{
    public function rules(): array
    {
        return [
            'blacklist' => 'nullable|string|max:50000',
        ];
    }
}
