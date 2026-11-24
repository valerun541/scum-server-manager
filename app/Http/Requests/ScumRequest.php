<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

abstract class ScumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Служебные поля CSRF и method-override не участвуют в проверке «неизвестных полей».
     */
    public function validationData(): array
    {
        return $this->except(['_token', '_method']);
    }

    protected function shouldFailOnUnknownFields(): bool
    {
        return true;
    }

    protected function validateNoUnknownFields(Validator $validator): void
    {
        $allowedKeys = array_keys($this->validationRules());

        $input = $this->isJson() ? $this->json()->all() : $this->validationData();

        foreach (array_keys(Arr::dot($input)) as $inputKey) {
            if (! $this->isKnownField($inputKey, $allowedKeys)) {
                $validator->errors()->add($inputKey, trans('validation.prohibited', [
                    'attribute' => str_replace('_', ' ', $inputKey),
                ]));
            }
        }
    }
}
