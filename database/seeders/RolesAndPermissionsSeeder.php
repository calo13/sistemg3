<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function () {
            foreach (PermissionName::cases() as $permission) {
                Permission::findOrCreate($permission->value, 'web');
            }

            // DatabaseSeeder may suppress model events; reload before assigning by name.
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            foreach (RoleName::cases() as $name) {
                Role::findOrCreate($name->value, 'web')->syncPermissions($name->permissions());
            }

            // Existing accounts retain their roles; unassigned accounts get read-only access.
            User::doesntHave('roles')->chunkById(100, function ($users) {
                foreach ($users as $user) {
                    $user->assignRole(RoleName::Observer->value);
                }
            });
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
