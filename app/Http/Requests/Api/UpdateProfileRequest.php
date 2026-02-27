<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'sometimes', 'string', 'max:50', 'regex:/^[^<>]*$/'],
            'password' => ['bail', 'sometimes', 'string', Password::min(8)->mixedCase()->numbers()->max(128), 'confirmed'],
            'current_password' => ['bail', 'required_with:password', 'string', 'current_password:api'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                if (! $this->hasAny(['name', 'password'])) {
                    $validator->errors()->add('_', 'At least one field (name or password) must be provided.');
                }
            },
        ];
    }
}
