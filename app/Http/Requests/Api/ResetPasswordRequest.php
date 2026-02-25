<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rules\Password;
use Mitoop\LaravelEfficientFormRequest\EfficientSceneFormRequest;

class ResetPasswordRequest extends EfficientSceneFormRequest
{
    public function resetRules(): array
    {
        return [
            'token' => ['bail', 'required', 'string'],
            'email' => ['bail', 'required', 'string', 'email:filter', 'max:255'],
            'password' => ['bail', 'required', 'string', Password::min(8)->mixedCase()->numbers()->max(128), 'confirmed'],
            'password_confirmation' => ['bail', 'required', 'string'],
        ];
    }
}
