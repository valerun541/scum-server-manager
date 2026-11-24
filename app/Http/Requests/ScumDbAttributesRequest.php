<?php

namespace App\Http\Requests;

class ScumDbAttributesRequest extends ScumRequest
{
    public function rules(): array
    {
        return [
            'strength' => 'nullable|numeric|min:0.5|max:12',
            'constitution' => 'nullable|numeric|min:0.5|max:12',
            'dexterity' => 'nullable|numeric|min:0.5|max:12',
            'intelligence' => 'nullable|numeric|min:0.5|max:12',
        ];
    }
}
