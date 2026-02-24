<?php

namespace App\Http\Requests\Api;

use Mitoop\LaravelEfficientFormRequest\EfficientSceneFormRequest;

class ResetPasswordRequest extends EfficientSceneFormRequest
{
    public function resetRules(): array
    {
        return [
            'token' => ['bail', 'required', 'string'],
            'email' => ['bail', 'required', 'string', 'email:filter', 'max:255'],
            'password' => ['bail', 'required', 'string', 'min:8', 'max:128', 'confirmed'],
            'password_confirmation' => ['bail', 'required', 'string', 'min:8', 'max:128'],
        ];
    }
}
