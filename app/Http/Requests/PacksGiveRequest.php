<?php

namespace App\Http\Requests;

class PacksGiveRequest extends ScumRequest
{
    public function rules(): array
    {
        return [
            'steam_id' => 'required|string|max:32',
            'type' => 'required|string|in:starter,daily',
        ];
    }
}
