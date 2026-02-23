<?php

namespace App\Http\Requests\System;

use Illuminate\Validation\Rule;
use Mitoop\LaravelEfficientFormRequest\EfficientSceneFormRequest;

/**
 * @scaffold
 */
class UserRequest extends EfficientSceneFormRequest
{
    public function updateRules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'min:2', 'max:255', 'regex:/^[^<>]*$/'],
            'email' => ['bail', 'required', 'string', 'email:filter', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'role_ids' => ['nullable', 'array', 'distinct'],
            'role_ids.*' => ['integer', Rule::exists('roles', 'id')->where('guard_name', 'api')],
        ];
    }

    public function toggleStatusRules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }
}
