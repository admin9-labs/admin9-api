<?php

namespace App\Http\Requests\System;

use Illuminate\Validation\Rule;
use Mitoop\LaravelEfficientFormRequest\EfficientSceneFormRequest;

class DictionaryItemRequest extends EfficientSceneFormRequest
{
    public function storeRules(): array
    {
        return [
            'dictionary_type_id' => ['bail', 'required', 'integer', Rule::exists('dictionary_types', 'id')],
            'label' => ['bail', 'required', 'string', 'max:255', 'regex:/^[^<>]*$/'],
            'value' => ['bail', 'required', 'string', 'max:255', 'regex:/^[^<>]*$/',
                Rule::unique('dictionary_items')->where('dictionary_type_id', $this->input('dictionary_type_id'))],
            'sort' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function updateRules(): array
    {
        return [
            'label' => ['bail', 'required', 'string', 'max:255', 'regex:/^[^<>]*$/'],
            'value' => ['bail', 'required', 'string', 'max:255', 'regex:/^[^<>]*$/',
                Rule::unique('dictionary_items')->where('dictionary_type_id', $this->route('dict_item')?->dictionary_type_id)->ignore($this->route('dict_item'))],
            'sort' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
