<?php

namespace App\Http\Requests\System;

use Illuminate\Validation\Rule;
use Mitoop\LaravelEfficientFormRequest\EfficientSceneFormRequest;

/**
 * @scaffold
 */
class RoleRequest extends EfficientSceneFormRequest
{
    public function storeRules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'min:2', 'max:255', 'alpha_dash', Rule::unique('roles', 'name')->where('guard_name', 'api')],
            'locale' => ['nullable', 'string', 'max:255', 'regex:/^[^<>]*$/'],
            'menu_ids' => ['nullable', 'array', 'distinct'],
            'menu_ids.*' => ['integer', Rule::exists('menus', 'id')],
        ];
    }

    public function updateRules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'min:2', 'max:255', 'alpha_dash', Rule::unique('roles', 'name')->where('guard_name', 'api')->ignore($this->route('role'))],
            'locale' => ['nullable', 'string', 'max:255', 'regex:/^[^<>]*$/'],
            'menu_ids' => ['nullable', 'array', 'distinct'],
            'menu_ids.*' => ['integer', Rule::exists('menus', 'id')],
        ];
    }
}
