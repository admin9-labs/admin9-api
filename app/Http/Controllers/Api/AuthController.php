<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AuthRequest;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Services\AuthService;
use App\Services\MenuService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenBlacklistedException;

/**
 * @scaffold — JWT login/logout/refresh/me.
 */
#[Group('Auth', weight: 0)]
class AuthController extends Controller
{
    public function __construct(
        #[Auth('api')] protected Guard $auth,
        private readonly AuthService $authService,
    ) {}

    /**
     * Login.
     */
    public function login(AuthRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        if (! $token = $this->auth->attempt($credentials)) {
            $this->authService->logLoginFailed($request->input('email'));

            return $this->error('Invalid credentials');
        }

        $user = $this->auth->user();
        if (! $user->is_active) {
            $this->auth->logout();

            $this->authService->logLoginBlockedInactive($user, $request->input('email'));

            return $this->error('Your account has been disabled');
        }

        $this->authService->logLoginSuccess($user);

        return $this->respondWithToken($token);
    }

    /**
     * Current user info.
     *
     * @response array{id: int, name: string, email: string, roles: string[]}
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->fresh();
        $user->loadMissing('roles:id,name');

        return $this->success([
            ...$user->only(['id', 'name', 'email', 'avatar']),
            'roles' => $user->roles->pluck('name'),
        ]);
    }

    /**
     * Update current user profile.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $changes = [];

        if (isset($data['name'])) {
            $changes['name'] = $data['name'];
        }

        if (isset($data['password'])) {
            $changes['password'] = $data['password'];
        }

        if ($changes) {
            $old = collect($changes)->keys()->mapWithKeys(fn ($key) => [$key => $key === 'password' ? '******' : $user->$key])->all();

            $user->update($changes);

            activity('user')
                ->performedOn($user)
                ->causedBy($user)
                ->event('profile_updated')
                ->withProperties([
                    'old' => $old,
                    'attributes' => collect($changes)->map(fn ($v, $k) => $k === 'password' ? '******' : $v)->all(),
                ])
                ->log('User profile updated');
        }

        return $this->success($user->only(['id', 'name', 'email']));
    }

    /**
     * Update current user avatar.
     */
    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:2048'],
        ]);

        $user = $request->user()->fresh();

        // Delete old avatar
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');

        $user->update(['avatar' => $path]);

        activity('user')
            ->performedOn($user)
            ->causedBy($user)
            ->event('avatar_updated')
            ->log('User avatar updated');

        return $this->success(['avatar' => $path]);
    }

    /**
     * Logout.
     */
    public function logout(): JsonResponse
    {
        $this->authService->logLogout($this->auth->user());

        $this->auth->logout();

        return $this->success();
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

                return $this->error('Your account has been disabled');
            }

            $token = $this->auth->refresh(true, true);

            // Re-check after refresh in case user() was null before (expired token in refresh window)
            $user = $this->auth->user()?->fresh();
            if ($user && ! $user->is_active) {
                $this->auth->logout();

                return $this->error('Your account has been disabled');
            }

            return $this->respondWithToken($token);
        } catch (TokenBlacklistedException $e) {
            return $this->error('Token has been blacklisted');
        } catch (JWTException $e) {
            return $this->error('Could not refresh token');
        }
    }

    /**
     * Current user menu tree.
     *
     * @response array<int, array{name: string, path: ?string, component: ?string, meta: array{locale: ?string, icon: ?string, requiresAuth: bool, hideInMenu: bool, order: int}, children: array<int, mixed>}>
     */
    public function menu(Request $request, MenuService $menuService): JsonResponse
    {
        return $this->success($menuService->getMenuTreeForUser($request->user()));
    }

    /**
     * @response array{access_token: string, token_type: string, expires_in: int}
     */
    protected function respondWithToken(string $token): JsonResponse
    {
        return $this->success([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $this->auth->factory()->getTTL() * 60,
        ]);
    }
}
