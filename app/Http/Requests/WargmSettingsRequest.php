<?php

namespace App\Http\Requests;

class WargmSettingsRequest extends ScumRequest
{
    public function rules(): array
    {
        return [
            'apiUrl' => 'nullable|string|max:255',
            'shopId' => 'nullable|string|max:255',
            'apiKey' => 'nullable|string|max:500',
            'timeout' => 'nullable|integer|min:1|max:300',
            'duplicateCheckMinutes' => 'nullable|integer|min:0',
            'commandCooldownSeconds' => 'nullable|integer|min:0',
            'maxItemsPerCard' => 'nullable|integer|min:1|max:1000',
        ];
    }
}
