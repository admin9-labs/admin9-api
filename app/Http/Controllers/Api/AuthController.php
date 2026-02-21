<?php

namespace App\Http\Controllers\Api;

use App\Events\AuditLoginAttempted;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AuthRequest;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenBlacklistedException;

/**
 * @scaffold — JWT login/logout/refresh/me.
 */
#[Group('Auth', weight: 0)]
class AuthController extends Controller
{
    public function __construct(#[Auth('api')] protected Guard $auth) {}

    /**
     * Login.
     */
    public function login(AuthRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        if (! $token = $this->auth->attempt($credentials)) {
            AuditLoginAttempted::dispatch('login_failed', null, [
                'email' => $request->input('email'),
            ]);

            return $this->error('Invalid credentials', 401);
        }

        $user = $this->auth->user();
        if (! $user->is_active) {
            $this->auth->logout();

            AuditLoginAttempted::dispatch('login_blocked_inactive', $user->id, [
                'email' => $request->input('email'),
            ]);

            return $this->error('Your account has been disabled', 403);
        }

        AuditLoginAttempted::dispatch('login_success', $user->id);

        return $this->respondWithToken($token);
    }

    /**
     * Current user info.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing('roles:id,name');

        return $this->success([
            ...$user->only(['id', 'name', 'email']),
            'roles' => $user->roles->pluck('name'),
        ]);
    }

    /**
     * Logout.
     */
    public function logout(): JsonResponse
    {
        $this->auth->logout();

        return $this->success(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh token.
     */
    public function refresh(): JsonResponse
    {
        try {
            $user = $this->auth->user()?->fresh();
            if ($user && ! $user->is_active) {
                $this->auth->logout();

                return $this->error('Your account has been disabled', 403);
            }

            $token = $this->auth->refresh(true, true);

            // Re-check after refresh in case user() was null before (expired token in refresh window)
            $user = $this->auth->user()?->fresh();
            if ($user && ! $user->is_active) {
                $this->auth->logout();

                return $this->error('Your account has been disabled', 403);
            }

            return $this->respondWithToken($token);
        } catch (TokenBlacklistedException $e) {
            return $this->error('Token has been blacklisted', 401);
        } catch (JWTException $e) {
            return $this->error('Could not refresh token', 401);
        }
    }

    protected function respondWithToken(string $token): JsonResponse
    {
        return $this->success([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $this->auth->factory()->getTTL() * 60,
        ]);
    }
}
