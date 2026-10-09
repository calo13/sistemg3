<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserRoleService
{
    public function changeRole(User $actor, User $target, RoleName $role): void
    {
        $this->assign($target, $role, $actor);
    }

    /** Trusted local console entry point for the initial administrator. */
    public function assignFromConsole(User $target, RoleName $role): void
    {
        $this->assign($target, $role);
    }

    private function assign(User $target, RoleName $role, ?User $actor = null): void
    {
        DB::transaction(function () use ($target, $role, $actor) {
            // Serialize changes so concurrent demotions cannot remove every administrator.
            Role::where('name', RoleName::Administrator->value)->where('guard_name', 'web')
                ->lockForUpdate()->firstOrFail();

            $currentTarget = User::findOrFail($target->getKey());
            if ($actor !== null) {
                // Reload permissions: a previous render must not authorize a revoked user.
                Gate::forUser(User::findOrFail($actor->getKey()))->authorize('assignRole', $currentTarget);
            }

            if ($role !== RoleName::Administrator
                && $currentTarget->hasRole(RoleName::Administrator->value)
                && User::role(RoleName::Administrator->value, 'web')->count() <= 1) {
                throw ValidationException::withMessages([
                    'role' => 'Debe quedar al menos un administrador en el sistema.',
                ]);
            }

            $currentTarget->syncRoles([$role->value]);
        }, 3);

        $target->unsetRelation('roles')->unsetRelation('permissions');
    }
}
