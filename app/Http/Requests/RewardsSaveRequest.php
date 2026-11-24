<?php

namespace App\Http\Requests;

class RewardsSaveRequest extends ScumRequest
{
    public function rules(): array
    {
        return [
            'hourly_enabled' => 'nullable',
            'hourly_money' => 'nullable|numeric|min:0',
            'hourly_gold' => 'nullable|numeric|min:0',
            'hourly_fame' => 'nullable|numeric|min:0',
            'top_enabled' => 'nullable',
            'top_interval_days' => 'nullable|integer|min:1',
            'top_count' => 'nullable|integer|min:1|max:50',
            'top_money' => 'nullable|numeric|min:0',
            'top_gold' => 'nullable|numeric|min:0',
            'top_fame' => 'nullable|numeric|min:0',
        ];
    }
}
