<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\ScenarioStatus;
use App\Enums\SimulationMode;
use App\Models\Scenario;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_seeder_creates_an_administrator_who_can_login_without_demo_data(): void
    {
        $this->seed(AdminUserSeeder::class);

        $admin = User::where('email', 'admin@admin.com')->sole();
        $this->assertSame('Administrador', $admin->name);
        $this->assertNotSame('123', $admin->password);
        $this->assertTrue(Hash::check('123', $admin->password));
        $this->assertSame(['administrador'], $admin->getRoleNames()->all());
        $this->assertTrue($admin->can('users.manage'));
        $this->assertTrue($admin->can('memory.configure'));

        $originalHash = $admin->password;
        $this->seed(AdminUserSeeder::class);
        $this->assertDatabaseCount('users', 1);
        $this->assertSame($originalHash, $admin->fresh()->password);
        foreach (['scenarios', 'memory_configurations', 'processes', 'memory_frames', 'pages', 'segments', 'simulation_events'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }

        $this->post('/login', ['email' => 'admin@admin.com', 'password' => '123'])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_existing_account_password_profile_and_manual_scenario_are_preserved(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@admin.com',
            'name' => 'Mi administrador',
            'password' => Hash::make('clave-elegida'),
        ]);
        $admin->assignRole(RoleName::Observer->value);
        $operator = User::factory()->create();
        $operator->assignRole(RoleName::Operator->value);
        $scenario = Scenario::create([
            'name' => 'Aprendizaje manual',
            'mode' => SimulationMode::Paging,
            'status' => ScenarioStatus::Ready,
            'created_by' => $operator->id,
        ]);
        $originalAccount = $admin->fresh()->getRawOriginal();
        $originalOperator = $operator->fresh()->getRawOriginal();
        $originalScenario = $scenario->fresh()->getRawOriginal();

        $this->seed(AdminUserSeeder::class);
        $this->seed(AdminUserSeeder::class);

        $this->assertDatabaseCount('users', 2);
        $this->assertSame($originalAccount, $admin->fresh()->getRawOriginal());
        $this->assertTrue(Hash::check('clave-elegida', $admin->fresh()->password));
        $this->assertSame(['administrador'], $admin->fresh()->getRoleNames()->all());
        $this->assertSame($originalOperator, $operator->fresh()->getRawOriginal());
        $this->assertSame(['operador'], $operator->fresh()->getRoleNames()->all());
        $this->assertDatabaseCount('scenarios', 1);
        $this->assertSame($originalScenario, $scenario->fresh()->getRawOriginal());
        $this->assertDatabaseCount('simulation_events', 0);
    }

    public function test_default_seeder_does_not_create_the_practice_account_automatically(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('scenarios', 0);
    }
}
