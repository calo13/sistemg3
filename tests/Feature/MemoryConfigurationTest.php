<?php

namespace Tests\Feature;

use App\Enums\ProcessStatus;
use App\Enums\ScenarioStatus;
use App\Enums\SimulationEventType;
use App\Enums\SimulationMode;
use App\Livewire\DashboardStats;
use App\Livewire\MemoryConfiguration as MemoryConfigurationComponent;
use App\Models\Page;
use App\Models\Process;
use App\Models\Scenario;
use App\Models\Segment;
use App\Models\SimulationEvent;
use App\Models\User;
use App\Services\ActiveScenarioService;
use App\Services\MemoryConfigurationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class MemoryConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_configuration_creates_only_the_simulated_memory_and_records_its_author(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->configure($admin, ['name' => '  Primera memoria  ']);

        $this->assertSame('Primera memoria', $scenario->name);
        $this->assertSame($admin->id, $scenario->created_by);
        $this->assertSame(SimulationMode::Paging, $scenario->mode);
        $this->assertSame(ScenarioStatus::Ready, $scenario->status);
        $this->assertFalse($scenario->is_demo);
        $this->assertSame(16384, $scenario->configuration->ram_size_bytes);
        $this->assertSame(1024, $scenario->configuration->page_size_bytes);
        $this->assertSame(65536, $scenario->configuration->secondary_storage_bytes);
        $this->assertSame(16, $scenario->configuration->frame_count);
        $this->assertSame(range(0, 15), $scenario->frames()->orderBy('frame_number')->pluck('frame_number')->all());
        $this->assertSame([
            SimulationEventType::ScenarioCreated,
            SimulationEventType::MemoryConfigured,
        ], $scenario->events()->orderBy('id')->get()->pluck('type')->all());
        $this->assertSame([$admin->id], $scenario->events()->pluck('user_id')->unique()->values()->all());
        $configured = $scenario->events()->where('type', SimulationEventType::MemoryConfigured)->sole();
        $this->assertEquals([
            'before' => null,
            'after' => [
                'ram_size_bytes' => 16384, 'page_size_bytes' => 1024,
                'secondary_storage_bytes' => 65536, 'frame_count' => 16,
            ],
        ], $configured->metadata);
        $this->assertDatabaseCount('scenarios', 1);
        $this->assertDatabaseCount('memory_configurations', 1);
        $this->assertDatabaseCount('memory_frames', 16);
        $this->assertDatabaseCount('processes', 0);
        $this->assertDatabaseCount('pages', 0);
        $this->assertDatabaseCount('segments', 0);
    }

    public static function invalidConfigurations(): array
    {
        return [
            'missing scenario name' => [['name' => null], 'name'],
            'blank scenario name' => [['name' => '   '], 'name'],
            'scenario name too long' => [['name' => str_repeat('a', 101)], 'name'],
            'zero RAM' => [['ram_kb' => 0], 'ram_kb'],
            'negative RAM' => [['ram_kb' => -1], 'ram_kb'],
            'fractional RAM' => [['ram_kb' => 1.5], 'ram_kb'],
            'non numeric RAM' => [['ram_kb' => 'invalid'], 'ram_kb'],
            'RAM exceeds limit' => [['ram_kb' => 65537], 'ram_kb'],
            'zero page size' => [['page_kb' => 0], 'page_kb'],
            'fractional page size' => [['page_kb' => '1.5'], 'page_kb'],
            'page exceeds RAM' => [['page_kb' => 32], 'page_kb'],
            'page exceeds size limit' => [['page_kb' => 65537], 'page_kb'],
            'RAM is not a multiple of page size' => [['ram_kb' => 15, 'page_kb' => 2], 'ram_kb'],
            'negative secondary storage' => [['secondary_kb' => -1], 'secondary_kb'],
            'fractional secondary storage' => [['secondary_kb' => 0.5], 'secondary_kb'],
            'secondary storage exceeds limit' => [['secondary_kb' => 65537], 'secondary_kb'],
            'too many frames' => [['ram_kb' => 1025, 'page_kb' => 1], 'ram_kb'],
        ];
    }

    #[DataProvider('invalidConfigurations')]
    public function test_invalid_configuration_does_not_create_partial_records(array $input, string $error): void
    {
        $admin = $this->user('administrador');

        $this->assertValidationFailure(fn () => $this->configure($admin, $input), $error);

        foreach (['scenarios', 'memory_configurations', 'memory_frames', 'simulation_events'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_integer_form_values_and_zero_secondary_storage_are_supported(): void
    {
        $scenario = $this->configure($this->user('administrador'), [
            'ram_kb' => '16', 'page_kb' => '2', 'secondary_kb' => '0',
        ]);

        $this->assertSame(8, $scenario->configuration->frame_count);
        $this->assertSame(0, $scenario->configuration->secondary_storage_bytes);
        $this->assertCount(8, $scenario->frames);
    }

    public function test_a_configuration_at_the_frame_limit_is_accepted(): void
    {
        $scenario = $this->configure($this->user('administrador'), [
            'ram_kb' => 1024, 'page_kb' => 1,
        ]);

        $this->assertSame(1024, $scenario->configuration->frame_count);
        $this->assertSame(1024, $scenario->frames()->count());
        $this->assertSame(1023, $scenario->frames()->max('frame_number'));
    }

    public function test_invalid_reconfiguration_preserves_the_existing_configuration_and_frames(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->configure($admin);
        $frameIds = $scenario->frames()->orderBy('frame_number')->pluck('id')->all();

        $this->assertValidationFailure(fn () => $this->configure($admin, ['ram_kb' => 0], $scenario->id), 'ram_kb');

        $this->assertOriginalMemory($scenario, $frameIds);
    }

    public function test_event_failure_rolls_back_the_new_scenario_configuration_and_frames(): void
    {
        $admin = $this->user('administrador');
        $dispatcher = SimulationEvent::getEventDispatcher();
        SimulationEvent::setEventDispatcher(clone $dispatcher);
        SimulationEvent::creating(function (SimulationEvent $event): void {
            if ($event->type === SimulationEventType::MemoryConfigured) {
                throw new RuntimeException('Falló el registro del evento de prueba.');
            }
        });

        try {
            try {
                $this->configure($admin);
                $this->fail('Una configuración incompleta debe revertirse.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Falló el registro del evento de prueba.', $exception->getMessage());
            }
        } finally {
            SimulationEvent::setEventDispatcher($dispatcher);
        }

        foreach (['scenarios', 'memory_configurations', 'memory_frames', 'simulation_events'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_reconfiguration_rebuilds_free_frames_and_keeps_configuration_and_event_history(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->configure($admin);
        $configurationId = $scenario->configuration->id;
        $eventIds = $scenario->events()->orderBy('id')->pluck('id')->all();

        $updated = $this->configure($admin, [
            'ram_kb' => 8, 'page_kb' => 2, 'secondary_kb' => 0,
        ], $scenario->id);

        $this->assertSame($scenario->id, $updated->id);
        $this->assertSame($configurationId, $updated->configuration->id);
        $this->assertSame(8192, $updated->configuration->ram_size_bytes);
        $this->assertSame(2048, $updated->configuration->page_size_bytes);
        $this->assertSame(0, $updated->configuration->secondary_storage_bytes);
        $this->assertSame([0, 1, 2, 3], $updated->frames()->orderBy('frame_number')->pluck('frame_number')->all());
        $this->assertSame($eventIds, $updated->events()->orderBy('id')->limit(2)->pluck('id')->all());
        $this->assertSame(2, $updated->events()->where('type', SimulationEventType::MemoryConfigured)->count());
        $this->assertDatabaseCount('scenarios', 1);
        $this->assertDatabaseCount('memory_configurations', 1);
        $this->assertDatabaseCount('memory_frames', 4);
    }

    public function test_identical_save_is_a_noop_even_when_the_scenario_has_started(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->configure($admin);
        $scenario->update(['status' => ScenarioStatus::Running]);
        $this->process($scenario);
        $frameIds = $scenario->frames()->orderBy('frame_number')->pluck('id')->all();
        $configurationId = $scenario->configuration->id;

        $this->configure($admin, [], $scenario->id);

        $this->assertSame($configurationId, $scenario->fresh()->configuration->id);
        $this->assertSame($frameIds, $scenario->frames()->orderBy('frame_number')->pluck('id')->all());
        $this->assertSame(ScenarioStatus::Running, $scenario->fresh()->status);
        $this->assertDatabaseCount('simulation_events', 2);
        $this->assertDatabaseCount('processes', 1);
    }

    public static function blockedScenarioStates(): array
    {
        return [
            'running scenario' => [ScenarioStatus::Running],
            'completed scenario' => [ScenarioStatus::Completed],
        ];
    }

    #[DataProvider('blockedScenarioStates')]
    public function test_reconfiguration_cannot_change_a_running_or_completed_scenario(ScenarioStatus $status): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->configure($admin);
        $scenario->update(['status' => $status]);
        $frameIds = $scenario->frames()->orderBy('frame_number')->pluck('id')->all();

        $this->assertValidationFailure(fn () => $this->configure($admin, ['ram_kb' => 8], $scenario->id), 'scenario_id');

        $this->assertOriginalMemory($scenario, $frameIds);
        $this->assertSame($status, $scenario->fresh()->status);
    }

    public static function incompatibleModes(): array
    {
        return [
            'segmentation' => [SimulationMode::Segmentation],
            'contiguous allocation' => [SimulationMode::Contiguous],
        ];
    }

    #[DataProvider('incompatibleModes')]
    public function test_paging_configuration_cannot_overwrite_other_memory_modes(SimulationMode $mode): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->configure($admin);
        $scenario->update(['mode' => $mode]);
        $frameIds = $scenario->frames()->orderBy('frame_number')->pluck('id')->all();

        $this->assertValidationFailure(fn () => $this->configure($admin, ['ram_kb' => 8], $scenario->id), 'scenario_id');

        $this->assertOriginalMemory($scenario, $frameIds);
        $this->assertSame($mode, $scenario->fresh()->mode);
    }

    public static function processStates(): array
    {
        return [
            'ready process' => [ProcessStatus::Ready],
            'terminated process' => [ProcessStatus::Terminated],
        ];
    }

    #[DataProvider('processStates')]
    public function test_any_existing_process_blocks_a_destructive_reconfiguration(ProcessStatus $status): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->configure($admin);
        $process = $this->process($scenario, ['status' => $status]);
        $frameIds = $scenario->frames()->orderBy('frame_number')->pluck('id')->all();

        $this->assertValidationFailure(fn () => $this->configure($admin, ['ram_kb' => 8], $scenario->id), 'scenario_id');

        $this->assertOriginalMemory($scenario, $frameIds);
        $this->assertTrue($process->fresh()->scenario->is($scenario));
    }

    public function test_pages_and_segments_are_kept_when_reconfiguration_is_rejected(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->configure($admin);
        $process = $this->process($scenario);
        $page = Page::create([
            'scenario_id' => $scenario->id, 'process_id' => $process->id,
            'page_number' => 0, 'frame_id' => $scenario->frames()->first()->id,
        ]);
        $segment = Segment::create([
            'scenario_id' => $scenario->id, 'process_id' => $process->id,
            'segment_number' => 0, 'name' => 'Código', 'base' => 0, 'size_bytes' => 1024,
        ]);
        $frameIds = $scenario->frames()->orderBy('frame_number')->pluck('id')->all();

        $this->assertValidationFailure(fn () => $this->configure($admin, ['page_kb' => 2], $scenario->id), 'scenario_id');

        $this->assertOriginalMemory($scenario, $frameIds);
        $this->assertTrue($page->fresh()->present);
        $this->assertTrue($segment->fresh()->process->is($process));
    }

    public static function readOnlyRoles(): array
    {
        return ['operator' => ['operador'], 'observer' => ['observador']];
    }

    #[DataProvider('readOnlyRoles')]
    public function test_read_only_roles_cannot_configure_memory_through_the_service(string $role): void
    {
        try {
            $this->configure($this->user($role));
            $this->fail('La configuración requiere el permiso de administrador.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('scenarios', 0);
            $this->assertDatabaseCount('memory_frames', 0);
        }
    }

    public function test_permission_revoked_after_loading_the_actor_cannot_authorize_a_save(): void
    {
        $admin = $this->user('administrador');
        $this->assertTrue($admin->can('memory.configure'));
        $admin->fresh()->syncRoles(['observador']);

        try {
            $this->configure($admin);
            $this->fail('El servicio debe comprobar los permisos actuales.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('scenarios', 0);
            $this->assertDatabaseCount('simulation_events', 0);
        }
    }

    public function test_creating_a_scenario_requires_the_creation_permission_in_addition_to_configuration(): void
    {
        $user = $this->user('observador');
        $user->givePermissionTo('memory.configure');

        try {
            $this->configure($user);
            $this->fail('Crear un escenario requiere scenarios.create.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('scenarios', 0);
        }
    }

    public function test_an_existing_scenario_can_be_configured_without_permission_to_create_another(): void
    {
        $scenario = $this->configure($this->user('administrador'));
        $user = $this->user('observador');
        $user->givePermissionTo('memory.configure');

        $updated = $this->configure($user, ['ram_kb' => 8], $scenario->id);

        $this->assertSame(8192, $updated->configuration->ram_size_bytes);
        $this->assertSame($user->id, $updated->events()->latest('id')->first()->user_id);
        $this->assertDatabaseCount('scenarios', 1);
    }

    public function test_a_missing_scenario_id_cannot_create_an_unintended_scenario(): void
    {
        $admin = $this->user('administrador');

        try {
            $this->configure($admin, [], 999999);
            $this->fail('Un escenario inexistente debe rechazarse.');
        } catch (ModelNotFoundException) {
            $this->assertDatabaseCount('scenarios', 0);
            $this->assertDatabaseCount('simulation_events', 0);
        }
    }

    public function test_configuration_page_requires_authentication_and_the_memory_view_permission(): void
    {
        $this->get('/memoria/configuracion')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/memoria/configuracion')->assertForbidden();

        foreach (['administrador', 'operador', 'observador'] as $role) {
            $this->actingAs($this->user($role))->get('/memoria/configuracion')->assertOk();
        }
    }

    #[DataProvider('readOnlyRoles')]
    public function test_forged_livewire_save_is_forbidden_for_read_only_roles(string $role): void
    {
        Livewire::actingAs($this->user($role))->test(MemoryConfigurationComponent::class)
            ->assertViewHas('canConfigure', false)->assertViewHas('canCreate', false)
            ->set('name', 'Configuración no autorizada')
            ->set('ramKb', 8)
            ->call('save')->assertForbidden();

        $this->assertDatabaseCount('scenarios', 0);
        $this->assertDatabaseCount('memory_frames', 0);
    }

    public function test_livewire_rechecks_permissions_when_an_administrator_has_been_revoked(): void
    {
        $admin = $this->user('administrador');
        $component = Livewire::actingAs($admin)->test(MemoryConfigurationComponent::class);
        $admin->fresh()->syncRoles(['observador']);

        $component->call('save')->assertForbidden();

        $this->assertDatabaseCount('scenarios', 0);
        $this->assertDatabaseCount('simulation_events', 0);
    }

    public function test_livewire_maps_geometry_errors_to_the_form_fields(): void
    {
        Livewire::actingAs($this->user('administrador'))->test(MemoryConfigurationComponent::class)
            ->set('ramKb', 15)->set('pageKb', 2)
            ->call('save')->assertHasErrors(['ramKb']);

        $this->assertDatabaseCount('scenarios', 0);
    }

    public function test_livewire_save_activates_the_saved_scenario_in_the_current_session(): void
    {
        $admin = $this->user('administrador');

        Livewire::actingAs($admin)->test(MemoryConfigurationComponent::class)
            ->set('name', 'Memoria del panel')->set('ramKb', 8)->set('pageKb', 2)
            ->call('save')->assertHasNoErrors();

        $scenario = Scenario::sole();
        $this->assertSame($scenario->id, session('memorylab.active_scenario_id'));
        $this->assertSame(4, $scenario->configuration->frame_count);
        $this->assertTrue(app(ActiveScenarioService::class)->current()->is($scenario));
    }

    public function test_selecting_an_existing_scenario_loads_its_memory_without_saving_it(): void
    {
        $scenario = $this->configure($this->user('administrador'), [
            'ram_kb' => 8, 'page_kb' => 2, 'secondary_kb' => 0,
        ]);
        $observer = $this->user('observador');

        Livewire::actingAs($observer)->test(MemoryConfigurationComponent::class)
            ->set('scenarioId', (string) $scenario->id)
            ->assertSet('ramKb', 8)->assertSet('pageKb', 2)->assertSet('secondaryKb', 0);

        $this->assertSame($scenario->id, session('memorylab.active_scenario_id'));
        $this->assertDatabaseCount('simulation_events', 2);
    }

    public static function invalidScenarioIds(): array
    {
        return [
            'nonexistent scenario' => ['999999'],
            'fractional scenario ID' => ['1.5'],
        ];
    }

    #[DataProvider('invalidScenarioIds')]
    public function test_invalid_livewire_scenario_selection_keeps_the_session_and_memory(string $invalidId): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->configure($admin);
        $frameIds = $scenario->frames()->orderBy('frame_number')->pluck('id')->all();
        $this->actingAs($admin);
        app(ActiveScenarioService::class)->select($admin, $scenario->id);

        Livewire::test(MemoryConfigurationComponent::class)
            ->set('scenarioId', $invalidId)->assertHasErrors(['scenarioId'])
            ->set('ramKb', 8)
            ->call('save')->assertHasErrors(['scenarioId']);

        $this->assertSame($scenario->id, session('memorylab.active_scenario_id'));
        $this->assertOriginalMemory($scenario, $frameIds);
        $this->assertDatabaseCount('scenarios', 1);
    }

    public function test_active_scenario_is_explicit_and_invalid_selection_preserves_the_previous_context(): void
    {
        $admin = $this->user('administrador');
        $first = $this->configure($admin, ['name' => 'Memoria A']);
        $second = $this->configure($admin, ['name' => 'Memoria B', 'ram_kb' => 8]);
        $observer = $this->user('observador');
        $this->actingAs($observer);
        $service = app(ActiveScenarioService::class);

        $this->assertNull($service->current());
        $service->select($observer, $first->id);
        $this->assertTrue($service->current()->is($first));
        $this->assertValidationFailure(fn () => $service->select($observer, $second->id + 1000), 'scenarioId');
        $this->assertTrue($service->current()->is($first));
        $service->select($observer, $second->id);
        $this->assertTrue($service->current()->is($second));
        $service->select($observer, null);
        $this->assertNull($service->current());
        $this->assertNull(session('memorylab.active_scenario_id'));
    }

    public function test_a_missing_selected_scenario_does_not_fall_back_to_another_scenario(): void
    {
        $scenario = $this->configure($this->user('administrador'));
        $this->actingAs($this->user('observador'));
        session()->put('memorylab.active_scenario_id', $scenario->id + 1000);

        $this->assertNull(app(ActiveScenarioService::class)->current());
        Livewire::test(DashboardStats::class)->assertViewHas('summary', null);
    }

    public function test_dashboard_does_not_publish_metrics_without_a_selected_configuration(): void
    {
        $this->configure($this->user('administrador'));
        $observer = $this->user('observador');

        Livewire::actingAs($observer)->test(DashboardStats::class)->assertViewHas('summary', null);

        $draft = Scenario::create(['name' => 'Borrador sin memoria']);
        app(ActiveScenarioService::class)->select($observer, $draft->id);
        Livewire::test(DashboardStats::class)->assertViewHas('summary', null);
    }

    public function test_dashboard_counts_only_the_selected_scenario_and_refreshes_its_usage(): void
    {
        $admin = $this->user('administrador');
        $selected = $this->configure($admin, ['name' => 'Memoria seleccionada']);
        $other = $this->configure($admin, ['name' => 'Otra memoria', 'ram_kb' => 8]);
        $process = $this->process($selected);
        $this->process($selected, ['name' => 'Proceso finalizado', 'status' => ProcessStatus::Terminated]);
        $otherProcess = $this->process($other);
        $this->occupiedPage($selected, $process, 0);
        $this->occupiedPage($other, $otherProcess, 0);
        $this->event($selected, SimulationEventType::PageFault);
        $this->event($selected, SimulationEventType::PageHit);
        $this->event($other, SimulationEventType::PageFault);
        $this->event($other, SimulationEventType::PageFault);
        $observer = $this->user('observador');
        $this->actingAs($observer);
        app(ActiveScenarioService::class)->select($observer, $selected->id);

        $component = Livewire::test(DashboardStats::class)->assertViewHas('summary', [
            'ram-total' => 16, 'ram-used' => 1, 'ram-available' => 15,
            'frames-total' => 16, 'frames-used' => 1, 'frames-free' => 15,
            'processes-active' => 1, 'page-faults' => 1, 'utilization' => 6.3,
        ]);

        $this->occupiedPage($selected, $process, 1);
        $this->event($selected, SimulationEventType::PageFault);

        $component->call('$refresh')->assertViewHas('summary', [
            'ram-total' => 16, 'ram-used' => 2, 'ram-available' => 14,
            'frames-total' => 16, 'frames-used' => 2, 'frames-free' => 14,
            'processes-active' => 1, 'page-faults' => 2, 'utilization' => 12.5,
        ]);
        $this->assertSame($selected->id, session('memorylab.active_scenario_id'));
    }

    public function test_dashboard_does_not_reuse_memory_view_permission_from_a_previous_request(): void
    {
        $scenario = $this->configure($this->user('administrador'));
        $observer = $this->user('observador');
        $this->actingAs($observer);
        app(ActiveScenarioService::class)->select($observer, $scenario->id);
        $component = Livewire::test(DashboardStats::class);
        $observer->fresh()->syncRoles([]);

        $component->call('$refresh')->assertViewHas('summary', null);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function configure(User $actor, array $input = [], ?int $scenarioId = null): Scenario
    {
        return app(MemoryConfigurationService::class)->configure($actor, $scenarioId, array_replace([
            'name' => 'Escenario de prueba', 'ram_kb' => 16, 'page_kb' => 1, 'secondary_kb' => 64,
        ], $input));
    }

    private function process(Scenario $scenario, array $attributes = []): Process
    {
        return Process::create(array_replace([
            'scenario_id' => $scenario->id, 'name' => 'Proceso de prueba', 'size_bytes' => 2048,
        ], $attributes));
    }

    private function occupiedPage(Scenario $scenario, Process $process, int $number): Page
    {
        return Page::create([
            'scenario_id' => $scenario->id, 'process_id' => $process->id,
            'page_number' => $number,
            'frame_id' => $scenario->frames()->where('frame_number', $number)->firstOrFail()->id,
        ]);
    }

    private function event(Scenario $scenario, SimulationEventType $type): SimulationEvent
    {
        return SimulationEvent::create([
            'scenario_id' => $scenario->id, 'type' => $type,
            'description' => 'Evento de prueba.', 'occurred_at' => now(),
        ]);
    }

    private function assertOriginalMemory(Scenario $scenario, array $frameIds): void
    {
        $configuration = $scenario->fresh()->configuration;
        $this->assertSame(16384, $configuration->ram_size_bytes);
        $this->assertSame(1024, $configuration->page_size_bytes);
        $this->assertSame(65536, $configuration->secondary_storage_bytes);
        $this->assertSame($frameIds, $scenario->frames()->orderBy('frame_number')->pluck('id')->all());
        $this->assertDatabaseCount('simulation_events', 2);
    }

    private function assertValidationFailure(callable $operation, string $error): void
    {
        try {
            $operation();
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($error, $exception->errors());

            return;
        }

        $this->fail('La operación debe rechazarse mediante validación.');
    }
}
