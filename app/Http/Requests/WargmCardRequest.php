<?php

namespace App\Http\Requests;

class WargmCardRequest extends ScumRequest
{
    public function rules(): array
    {
        return [
            'card_id' => 'nullable|integer|min:1',
            'name' => 'required|string|max:255',
            'shop_item_id' => 'required|string|max:255',
            'enabled' => 'sometimes',
            'items_json' => 'nullable|string|max:100000',
        ];
    }
}
