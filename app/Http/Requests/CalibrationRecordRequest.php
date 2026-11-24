<?php

namespace App\Http\Requests;

class CalibrationRecordRequest extends ScumRequest
{
    public function rules(): array
    {
        return [
            'index' => 'required|integer|min:0',
            'z' => 'required|numeric',
        ];
    }
}
