<?php

namespace App\Http\Requests;

class AirdropSaveRequest extends ScumRequest
{
    public function rules(): array
    {
        return [
            'chest_item' => 'nullable|string|max:255',
            'min_items' => 'nullable|integer|min:1|max:1000',
            'max_items' => 'nullable|integer|min:1|max:1000',
            'cooldown_minutes' => 'nullable|integer|min:0',
            'auto_drop_enabled' => 'nullable',
            'auto_drop_interval' => 'nullable|integer|min:1',
            'auto_drop_min_players' => 'nullable|integer|min:0',
        ];
    }
}
