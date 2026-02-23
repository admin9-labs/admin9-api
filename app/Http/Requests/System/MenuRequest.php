<?php

namespace App\Http\Requests\System;

use App\Models\Menu;
use Illuminate\Validation\Rule;
use Mitoop\LaravelEfficientFormRequest\EfficientSceneFormRequest;

class MenuRequest extends EfficientSceneFormRequest
{
    public function storeRules(): array
    {
        return [
            ...$this->commonRules(),
            'name' => ['bail', 'required', 'string', 'max:255', 'regex:/^[^<>]*$/', Rule::unique('menus', 'name')],
            'permission' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_.]+$/', Rule::unique('menus', 'permission')],
        ];
    }

    public function updateRules(): array
    {
        return [
            ...$this->commonRules(),
            'name' => ['bail', 'required', 'string', 'max:255', 'regex:/^[^<>]*$/', Rule::unique('menus', 'name')->ignore($this->route('menu'))],
            'permission' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_.]+$/', Rule::unique('menus', 'permission')->ignore($this->route('menu'))],
        ];
    }

    private function commonRules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', Rule::exists('menus', 'id')],
            'type' => ['nullable', 'integer', Rule::in([Menu::TYPE_DIRECTORY, Menu::TYPE_MENU, Menu::TYPE_BUTTON])],
            'path' => ['nullable', 'string', 'max:255', 'regex:/^[^<>]*$/'],
            'component' => ['nullable', 'string', 'max:255', 'regex:/^[^<>]*$/'],
            'locale' => ['bail', 'required', 'string', 'max:255', 'regex:/^[^<>]*$/'],
            'icon' => ['nullable', 'string', 'max:255', 'regex:/^[^<>]*$/'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'is_hidden' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
