<?php

namespace App\Http\Requests;

class ConfigFileRequest extends ScumRequest
{
    public function rules(): array
    {
        return [
            'file' => 'nullable|string|max:255',
            'path' => 'nullable|string|max:500',
            'content' => 'nullable|string|max:1000000',
            'encoding' => 'nullable|string|in:utf-8,utf-16le,windows-1251,cp1251',
        ];
    }
}
