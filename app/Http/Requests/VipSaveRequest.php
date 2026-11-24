<?php

namespace App\Http\Requests;

class VipSaveRequest extends ScumRequest
{
    public function rules(): array
    {
        return [
            'starter_items' => 'nullable|string|max:100000',
            'starter_money' => 'nullable|numeric|min:0',
            'starter_gold' => 'nullable|numeric|min:0',
            'starter_fame' => 'nullable|numeric|min:0',
            'daily_items' => 'nullable|string|max:100000',
            'daily_money' => 'nullable|numeric|min:0',
            'daily_gold' => 'nullable|numeric|min:0',
            'daily_fame' => 'nullable|numeric|min:0',
        ];
    }
}
