<?php

namespace App\Http\Requests;

class AutomationTestRequest extends ScumRequest
{
    public function rules(): array
    {
        return [
            'steam_id' => 'nullable|string|max:32',
            'player_name' => 'nullable|string|max:255',
            'message' => 'nullable|string|max:5000',
        ];
    }
}
