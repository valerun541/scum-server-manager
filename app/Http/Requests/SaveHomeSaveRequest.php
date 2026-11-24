<?php

namespace App\Http\Requests;

class SaveHomeSaveRequest extends ScumRequest
{
    public function rules(): array
    {
        return [
            'max_locations' => 'nullable|integer|min:1|max:100',
            'vip_max_locations' => 'nullable|integer|min:1|max:100',
            'teleport_price' => 'nullable|numeric|min:0',
        ];
    }
}
