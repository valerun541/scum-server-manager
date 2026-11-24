<?php

namespace App\Http\Requests;

class ScumDbSkillRequest extends ScumRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'level' => 'required|integer|min:0|max:4',
        ];
    }
}
