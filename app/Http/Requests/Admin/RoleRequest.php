<?php

namespace App\Http\Requests\Admin;

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
            'permission_ids' => ['nullable', 'array', 'distinct'],
            'permission_ids.*' => ['integer', Rule::exists('permissions', 'id')->where('guard_name', 'api')],
        ];
    }

    public function updateRules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'min:2', 'max:255', 'alpha_dash', Rule::unique('roles', 'name')->where('guard_name', 'api')->ignore($this->route('role'))],
            'permission_ids' => ['nullable', 'array', 'distinct'],
            'permission_ids.*' => ['integer', Rule::exists('permissions', 'id')->where('guard_name', 'api')],
        ];
    }
}
