<?php

namespace App\Services;

use App\Enums\Role as RoleEnum;
use App\Exceptions\BusinessException;
use App\Models\User;
use App\Notifications\PasswordResetNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
    public function updateUser(User $user, array $data, ?array $roleIds = null): User
    {
        return DB::transaction(function () use ($user, $data, $roleIds) {
            if ($user->hasRole(RoleEnum::SuperAdmin->value)) {
                throw new BusinessException('Cannot modify super-admin user', 403);
            }

            $changes = collect($data)->only(['name', 'email'])->toArray();

            $user->update($changes);

            if ($roleIds !== null) {
                $oldRoles = $user->roles()->pluck('name')->all();
                $oldRoleIds = $user->roles()->pluck('id')->sort()->values()->all();
                $newRoleIds = collect($roleIds)->sort()->values()->all();

                if ($oldRoleIds !== $newRoleIds) {
                    $roles = $this->syncRoles($user, $roleIds);

                    DB::afterCommit(fn () => activity('user')
                        ->performedOn($user)
                        ->causedBy(auth()->user())
                        ->event('roles_synced')
                        ->withProperties([
                            'old' => ['roles' => $oldRoles],
                            'attributes' => ['roles' => $roles->pluck('name')->all()],
                        ])
                        ->log('User roles synced'));
                }
            }

            $user->load('roles:id,name');

            return $user;
        });
    }

    /**
     * @throws BusinessException
     */
    private function syncRoles(User $user, array $roleIds): Collection
    {
        if ($user->hasRole(RoleEnum::SuperAdmin->value)) {
            throw new BusinessException('Cannot modify super-admin user roles', 403);
        }

        $roleIds = array_values(array_unique($roleIds));

        $roles = SpatieRole::whereIn('id', $roleIds)->where('guard_name', 'api')->get();

        if ($roles->count() !== count($roleIds)) {
            throw new BusinessException('Invalid role IDs', 422);
        }

        if ($roles->contains('name', RoleEnum::SuperAdmin->value)) {
            throw new BusinessException('Cannot assign super-admin role', 403);
        }

        $user->syncRoles($roles);

        return $roles;
    }

    /**
     * @throws BusinessException
     * @throws Throwable
     */
    public function toggleStatus(User $user, bool $isActive, int $operatorId): User
    {
        if ($user->id === $operatorId) {
            throw new BusinessException('Cannot disable your own account', 403);
        }

        if (! $isActive && $user->hasRole(RoleEnum::SuperAdmin->value)) {
            throw new BusinessException('Cannot disable super-admin users', 403);
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

            $user->load('roles:id,name');

            return $user;
        });
    }

    /**
     * @throws BusinessException
     * @throws Throwable
     */
    public function resetPassword(User $user): void
    {
        if ($user->hasRole(RoleEnum::SuperAdmin->value)) {
            throw new BusinessException('Cannot reset super-admin password via API', 403);
        }

        DB::transaction(function () use ($user) {
            $password = Str::password(16);
            $user->update(['password' => $password]);

            DB::afterCommit(function () use ($user, $password) {
                activity('user')
                    ->performedOn($user)
                    ->causedBy(auth()->user())
                    ->event('password_reset')
                    ->log('User password reset');

                $user->notify(new PasswordResetNotification($password));
            });
        });
    }
}
