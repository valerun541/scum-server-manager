<?php

namespace App\Http\Requests;

class WargmImportRequest extends ScumRequest
{
    public function rules(): array
    {
        return [
            'import_json' => 'required|string|max:1000000',
        ];
    }
}
