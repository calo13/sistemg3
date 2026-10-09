<?php

namespace Tests\Feature;

use App\Enums\ProcessStatus;
use App\Enums\ScenarioStatus;
use App\Enums\SimulationEventType;
use App\Enums\SimulationMode;
use App\Livewire\DashboardStats;
use App\Livewire\ProcessManager;
use App\Models\MemoryFrame;
use App\Models\Process;
use App\Models\Scenario;
use App\Models\SimulationEvent;
use App\Models\User;
use App\Services\ActiveScenarioService;
use App\Services\MemoryConfigurationService;
use App\Services\ProcessManagerService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ProcessManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_rounds_pages_up_and_records_the_process_without_allocating_ram(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin, ['page_kb' => 2]);
        $frameIds = $scenario->frames()->orderBy('frame_number')->pluck('id')->all();
        $process = $this->createProcess($admin, $scenario, ['name' => '  Editor de texto  ', 'size_kb' => 3]);

        $this->assertSame('Editor de texto', $process->name);
        $this->assertSame($scenario->id, $process->scenario_id);
        $this->assertSame(3072, $process->size_bytes);
        $this->assertSame(ProcessStatus::Ready, $process->status);
        $this->assertSame(2, $process->pages_count);
        $this->assertSame([0, 1], $process->pages()->orderBy('page_number')->pluck('page_number')->all());
        $this->assertSame(0, $process->pages()->whereNotNull('frame_id')->count());
        $this->assertSame([$scenario->id], $process->pages()->pluck('scenario_id')->unique()->values()->all());
        $this->assertSame($frameIds, $scenario->frames()->orderBy('frame_number')->pluck('id')->all());
        $this->assertSame(ScenarioStatus::Ready, $scenario->fresh()->status);
        $event = $process->events()->sole();
        $this->assertSame(SimulationEventType::ProcessCreated, $event->type);
        $this->assertSame($scenario->id, $event->scenario_id);
        $this->assertSame($admin->id, $event->user_id);
        $this->assertEquals([
            'name' => 'Editor de texto', 'size_bytes' => 3072,
            'page_size_bytes' => 2048, 'page_count' => 2, 'secondary_bytes_reserved' => 4096,
        ], $event->metadata);
        $this->assertDatabaseCount('processes', 1);
        $this->assertDatabaseCount('pages', 2);
        $this->assertDatabaseCount('simulation_events', 3);
        $this->assertDatabaseCount('segments', 0);
        $this->assertSame(0, $scenario->events()->where('type', SimulationEventType::PageFault)->count());
    }

    public static function creatorRoles(): array
    {
        return ['administrator' => ['administrador'], 'operator' => ['operador']];
    }

    #[DataProvider('creatorRoles')]
    public function test_administrators_and_operators_can_create_processes(string $role): void
    {
        $scenario = $this->memory($this->user('administrador'));
        $actor = $this->user($role);

        $process = $this->createProcess($actor, $scenario, ['size_kb' => '4']);

        $this->assertSame(4096, $process->size_bytes);
        $this->assertSame(4, $process->pages_count);
        $this->assertSame($actor->id, $process->events()->sole()->user_id);
    }

    public function test_an_observer_cannot_create_a_process_through_the_service(): void
    {
        $scenario = $this->memory($this->user('administrador'));

        try {
            $this->createProcess($this->user('observador'), $scenario);
            $this->fail('La creación requiere processes.create.');
        } catch (AuthorizationException) {
            $this->assertNoCreatedProcesses();
        }
    }

    public function test_service_rechecks_permissions_after_the_operator_role_is_revoked(): void
    {
        $scenario = $this->memory($this->user('administrador'));
        $operator = $this->user('operador');
        $this->assertTrue($operator->can('processes.create'));
        $operator->fresh()->syncRoles(['observador']);

        try {
            $this->createProcess($operator, $scenario);
            $this->fail('Los permisos revocados no deben conservarse en el servicio.');
        } catch (AuthorizationException) {
            $this->assertNoCreatedProcesses();
        }
    }

    public function test_forged_process_state_actor_pages_and_scenario_are_ignored(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $other = $this->memory($admin, ['name' => 'Otra memoria']);
        $operator = $this->user('operador');

        $process = $this->createProcess($operator, $scenario, [
            'size_kb' => 2, 'size_bytes' => 1, 'status' => ProcessStatus::Terminated->value,
            'scenario_id' => $other->id, 'user_id' => $admin->id,
            'pages' => [['page_number' => 99, 'frame_id' => $scenario->frames()->first()->id]],
        ]);

        $this->assertSame($scenario->id, $process->scenario_id);
        $this->assertSame(2048, $process->size_bytes);
        $this->assertSame(ProcessStatus::Ready, $process->status);
        $this->assertSame([0, 1], $process->pages()->orderBy('page_number')->pluck('page_number')->all());
        $this->assertSame(0, $process->pages()->whereNotNull('frame_id')->count());
        $this->assertSame($operator->id, $process->events()->sole()->user_id);
        $this->assertSame(0, $other->processes()->count());
    }

    public static function invalidInputs(): array
    {
        return [
            'missing name' => [['name' => null], 'name'],
            'blank name' => [['name' => '   '], 'name'],
            'name exceeds limit' => [['name' => str_repeat('a', 101)], 'name'],
            'missing size' => [['size_kb' => null], 'size_kb'],
            'zero size' => [['size_kb' => 0], 'size_kb'],
            'negative size' => [['size_kb' => -1], 'size_kb'],
            'fractional size' => [['size_kb' => 1.5], 'size_kb'],
            'non numeric size' => [['size_kb' => 'invalid'], 'size_kb'],
            'size exceeds limit' => [['size_kb' => 65537], 'size_kb'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_input_does_not_create_process_pages_or_history(array $input, string $error): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $frameIds = $scenario->frames()->orderBy('frame_number')->pluck('id')->all();

        $this->assertValidationFailure(fn () => $this->createProcess($admin, $scenario, $input), $error);

        $this->assertNoCreatedProcesses();
        $this->assertSame($frameIds, $scenario->frames()->orderBy('frame_number')->pluck('id')->all());
    }

    public function test_page_reservation_uses_the_rounded_size_and_rejects_when_secondary_storage_is_full(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin, ['ram_kb' => 8, 'page_kb' => 4, 'secondary_kb' => 4]);
        $first = $this->createProcess($admin, $scenario, ['size_kb' => 1]);

        $this->assertSame(1, $first->pages_count);
        $this->assertSame(4096, $first->events()->sole()->metadata['secondary_bytes_reserved']);
        $this->assertSame([
            'page_size_bytes' => 4096, 'secondary_total_bytes' => 4096,
            'secondary_used_bytes' => 4096, 'secondary_available_bytes' => 0,
        ], app(ProcessManagerService::class)->capacity($scenario));
        $this->assertValidationFailure(fn () => $this->createProcess($admin, $scenario, ['size_kb' => 1]), 'size_kb');

        $this->assertDatabaseCount('processes', 1);
        $this->assertDatabaseCount('pages', 1);
        $this->assertDatabaseCount('simulation_events', 3);
        $this->assertFalse($first->pages()->sole()->present);
    }

    public function test_a_process_larger_than_ram_is_allowed_when_secondary_storage_has_room(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin, ['ram_kb' => 4, 'secondary_kb' => 16]);

        $process = $this->createProcess($admin, $scenario, ['size_kb' => 8]);

        $this->assertSame(8192, $process->size_bytes);
        $this->assertSame(8, $process->pages_count);
        $this->assertSame(4, $scenario->frames()->count());
        $this->assertSame(0, $process->pages()->whereNotNull('frame_id')->count());
    }

    public function test_zero_secondary_storage_rejects_a_process_without_occupying_a_free_ram_frame(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin, ['secondary_kb' => 0]);

        $this->assertValidationFailure(fn () => $this->createProcess($admin, $scenario, ['size_kb' => 1]), 'size_kb');

        $this->assertNoCreatedProcesses();
        $this->assertSame(0, $scenario->frames()->whereHas('page')->count());
    }

    public function test_pages_already_in_ram_are_excluded_from_secondary_storage_reservation(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin, ['page_kb' => 2, 'secondary_kb' => 4]);
        $first = $this->createProcess($admin, $scenario, ['size_kb' => 3]);
        $first->pages()->where('page_number', 0)->firstOrFail()
            ->update(['frame_id' => $scenario->frames()->where('frame_number', 0)->firstOrFail()->id]);
        $this->assertSame([
            'page_size_bytes' => 2048, 'secondary_total_bytes' => 4096,
            'secondary_used_bytes' => 2048, 'secondary_available_bytes' => 2048,
        ], app(ProcessManagerService::class)->capacity($scenario));

        $second = $this->createProcess($admin, $scenario, ['name' => 'Otro proceso', 'size_kb' => 2]);

        $this->assertSame(1, $second->pages_count);
        $this->assertFalse($second->pages()->sole()->present);
        $this->assertSame(2, $scenario->pages()->whereNull('frame_id')->count());
        $this->assertSame(1, $scenario->pages()->whereNotNull('frame_id')->count());
    }

    public function test_terminated_process_pages_keep_their_secondary_reservation_until_they_are_released(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin, ['secondary_kb' => 2]);
        $first = $this->createProcess($admin, $scenario, ['size_kb' => 2]);
        $first->update(['status' => ProcessStatus::Terminated]);

        $this->assertValidationFailure(fn () => $this->createProcess($admin, $scenario, ['size_kb' => 1]), 'size_kb');

        $this->assertSame(ProcessStatus::Terminated, $first->fresh()->status);
        $this->assertSame(2, $first->pages()->count());
        $this->assertDatabaseCount('processes', 1);
        $this->assertDatabaseCount('simulation_events', 3);
    }

    public function test_secondary_reservation_is_scoped_to_the_current_scenario(): void
    {
        $admin = $this->user('administrador');
        $selected = $this->memory($admin, ['name' => 'Memoria seleccionada', 'secondary_kb' => 1]);
        $other = $this->memory($admin, ['name' => 'Memoria llena', 'secondary_kb' => 64]);
        $this->createProcess($admin, $other, ['size_kb' => 64]);

        $process = $this->createProcess($admin, $selected, ['size_kb' => 1]);

        $this->assertSame($selected->id, $process->scenario_id);
        $this->assertSame(1, $process->pages_count);
        $this->assertSame(64, $other->pages()->whereNull('frame_id')->count());
    }

    public function test_a_process_at_the_size_and_page_limits_is_accepted(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin, ['ram_kb' => 64, 'page_kb' => 64, 'secondary_kb' => 65536]);

        $process = $this->createProcess($admin, $scenario, ['size_kb' => 65536]);

        $this->assertSame(67108864, $process->size_bytes);
        $this->assertSame(1024, $process->pages_count);
        $this->assertSame(1023, $process->pages()->max('page_number'));
        $this->assertSame(0, $process->pages()->whereNotNull('frame_id')->count());
    }

    public function test_exceeding_the_page_limit_is_rejected_before_creating_records(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin, ['secondary_kb' => 2048]);

        $this->assertValidationFailure(fn () => $this->createProcess($admin, $scenario, ['size_kb' => 1025]), 'size_kb');

        $this->assertNoCreatedProcesses();
    }

    public function test_process_limit_counts_terminated_processes_and_accepts_the_last_available_slot(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $records = [];
        for ($number = 0; $number < 99; $number++) {
            $records[] = [
                'scenario_id' => $scenario->id, 'name' => 'Finalizado '.$number,
                'size_bytes' => 1024, 'status' => ProcessStatus::Terminated->value,
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        Process::insert($records);

        $last = $this->createProcess($admin, $scenario, ['size_kb' => 1]);
        $this->assertSame(ProcessStatus::Ready, $last->status);
        $this->assertSame(100, $scenario->processes()->count());
        $this->assertValidationFailure(fn () => $this->createProcess($admin, $scenario, ['size_kb' => 1]), 'scenario_id');

        $this->assertDatabaseCount('processes', 100);
        $this->assertDatabaseCount('pages', 1);
        $this->assertDatabaseCount('simulation_events', 3);
    }

    public static function eligibleStates(): array
    {
        return ['ready' => [ScenarioStatus::Ready], 'running' => [ScenarioStatus::Running]];
    }

    #[DataProvider('eligibleStates')]
    public function test_creation_keeps_an_eligible_scenarios_state(ScenarioStatus $status): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $scenario->update(['status' => $status]);

        $process = $this->createProcess($admin, $scenario);

        $this->assertSame(ProcessStatus::Ready, $process->status);
        $this->assertSame($status, $scenario->fresh()->status);
    }

    public static function blockedStates(): array
    {
        return ['draft' => [ScenarioStatus::Draft], 'completed' => [ScenarioStatus::Completed]];
    }

    #[DataProvider('blockedStates')]
    public function test_draft_or_completed_scenarios_cannot_receive_processes(ScenarioStatus $status): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $scenario->update(['status' => $status]);

        $this->assertValidationFailure(fn () => $this->createProcess($admin, $scenario), 'scenario_id');

        $this->assertNoCreatedProcesses();
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
    public function test_creation_cannot_apply_paging_to_other_scenario_modes(SimulationMode $mode): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $scenario->update(['mode' => $mode]);

        $this->assertValidationFailure(fn () => $this->createProcess($admin, $scenario), 'scenario_id');

        $this->assertNoCreatedProcesses();
        $this->assertSame($mode, $scenario->fresh()->mode);
    }

    public function test_a_scenario_without_a_memory_configuration_cannot_receive_processes(): void
    {
        $admin = $this->user('administrador');
        $scenario = Scenario::create(['name' => 'Sin memoria', 'status' => ScenarioStatus::Ready]);

        $this->assertValidationFailure(fn () => $this->createProcess($admin, $scenario), 'scenario_id');

        $this->assertDatabaseCount('processes', 0);
        $this->assertDatabaseCount('pages', 0);
        $this->assertDatabaseCount('simulation_events', 0);
    }

    public function test_a_missing_scenario_cannot_create_a_process_in_another_context(): void
    {
        $admin = $this->user('administrador');
        $this->memory($admin);

        try {
            app(ProcessManagerService::class)->create($admin, 999999, ['name' => 'Proceso perdido', 'size_kb' => 1]);
            $this->fail('Un escenario inexistente debe rechazarse.');
        } catch (ModelNotFoundException) {
            $this->assertNoCreatedProcesses();
        }
    }

    public static function inconsistentFrames(): array
    {
        return ['missing frame' => ['missing'], 'extra frame' => ['extra'], 'noncontiguous frames' => ['gap']];
    }

    #[DataProvider('inconsistentFrames')]
    public function test_inconsistent_memory_frames_cannot_receive_new_processes(string $inconsistency): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        if ($inconsistency === 'missing') {
            $scenario->frames()->where('frame_number', 0)->delete();
        } elseif ($inconsistency === 'extra') {
            MemoryFrame::create(['scenario_id' => $scenario->id, 'frame_number' => 16]);
        } else {
            $scenario->frames()->where('frame_number', 0)->firstOrFail()->update(['frame_number' => 99]);
        }
        $frameIds = $scenario->frames()->orderBy('frame_number')->pluck('id')->all();

        $this->assertValidationFailure(fn () => $this->createProcess($admin, $scenario), 'scenario_id');

        $this->assertNoCreatedProcesses();
        $this->assertSame($frameIds, $scenario->frames()->orderBy('frame_number')->pluck('id')->all());
    }

    public function test_event_failure_rolls_back_the_new_process_and_all_its_pages(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $frameIds = $scenario->frames()->orderBy('frame_number')->pluck('id')->all();
        $dispatcher = SimulationEvent::getEventDispatcher();
        SimulationEvent::setEventDispatcher(clone $dispatcher);
        SimulationEvent::creating(function (SimulationEvent $event): void {
            if ($event->type === SimulationEventType::ProcessCreated) {
                throw new RuntimeException('Falló el evento del proceso de prueba.');
            }
        });

        try {
            try {
                $this->createProcess($admin, $scenario);
                $this->fail('El proceso y sus páginas deben revertirse junto con su evento.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Falló el evento del proceso de prueba.', $exception->getMessage());
            }
        } finally {
            SimulationEvent::setEventDispatcher($dispatcher);
        }

        $this->assertNoCreatedProcesses();
        $this->assertSame($frameIds, $scenario->frames()->orderBy('frame_number')->pluck('id')->all());
        $this->assertSame(ScenarioStatus::Ready, $scenario->fresh()->status);
    }

    public function test_page_requires_authentication_and_allows_memory_readers(): void
    {
        $this->get('/procesos')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/procesos')->assertForbidden();

        foreach (['administrador', 'operador', 'observador'] as $role) {
            $this->actingAs($this->user($role))->get('/procesos')->assertOk();
        }
    }

    public function test_observer_can_read_processes_but_a_forged_livewire_create_is_forbidden(): void
    {
        $scenario = $this->memory($this->user('administrador'));
        $observer = $this->user('observador');

        Livewire::actingAs($observer)->test(ProcessManager::class)
            ->set('scenarioId', (string) $scenario->id)->assertViewHas('canCreate', false)
            ->set('name', 'Proceso no autorizado')->set('sizeKb', 1)
            ->call('create')->assertForbidden();

        $this->assertNoCreatedProcesses();
    }

    public function test_livewire_rechecks_permissions_after_the_operator_role_is_revoked(): void
    {
        $scenario = $this->memory($this->user('administrador'));
        $operator = $this->user('operador');
        $component = Livewire::actingAs($operator)->test(ProcessManager::class)
            ->set('scenarioId', (string) $scenario->id)->set('name', 'Proceso de prueba');
        $operator->fresh()->syncRoles(['observador']);

        $component->call('create')->assertForbidden();

        $this->assertNoCreatedProcesses();
    }

    public function test_livewire_creation_requires_an_explicit_scenario(): void
    {
        $this->memory($this->user('administrador'));

        Livewire::actingAs($this->user('operador'))->test(ProcessManager::class)
            ->set('name', 'Sin selección')->set('sizeKb', 1)
            ->call('create')->assertHasErrors(['scenarioId']);

        $this->assertNoCreatedProcesses();
        $this->assertNull(session('memorylab.active_scenario_id'));
    }

    public function test_livewire_maps_size_validation_to_the_form_field(): void
    {
        $scenario = $this->memory($this->user('administrador'));

        Livewire::actingAs($this->user('operador'))->test(ProcessManager::class)
            ->set('scenarioId', (string) $scenario->id)->set('name', 'Tamaño inválido')->set('sizeKb', 0)
            ->call('create')->assertHasErrors(['sizeKb']);

        $this->assertNoCreatedProcesses();
    }

    public function test_page_preview_rounds_up_and_rejects_invalid_size_without_creating_records(): void
    {
        $scenario = $this->memory($this->user('administrador'), ['page_kb' => 2]);

        Livewire::actingAs($this->user('operador'))->test(ProcessManager::class)
            ->set('scenarioId', (string) $scenario->id)
            ->set('sizeKb', 3)->assertSet('pageCount', 2)
            ->set('sizeKb', 4)->assertSet('pageCount', 2)
            ->set('sizeKb', 5)->assertSet('pageCount', 3)
            ->set('sizeKb', 0)->assertSet('pageCount', null)
            ->set('sizeKb', 'invalid')->assertSet('pageCount', null);

        $this->assertNoCreatedProcesses();
    }

    public function test_livewire_creates_the_process_in_the_selected_session_context(): void
    {
        $scenario = $this->memory($this->user('administrador'), ['page_kb' => 2]);
        $operator = $this->user('operador');

        Livewire::actingAs($operator)->test(ProcessManager::class)
            ->set('scenarioId', (string) $scenario->id)->set('name', 'Proceso desde el panel')->set('sizeKb', 3)
            ->call('create')->assertHasNoErrors();

        $process = Process::sole();
        $this->assertSame('Proceso desde el panel', $process->name);
        $this->assertSame($scenario->id, $process->scenario_id);
        $this->assertSame(2, $process->pages()->count());
        $this->assertSame($scenario->id, session('memorylab.active_scenario_id'));
        $this->assertSame($operator->id, $process->events()->sole()->user_id);
    }

    public function test_an_invalid_livewire_scenario_id_cannot_change_the_previous_context_or_create_a_process(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $this->actingAs($admin);
        app(ActiveScenarioService::class)->select($admin, $scenario->id);

        Livewire::test(ProcessManager::class)
            ->set('scenarioId', '999999')->assertHasErrors(['scenarioId'])
            ->set('name', 'Destino inválido')->set('sizeKb', 1)
            ->call('create')->assertHasErrors(['scenarioId']);

        $this->assertSame($scenario->id, session('memorylab.active_scenario_id'));
        $this->assertNoCreatedProcesses();
    }

    public function test_process_list_stays_empty_until_a_scenario_is_selected(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $this->createProcess($admin, $scenario);

        Livewire::actingAs($this->user('observador'))->test(ProcessManager::class)
            ->assertViewHas('scenario', null)->assertViewHas('capacity', null)
            ->assertViewHas('processes', fn ($processes) => $processes->total() === 0);

        $this->assertNull(session('memorylab.active_scenario_id'));
    }

    public function test_process_pagination_is_scoped_and_resets_when_the_selected_scenario_changes(): void
    {
        $admin = $this->user('administrador');
        $first = $this->memory($admin, ['name' => 'Primera memoria']);
        $second = $this->memory($admin, ['name' => 'Segunda memoria']);
        for ($number = 0; $number < 16; $number++) {
            $this->createProcess($admin, $first, ['name' => 'Proceso A '.$number, 'size_kb' => 1]);
        }
        $this->createProcess($admin, $second, ['name' => 'Proceso B', 'size_kb' => 3]);
        $observer = $this->user('observador');

        Livewire::actingAs($observer)->test(ProcessManager::class)
            ->set('scenarioId', (string) $first->id)
            ->assertViewHas('processes', fn ($processes) => $processes->total() === 16
                && $processes->count() === 15
                && $processes->currentPage() === 1
                && $processes->getCollection()->every(fn (Process $process) => $process->scenario_id === $first->id && $process->pages_count === 1))
            ->call('gotoPage', 2)
            ->assertViewHas('processes', fn ($processes) => $processes->count() === 1
                && $processes->currentPage() === 2
                && $processes->first()->name === 'Proceso A 0')
            ->set('scenarioId', (string) $second->id)
            ->assertViewHas('processes', fn ($processes) => $processes->total() === 1
                && $processes->currentPage() === 1
                && $processes->first()->name === 'Proceso B'
                && $processes->first()->pages_count === 3)
            ->assertViewHas('capacity', [
                'page_size_bytes' => 1024, 'secondary_total_bytes' => 65536,
                'secondary_used_bytes' => 3072, 'secondary_available_bytes' => 62464,
            ]);

        $this->assertSame($second->id, session('memorylab.active_scenario_id'));
    }

    public function test_creation_updates_the_active_process_counter_without_consuming_ram_or_causing_a_fault(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $observer = $this->user('observador');
        $this->actingAs($observer);
        app(ActiveScenarioService::class)->select($observer, $scenario->id);
        $dashboard = Livewire::test(DashboardStats::class);

        $this->createProcess($admin, $scenario, ['size_kb' => 4]);

        $dashboard->call('$refresh')->assertViewHas('summary', [
            'ram-total' => 16, 'ram-used' => 0, 'ram-available' => 16,
            'frames-total' => 16, 'frames-used' => 0, 'frames-free' => 16,
            'processes-active' => 1, 'page-faults' => 0, 'utilization' => 0.0,
        ]);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function memory(User $admin, array $input = []): Scenario
    {
        return app(MemoryConfigurationService::class)->configure($admin, null, array_replace([
            'name' => 'Memoria de prueba', 'ram_kb' => 16, 'page_kb' => 1, 'secondary_kb' => 64,
        ], $input));
    }

    private function createProcess(User $actor, Scenario $scenario, array $input = []): Process
    {
        return app(ProcessManagerService::class)->create($actor, $scenario->id, array_replace([
            'name' => 'Proceso de prueba', 'size_kb' => 4,
        ], $input));
    }

    private function assertNoCreatedProcesses(): void
    {
        $this->assertDatabaseCount('processes', 0);
        $this->assertDatabaseCount('pages', 0);
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
