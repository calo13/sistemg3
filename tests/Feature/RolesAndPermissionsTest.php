<?php

namespace Tests\Feature;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Livewire\Admin\UserRoles;
use App\Models\User;
use App\Services\UserRoleService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public static function permissionMatrix(): array
    {
        return [
            'administrador' => ['administrador', [
                'users.manage', 'memory.configure', 'scenarios.create', 'simulations.execute',
                'memory.reset', 'history.view', 'processes.create', 'pages.request',
                'segmentation.execute', 'results.view', 'memory.view', 'tables.view', 'simulations.view',
            ]],
            'operador' => ['operador', [
                'processes.create', 'simulations.execute', 'pages.request', 'segmentation.execute',
                'results.view', 'memory.view', 'tables.view', 'simulations.view',
            ]],
            'observador' => ['observador', ['results.view', 'memory.view', 'tables.view', 'simulations.view']],
        ];
    }

    #[DataProvider('permissionMatrix')]
    public function test_roles_grant_only_the_expected_permissions(string $role, array $allowed): void
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        foreach (PermissionName::cases() as $permission) {
            $this->assertSame(in_array($permission->value, $allowed, true), $user->can($permission->value), $permission->value);
        }
    }

    public function test_seeding_preserves_accounts_and_roles_and_backfills_observers(): void
    {
        $this->assertDatabaseCount('users', 0);
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $unassigned = User::factory()->create();
        $originalPassword = $unassigned->password;

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('roles', 3);
        $this->assertDatabaseCount('permissions', 13);
        $this->assertDatabaseCount('role_has_permissions', 25);
        $this->assertTrue($admin->fresh()->hasRole('administrador'));
        $this->assertTrue($unassigned->fresh()->hasRole('observador'));
        $this->assertSame($originalPassword, $unassigned->fresh()->password);
        $this->assertSame(['web'], Role::pluck('guard_name')->unique()->values()->all());
        $this->assertSame(['web'], Permission::pluck('guard_name')->unique()->values()->all());
    }

    public function test_registration_cannot_inject_an_administrator_role_or_permissions(): void
    {
        $this->post('/register', [
            'name' => 'Observador de prueba', 'email' => 'observer@example.test',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
            'role' => 'administrador', 'roles' => ['administrador'],
            'permissions' => ['users.manage'],
        ])->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'observer@example.test')->firstOrFail();
        $this->assertSame(['observador'], $user->getRoleNames()->all());
        $this->assertFalse($user->can('users.manage'));
        $this->assertCount(0, $user->getDirectPermissions());
    }

    public function test_guests_are_redirected_and_only_administrators_can_open_role_management(): void
    {
        $this->get('/administracion/usuarios')->assertRedirect(route('login'));

        foreach (['observador', 'operador'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $this->actingAs($user)->get('/administracion/usuarios')->assertForbidden()->assertSee('Acceso restringido');
            $this->get('/dashboard')->assertOk()->assertDontSee('Usuarios y roles');
        }

        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $this->actingAs($admin)->get('/administracion/usuarios')->assertOk()->assertSee('Roles de usuarios');
        $this->get('/dashboard')->assertOk()->assertSee('Usuarios y roles');
    }

    public function test_unassigned_users_have_no_management_permission(): void
    {
        $this->actingAs(User::factory()->create())->get('/administracion/usuarios')->assertForbidden();
    }

    public function test_livewire_cannot_mount_for_an_operator_or_observer(): void
    {
        foreach (['operador', 'observador'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            Livewire::actingAs($user)->test(UserRoles::class)->assertForbidden();
        }
    }

    public function test_administrator_can_change_another_users_role_and_invalid_roles_are_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $target = User::factory()->create();
        $target->assignRole('observador');

        Livewire::actingAs($admin)->test(UserRoles::class)
            ->call('updateRole', $target->id, 'operador')->assertHasNoErrors()
            ->call('updateRole', $target->id, 'superadmin')->assertHasErrors(['role']);

        $this->assertSame(['operador'], $target->fresh()->getRoleNames()->all());
        $this->assertTrue($target->fresh()->can('pages.request'));
        $this->assertFalse($target->fresh()->can('memory.configure'));
    }

    public function test_administrator_cannot_change_their_own_role_even_with_a_forged_user_id(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        Livewire::actingAs($admin)->test(UserRoles::class)
            ->call('updateRole', $admin->id, 'observador')->assertForbidden();

        $this->assertTrue($admin->fresh()->hasRole('administrador'));
    }

    public function test_missing_target_is_rejected_without_creating_a_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        try {
            Livewire::actingAs($admin)->test(UserRoles::class)
                ->call('updateRole', $admin->id + 1000, 'administrador');
            $this->fail('Un usuario inexistente debe rechazarse.');
        } catch (ModelNotFoundException) {
            $this->assertDatabaseCount('users', 1);
        }
    }

    public function test_service_rechecks_a_revoked_administrators_permissions(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $this->assertTrue($admin->can('users.manage'));
        $target = User::factory()->create();
        $target->assignRole('observador');
        $admin->fresh()->syncRoles(['observador']);

        try {
            app(UserRoleService::class)->changeRole($admin, $target, RoleName::Operator);
            $this->fail('Un administrador revocado no debe modificar roles.');
        } catch (AuthorizationException) {
            $this->assertSame(['observador'], $target->fresh()->getRoleNames()->all());
        }
    }

    public function test_livewire_rejects_a_change_after_the_administrator_role_is_revoked(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $target = User::factory()->create();
        $target->assignRole('observador');
        $component = Livewire::actingAs($admin)->test(UserRoles::class);
        $admin->fresh()->syncRoles(['observador']);

        $component->call('updateRole', $target->id, 'administrador')->assertForbidden();
        $this->assertTrue($target->fresh()->hasRole('observador'));
    }

    public function test_last_administrator_is_preserved_and_demotions_work_when_another_remains(): void
    {
        $first = User::factory()->create();
        $first->assignRole('administrador');

        try {
            app(UserRoleService::class)->assignFromConsole($first, RoleName::Observer);
            $this->fail('Debe conservarse un administrador.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('role', $exception->errors());
            $this->assertTrue($first->fresh()->hasRole('administrador'));
        }

        $second = User::factory()->create();
        $second->assignRole('administrador');
        app(UserRoleService::class)->changeRole($second, $first, RoleName::Observer);
        $this->assertTrue($first->fresh()->hasRole('observador'));
        $this->actingAs($first->fresh())->get('/administracion/usuarios')->assertForbidden();
    }

    public function test_console_assigns_the_initial_administrator_to_an_existing_account_only(): void
    {
        $user = User::factory()->create();
        $user->assignRole('observador');
        $password = $user->password;

        $this->artisan('memorylab:user-role', ['email' => $user->email])->assertSuccessful();
        $this->assertTrue($user->fresh()->hasRole('administrador'));
        $this->assertSame($password, $user->fresh()->password);
        $this->artisan('memorylab:user-role', ['email' => $user->email, 'role' => 'observador'])->assertFailed();
        $this->artisan('memorylab:user-role', ['email' => $user->email, 'role' => 'invalido'])->assertFailed();
        $this->artisan('memorylab:user-role', ['email' => 'missing@example.test'])->assertFailed();
        $this->assertDatabaseCount('users', 1);
    }
}
