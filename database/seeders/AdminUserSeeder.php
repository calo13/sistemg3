<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        DB::transaction(function (): void {
            // Only a new practice account receives this initial password.
            $admin = User::firstOrCreate(
                ['email' => 'admin@admin.com'],
                ['name' => 'Administrador', 'password' => Hash::make('123')],
            );

            $admin->syncRoles([RoleName::Administrator->value]);
        });
    }
}
