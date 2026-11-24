<?php

namespace App\Http\Requests;

class PacksCooldownResetRequest extends ScumRequest
{
    public function rules(): array
    {
        return [
            'steam_id' => 'required|string|max:32',
            'type' => 'nullable|string|in:starter,daily',
        ];
    }
}
