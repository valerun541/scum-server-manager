<?php

namespace App\Http\Requests;

class ScumDbTableRequest extends ScumRequest
{
    protected function shouldFailOnUnknownFields(): bool
    {
        return false;
    }

    public function rules(): array
    {
        return [
            '_id' => 'nullable|array',
            'cols' => 'nullable|array',
        ];
    }
}
