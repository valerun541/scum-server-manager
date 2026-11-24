<?php

namespace App\Http\Requests;

class PacksSaveRequest extends ScumRequest
{
    public function rules(): array
    {
        return [
            'starter_enabled' => 'sometimes',
            'starter_items' => 'nullable|string|max:20000',
            'starter_cooldown' => 'nullable|integer|min:0',
            'starter_cooldown_input' => 'nullable|integer|min:0',
            'daily_enabled' => 'sometimes',
            'daily_items' => 'nullable|string|max:20000',
            'daily_cooldown' => 'nullable|integer|min:0',
            'daily_cooldown_input' => 'nullable|integer|min:0',
        ];
    }
}
