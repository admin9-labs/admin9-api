<?php

namespace App\Services;

use App\Enums\Role as RoleEnum;
use App\Events\AuditUserChanged;
use App\Exceptions\BusinessException;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
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
            $original = collect($user->only(['name', 'email']));

            $user->update($changes);

            $dirty = $original->diffAssoc(collect($changes))->keys()->all();
            if (! empty($dirty)) {
                DB::afterCommit(fn () => AuditUserChanged::dispatch('info_updated', $user->id, [
                    'changed_fields' => $dirty,
                ]));
            }

            if ($roleIds !== null) {
                $roles = $this->syncRoles($user, $roleIds);

                DB::afterCommit(fn () => AuditUserChanged::dispatch('roles_synced', $user->id, [
                    'roles' => $roles->pluck('name')->all(),
                ]));
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

        $roles = Role::whereIn('id', $roleIds)->where('guard_name', 'api')->get();

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

        return DB::transaction(function () use ($user, $isActive, $operatorId) {
            // forceFill: is_active is intentionally excluded from $fillable to prevent mass-assignment
            $user->forceFill(['is_active' => $isActive])->save();

            DB::afterCommit(fn () => AuditUserChanged::dispatch('status_changed', $user->id, [
                'is_active' => $isActive,
                'operator_id' => $operatorId,
            ]));

            $user->load('roles:id,name');

            return $user;
        });
    }

    /**
     * @throws BusinessException
     * @throws Throwable
     *
     * @internal Return value is for CLI commands only. Controllers must not expose the password.
     */
    public function resetPassword(User $user): string
    {
        if ($user->hasRole(RoleEnum::SuperAdmin->value)) {
            throw new BusinessException('Cannot reset super-admin password via API', 403);
        }

        return DB::transaction(function () use ($user) {
            $password = Str::password(16);
            $user->update(['password' => $password]);

            DB::afterCommit(fn () => AuditUserChanged::dispatch('password_reset', $user->id));

            return $password;
        });
    }
}
