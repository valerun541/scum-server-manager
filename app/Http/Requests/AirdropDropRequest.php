<?php

namespace App\Http\Requests;

class AirdropDropRequest extends ScumRequest
{
    public function rules(): array
    {
        return [
            'x' => 'nullable|numeric',
            'y' => 'nullable|numeric',
            'z' => 'nullable|numeric',
        ];
    }
}
