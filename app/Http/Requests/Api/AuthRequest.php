<?php

namespace App\Http\Requests\Api;

use Mitoop\LaravelEfficientFormRequest\EfficientSceneFormRequest;

/**
 * @scaffold
 */
class AuthRequest extends EfficientSceneFormRequest
{
    public function loginRules(): array
    {
        return [
            'email' => ['bail', 'required', 'string', 'email:filter', 'max:255'],
            'password' => ['bail', 'required', 'string', 'min:8', 'max:128'],
        ];
    }
}
