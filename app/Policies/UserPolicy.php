<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ManageUsers->value);
    }

    public function assignRole(User $actor, User $target): bool
    {
        return $actor->can(PermissionName::ManageUsers->value) && ! $actor->is($target);
    }
}
