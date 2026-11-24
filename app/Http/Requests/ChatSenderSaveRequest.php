<?php

namespace App\Http\Requests;

class ChatSenderSaveRequest extends ScumRequest
{
    public function rules(): array
    {
        return [
            'chat_sender' => 'nullable|string|max:255',
        ];
    }
}
