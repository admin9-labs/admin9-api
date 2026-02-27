<?php

namespace App\Services;

use App\Enums\Role as RoleEnum;
use App\Exceptions\BusinessException;
use App\Models\User;
use App\Notifications\PasswordResetCompletedNotification;
use App\Notifications\PasswordResetNotification;
use App\Notifications\UserProfileUpdatedNotification;
use App\Notifications\UserRolesChangedNotification;
use App\Notifications\UserStatusChangedNotification;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Role as SpatieRole;
use Throwable;

/**
 * @scaffold — User business logic (role sync, password reset, status toggle).
 */
class UserService
{
    /**
     * @throws Throwable
     */
    public function createUser(array $data, RoleEnum $role = RoleEnum::User): User
    {
        return DB::transaction(function () use ($data, $role) {
            $user = User::create($data);
            $user->assignRole($role->value);

            return $user;
        });
    }

    /**
     * @throws Throwable
     */
    public function updateUser(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $isSuperAdmin = $user->hasRole(RoleEnum::SuperAdmin->value);
            $isSelf = auth()->id() === $user->id;
            if ($isSuperAdmin && ! $isSelf) {
                throw new BusinessException('Cannot modify super-admin user');
            }

            $changes = collect($data)->only(['name', 'email'])->toArray();

            $user->update($changes);

            if ($changes && ! $isSelf) {
                $user->notify(new UserProfileUpdatedNotification($changes));
            }

            $user->load('roles:id,name');

            return $user;
        });
    }

    /**
     * @throws BusinessException
     */
    public function syncRoles(User $user, array $roleIds): Collection
    {
        if ($user->hasRole(RoleEnum::SuperAdmin->value)) {
            throw new BusinessException('Cannot modify super-admin user roles');
        }

        $roleIds = array_values(array_unique($roleIds));

        $roles = SpatieRole::whereIn('id', $roleIds)->where('guard_name', 'api')->get();

        if ($roles->count() !== count($roleIds)) {
            throw new BusinessException('Invalid role IDs');
        }

        if ($roles->contains('name', RoleEnum::SuperAdmin->value)) {
            throw new BusinessException('Cannot assign super-admin role');
        }

        $oldRoles = $user->roles()->pluck('name')->all();

        $user->syncRoles($roles);

        activity('user')
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->event('roles_synced')
            ->withProperties([
                'old' => ['roles' => $oldRoles],
                'attributes' => ['roles' => $roles->pluck('name')->all()],
            ])
            ->log('User roles synced');

        $newRoles = $roles->pluck('name')->all();
        $user->notify(new UserRolesChangedNotification($oldRoles, $newRoles));

        return $roles;
    }

    /**
     * @throws BusinessException
     * @throws Throwable
     */
    public function toggleStatus(User $user, bool $isActive, int $operatorId): User
    {
        if ($user->id === $operatorId) {
            throw new BusinessException('Cannot disable your own account');
        }

        if (! $isActive && $user->hasRole(RoleEnum::SuperAdmin->value)) {
            throw new BusinessException('Cannot disable super-admin users');
        }

        if ($user->is_active === $isActive) {
            return $user;
        }

        return DB::transaction(function () use ($user, $isActive) {
            // forceFill: is_active is intentionally excluded from $fillable to prevent mass-assignment
            $user->disableLogging();
            try {
                $user->forceFill(['is_active' => $isActive])->save();
            } finally {
                $user->enableLogging();
            }

            DB::afterCommit(fn () => activity('user')
                ->performedOn($user)
                ->causedBy(auth()->user())
                ->event('status_toggled')
                ->withProperties([
                    'old' => ['is_active' => ! $isActive],
                    'attributes' => ['is_active' => $isActive],
                ])
                ->log($isActive ? 'User account enabled' : 'User account disabled'));

            DB::afterCommit(fn () => $user->notify(new UserStatusChangedNotification($isActive)));

            $user->load('roles:id,name');

            return $user;
        });
    }

    /**
     * @throws BusinessException
     */
    public function resetPassword(User $user): void
    {
        $isSuperAdmin = $user->hasRole(RoleEnum::SuperAdmin->value);
        $isSelf = auth()->id() === $user->id;

        if ($isSuperAdmin && ! $isSelf) {
            throw new BusinessException('Cannot reset super-admin password via API');
        }

        $token = Password::broker()->createToken($user);

        $user->notify(new PasswordResetNotification($token, $user->email));

        activity('user')
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->event('password_reset_requested')
            ->log('Password reset requested');
    }

    /**
     * @throws BusinessException
     */
    public function sendResetLink(string $email): void
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            throw new BusinessException('User not found');
        }

        if (! $user->is_active) {
            throw new BusinessException('User account is disabled');
        }

        $token = Password::broker()->createToken($user);

        $user->notify(new PasswordResetNotification($token, $user->email));

        activity('user')
            ->performedOn($user)
            ->causedBy($user)
            ->event('password_reset_requested')
            ->log('Password reset requested by user');
    }

    /**
     * @throws BusinessException
     */
    public function completePasswordReset(array $data): void
    {
        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            throw new BusinessException('User not found');
        }

        if (! $user->is_active) {
            throw new BusinessException('User account is disabled');
        }

        $status = Password::broker()->reset(
            [
                'email' => $data['email'],
                'password' => $data['password'],
                'password_confirmation' => $data['password_confirmation'],
                'token' => $data['token'],
            ],
            function (User $user, string $password) {
                $user->update(['password' => $password]);

                $user->notify(new PasswordResetCompletedNotification);

                activity('user')
                    ->performedOn($user)
                    ->causedBy($user)
                    ->event('password_reset')
                    ->log('User password reset via token');
            }
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            $message = match ($status) {
                PasswordBroker::INVALID_TOKEN => 'Invalid or expired reset token',
                PasswordBroker::INVALID_USER => 'User not found',
                default => 'Password reset failed',
            };

            throw new BusinessException($message);
        }
    }
}
