<?php

namespace App\Http\Requests\System;

use Illuminate\Validation\Rule;
use Mitoop\LaravelEfficientFormRequest\EfficientSceneFormRequest;

class DictionaryTypeRequest extends EfficientSceneFormRequest
{
    public function storeRules(): array
    {
        return [
            ...$this->commonRules(),
            'name' => ['bail', 'required', 'string', 'max:255', 'regex:/^[^<>]*$/', Rule::unique('dictionary_types', 'name')],
            'code' => ['bail', 'required', 'string', 'max:255', 'alpha_dash', Rule::unique('dictionary_types', 'code')],
        ];
    }

    public function updateRules(): array
    {
        return [
            ...$this->commonRules(),
            'name' => ['bail', 'required', 'string', 'max:255', 'regex:/^[^<>]*$/', Rule::unique('dictionary_types', 'name')->ignore($this->route('dict_type'))],
            'code' => ['bail', 'required', 'string', 'max:255', 'alpha_dash', Rule::unique('dictionary_types', 'code')->ignore($this->route('dict_type'))],
        ];
    }

    private function commonRules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:255', 'regex:/^[^<>]*$/'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
