<?php

namespace Tests\Feature;

use App\Enums\ProcessStatus;
use App\Enums\ScenarioStatus;
use App\Enums\SimulationEventType;
use App\Enums\SimulationMode;
use App\Livewire\MemoryStress;
use App\Models\Scenario;
use App\Models\SimulationEvent;
use App\Models\User;
use App\Services\MemoryConfigurationService;
use App\Services\MemoryStatisticsService;
use App\Services\MemoryStressService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class MemoryStressTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_requests_fill_two_frames_and_fifo_evicts_four_pages_from_three_new_processes(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $actor = $this->user('operador');
        $lastEventId = SimulationEvent::max('id');

        $result = app(MemoryStressService::class)->run($actor, $scenario->id, [
            'process_count' => 3, 'size_kb' => 2, 'scenario_id' => 999999,
            'user_id' => $admin->id, 'name' => 'Nombre enviado por cliente',
        ]);

        $this->assertSame($scenario->id, $result['scenario_id']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $result['run_id']);
        $this->assertSame(6, $result['requested_pages']);
        $this->assertSame(4, $result['evictions']);
        $this->assertSame([
            'ram' => ['total_bytes' => 2048, 'used_bytes' => 0, 'available_bytes' => 2048,
                'frames_total' => 2, 'frames_used' => 0, 'frames_free' => 2, 'selected_frames_used' => 0],
            'secondary' => ['total_bytes' => 65536, 'used_bytes' => 0, 'available_bytes' => 65536, 'selected_pages' => 0, 'selected_bytes' => 0],
            'active_process_count' => 0, 'page_fault_count' => 0,
        ], $result['before']);
        $this->assertSame([
            'ram' => ['total_bytes' => 2048, 'used_bytes' => 2048, 'available_bytes' => 0,
                'frames_total' => 2, 'frames_used' => 2, 'frames_free' => 0, 'selected_frames_used' => 0],
            'secondary' => ['total_bytes' => 65536, 'used_bytes' => 4096, 'available_bytes' => 61440, 'selected_pages' => 0, 'selected_bytes' => 0],
            'active_process_count' => 3, 'page_fault_count' => 6,
        ], $result['after']);
        $this->assertCount(3, $result['created_processes']);
        $this->assertCount(3, array_unique(array_column($result['created_processes'], 'name')));
        $processes = $scenario->processes()->orderBy('id')->get();
        $this->assertSame($processes->pluck('id')->all(), array_column($result['created_processes'], 'id'));
        foreach ($result['created_processes'] as $created) {
            $this->assertSame(2, $created['size_kb']);
            $this->assertStringStartsWith('Carga', $created['name']);
            $this->assertNotSame('Nombre enviado por cliente', $created['name']);
        }
        $this->assertCount(6, $result['progress']);
        foreach ($result['progress'] as $index => $sample) {
            $this->assertSame([
                'request_index' => $index + 1,
                'process_name' => $result['created_processes'][intdiv($index, 2)]['name'],
                'page_number' => $index % 2, 'outcome' => 'PAGE_FAULT',
                'frames_used' => $index === 0 ? 1 : 2,
                'ram_used_bytes' => $index === 0 ? 1024 : 2048,
            ], $sample);
        }
        foreach ($processes as $index => $process) {
            $this->assertSame(ProcessStatus::Running, $process->status);
            $this->assertSame(2048, $process->size_bytes);
            $pages = $process->pages()->orderBy('page_number')->get();
            $this->assertSame([0, 1], $pages->pluck('page_number')->all());
            $this->assertSame($index === 2 ? 2 : 0, $pages->whereNotNull('frame_id')->count());
        }
        $events = $scenario->events()->where('id', '>', $lastEventId)->orderBy('id')->get();
        $this->assertCount(21, $events);
        $this->assertTrue($events->every(fn (SimulationEvent $event) => $event->user_id === $actor->id));
        $this->assertSame(array_fill(0, 3, SimulationEventType::ProcessCreated), $events->take(3)->pluck('type')->all());
        $this->assertSame(array_merge(...array_fill(0, 6, [
            SimulationEventType::PageRequest, SimulationEventType::PageFault, SimulationEventType::PageLoaded,
        ])), $events->skip(3)->pluck('type')->all());
        $loaded = $events->where('type', SimulationEventType::PageLoaded);
        $this->assertSame(4, $loaded->filter(fn ($event) => $event->metadata['evicted'] !== null)->count());
        $this->assertSame(ScenarioStatus::Running, $scenario->fresh()->status);
        $statistics = app(MemoryStatisticsService::class)->forScenario($scenario);
        $this->assertEquals(2, $statistics['ram-used']);
        $this->assertSame(2, $statistics['frames-used']);
        $this->assertSame(3, $statistics['processes-active']);
        $this->assertSame(6, $statistics['page-faults']);
    }

    public function test_page_size_rounds_each_process_before_requests_and_counts_whole_frames(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin, ['ram_kb' => 4, 'page_kb' => 2]);

        $result = $this->runWorkload($admin, $scenario, ['process_count' => 2, 'size_kb' => 3]);

        $this->assertSame(4, $result['requested_pages']);
        $this->assertSame(2, $result['evictions']);
        $this->assertSame(4096, $result['after']['ram']['used_bytes']);
        $this->assertSame(4096, $result['after']['secondary']['used_bytes']);
        $this->assertSame(4, $scenario->pages()->count());
        $this->assertSame(6144, (int) $scenario->processes()->sum('size_bytes'));
    }

    public function test_aggregate_limit_accepts_exactly_sixty_four_pages(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);

        $result = $this->runWorkload($admin, $scenario, ['process_count' => 1, 'size_kb' => 64]);

        $this->assertSame(64, $result['requested_pages']);
        $this->assertSame(62, $result['evictions']);
        $this->assertSame(64, $result['after']['page_fault_count']);
        $this->assertSame(62 * 1024, $result['after']['secondary']['used_bytes']);
        $this->assertSame(64, $scenario->pages()->count());
    }

    public function test_process_count_upper_bound_accepts_eight_processes(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);

        $result = $this->runWorkload($admin, $scenario, ['process_count' => 8, 'size_kb' => 1]);

        $this->assertCount(8, $result['created_processes']);
        $this->assertSame(8, $result['requested_pages']);
        $this->assertSame(6, $result['evictions']);
        $this->assertSame(8, $result['after']['active_process_count']);
    }

    public static function invalidInputs(): array
    {
        return [
            'zero count' => [['process_count' => 0], 'process_count'],
            'too many processes' => [['process_count' => 9], 'process_count'],
            'fractional count' => [['process_count' => 1.5], 'process_count'],
            'malformed count' => [['process_count' => [3]], 'process_count'],
            'zero size' => [['size_kb' => 0], 'size_kb'],
            'oversized process' => [['size_kb' => 65], 'size_kb'],
            'fractional size' => [['size_kb' => 1.5], 'size_kb'],
            'more than sixty four pages' => [['process_count' => 8, 'size_kb' => 9], 'size_kb'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_inputs_do_not_create_processes_pages_or_events(array $input, string $error): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => $this->runWorkload($admin, $scenario, $input), $error);

        $this->assertSame($before, $this->domainState());
    }

    public function test_secondary_capacity_failure_rolls_back_all_created_processes_before_any_request(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin, ['secondary_kb' => 5]);
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => $this->runWorkload($admin, $scenario), 'size_kb');

        $this->assertSame($before, $this->domainState());
    }

    public function test_failure_during_the_second_load_rolls_back_processes_requests_and_the_first_allocation(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $before = $this->domainState();
        $dispatcher = SimulationEvent::getEventDispatcher();
        SimulationEvent::setEventDispatcher(clone $dispatcher);
        $loads = 0;
        SimulationEvent::creating(function (SimulationEvent $event) use (&$loads): void {
            if ($event->type === SimulationEventType::PageLoaded && ++$loads === 2) {
                throw new RuntimeException('Fallo durante la segunda carga de prueba.');
            }
        });

        try {
            try {
                $this->runWorkload($admin, $scenario);
                $this->fail('La carga completa debe revertirse si falla una solicitud.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Fallo durante la segunda carga de prueba.', $exception->getMessage());
            }
        } finally {
            SimulationEvent::setEventDispatcher($dispatcher);
        }

        $this->assertSame(2, $loads);
        $this->assertSame($before, $this->domainState());
    }

    public static function nonExecutableScenarios(): array
    {
        return ['draft' => [ScenarioStatus::Draft], 'completed' => [ScenarioStatus::Completed]];
    }

    #[DataProvider('nonExecutableScenarios')]
    public function test_scenarios_that_are_not_executable_reject_the_workload(ScenarioStatus $status): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $scenario->update(['status' => $status]);
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => $this->runWorkload($admin, $scenario), 'scenario_id');

        $this->assertSame($before, $this->domainState());
    }

    public function test_wrong_mode_missing_configuration_and_inconsistent_frames_reject_without_changes(): void
    {
        $admin = $this->user('administrador');
        foreach (['mode', 'configuration', 'frames'] as $kind) {
            $scenario = $this->memory($admin, ['name' => 'Memoria '.$kind]);
            match ($kind) {
                'mode' => $scenario->update(['mode' => SimulationMode::Segmentation]),
                'configuration' => $scenario->configuration->delete(),
                'frames' => $scenario->frames()->where('frame_number', 1)->delete(),
            };
            $before = $this->domainState();
            $this->assertValidationFailure(fn () => $this->runWorkload($admin, $scenario), 'scenario_id');
            $this->assertSame($before, $this->domainState());
        }
    }

    public static function missingRunPermissions(): array
    {
        return array_map(fn ($permission) => [$permission], [
            'memory.view', 'tables.view', 'simulations.view', 'processes.create', 'pages.request', 'simulations.execute',
        ]);
    }

    #[DataProvider('missingRunPermissions')]
    public function test_each_read_and_write_permission_is_required_to_run_a_workload(string $missing): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $actor = User::factory()->create();
        $actor->givePermissionTo(array_values(array_diff([
            'memory.view', 'tables.view', 'simulations.view', 'processes.create', 'pages.request', 'simulations.execute',
        ], [$missing])));
        $before = $this->domainState();

        try {
            $this->runWorkload($actor, $scenario);
            $this->fail('Todos los permisos son necesarios para ejecutar la carga.');
        } catch (AuthorizationException) {
            $this->assertSame($before, $this->domainState());
        }
    }

    public function test_observer_and_an_operator_with_revoked_permissions_cannot_run(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $operator = $this->user('operador');
        $this->assertTrue($operator->can('processes.create'));
        $operator->fresh()->syncRoles(['observador']);
        $before = $this->domainState();

        foreach ([$this->user('observador'), $operator] as $actor) {
            try {
                $this->runWorkload($actor, $scenario);
                $this->fail('Los permisos vigentes deben permitir la carga.');
            } catch (AuthorizationException) {
                $this->assertSame($before, $this->domainState());
            }
        }
    }

    public function test_the_explicit_scenario_is_used_without_changing_another_saved_scenario_or_selection(): void
    {
        $admin = $this->user('administrador');
        $selected = $this->memory($admin, ['name' => 'Seleccionado']);
        $target = $this->memory($admin, ['name' => 'Destino']);
        session(['memorylab.active_scenario_id' => $selected->id]);
        $selectedBefore = $selected->fresh()->getAttributes();
        $selectedEvents = $selected->events()->count();

        $result = $this->runWorkload($admin, $target, ['process_count' => 1, 'size_kb' => 1]);

        $this->assertSame($target->id, $result['scenario_id']);
        $this->assertSame($selected->id, session('memorylab.active_scenario_id'));
        $this->assertSame($selectedBefore, $selected->fresh()->getAttributes());
        $this->assertSame($selectedEvents, $selected->events()->count());
        $this->assertSame(0, $selected->processes()->count());
        $this->assertSame(1, $target->processes()->count());
    }

    public function test_nonexistent_scenario_is_rejected_without_creating_data(): void
    {
        $admin = $this->user('administrador');
        $before = $this->domainState();

        try {
            app(MemoryStressService::class)->run($admin, 999999, ['process_count' => 3, 'size_kb' => 2]);
            $this->fail('La carga requiere un escenario existente.');
        } catch (ModelNotFoundException) {
            $this->assertSame($before, $this->domainState());
        }
    }

    public static function readerRoles(): array
    {
        return ['administrator' => ['administrador'], 'operator' => ['operador'], 'observer' => ['observador']];
    }

    #[DataProvider('readerRoles')]
    public function test_all_roles_can_read_the_workload_route_and_reading_creates_no_domain_data(string $role): void
    {
        $scenario = $this->memory($this->user('administrador'));
        $before = $this->domainState();
        $actor = $this->user($role);

        $this->actingAs($actor)->get(route('stress.index'))->assertOk();
        Livewire::actingAs($actor)->test(MemoryStress::class)->set('scenarioId', $scenario->id)
            ->assertSet('result', null)->call('$refresh');

        $this->assertSame($before, $this->domainState());
    }

    public function test_operator_can_run_from_the_form_and_the_result_matches_persisted_faults(): void
    {
        $scenario = $this->memory($this->user('administrador'));

        Livewire::actingAs($this->user('operador'))->test(MemoryStress::class)->set('scenarioId', $scenario->id)
            ->assertSeeHtml('wire:submit="run"')->assertSet('processCount', 3)->assertSet('sizeKb', 2)
            ->call('run')->assertHasNoErrors()->assertSet('result.requested_pages', 6)
            ->assertSet('result.evictions', 4)->assertSet('result.after.page_fault_count', 6)
            ->assertDispatched('memory-updated');

        $this->assertSame(3, $scenario->processes()->count());
        $this->assertSame(6, $scenario->events()->where('type', SimulationEventType::PageFault->value)->count());
    }

    public function test_observer_has_no_run_form_and_a_forged_action_is_forbidden(): void
    {
        $scenario = $this->memory($this->user('administrador'));
        $before = $this->domainState();
        $component = Livewire::actingAs($this->user('observador'))->test(MemoryStress::class)->set('scenarioId', $scenario->id);
        $this->assertStringNotContainsString('wire:submit="run"', $component->html());
        $component->call('run')->assertForbidden();

        $this->assertSame($before, $this->domainState());
    }

    public function test_changing_scenario_clears_the_previous_result_without_running_another_workload(): void
    {
        $admin = $this->user('administrador');
        $first = $this->memory($admin, ['name' => 'Primera memoria']);
        $second = $this->memory($admin, ['name' => 'Segunda memoria']);
        $component = Livewire::actingAs($this->user('operador'))->test(MemoryStress::class)
            ->set('scenarioId', $first->id)->set('processCount', 1)->set('sizeKb', 1)->call('run')
            ->assertHasNoErrors()->assertSet('result.scenario_id', $first->id);
        $before = $this->domainState();

        $component->set('scenarioId', $second->id)->assertSet('result', null)->call('$refresh');

        $this->assertSame($second->id, session('memorylab.active_scenario_id'));
        $this->assertSame($before, $this->domainState());
        $this->assertSame(0, $second->processes()->count());
    }

    public function test_form_maps_invalid_count_and_secondary_capacity_errors_without_partial_changes(): void
    {
        $scenario = $this->memory($this->user('administrador'), ['secondary_kb' => 5]);
        $before = $this->domainState();
        $component = Livewire::actingAs($this->user('operador'))->test(MemoryStress::class)->set('scenarioId', $scenario->id);

        $component->set('processCount', 9)->call('run')->assertHasErrors(['processCount'])->assertSet('result', null);
        $component->set('processCount', 3)->call('run')->assertHasErrors(['sizeKb'])->assertSet('result', null);

        $this->assertSame($before, $this->domainState());
    }

    public function test_workload_result_is_locked_and_permission_revocation_applies_to_a_mounted_form(): void
    {
        $scenario = $this->memory($this->user('administrador'));
        $operator = $this->user('operador');
        $component = Livewire::actingAs($operator)->test(MemoryStress::class)->set('scenarioId', $scenario->id);
        $before = $this->domainState();

        try {
            $component->set('result', ['requested_pages' => 1, 'evictions' => 0]);
            $this->fail('El cliente no puede fabricar un resultado de carga.');
        } catch (CannotUpdateLockedPropertyException) {
            $this->assertSame($before, $this->domainState());
        }
        $component = Livewire::test(MemoryStress::class)->set('scenarioId', $scenario->id);
        $operator->fresh()->syncRoles(['observador']);
        $component->call('run')->assertForbidden();
        $this->assertSame($before, $this->domainState());
    }

    public function test_route_requires_authentication_and_results_permission_in_addition_to_the_memory_read_permissions(): void
    {
        $this->get(route('stress.index'))->assertRedirect(route('login'));
        $actor = User::factory()->create();
        $actor->givePermissionTo(['memory.view', 'tables.view', 'simulations.view']);
        $this->actingAs($actor)->get(route('stress.index'))->assertForbidden();
        Livewire::actingAs($actor)->test(MemoryStress::class)->assertForbidden();
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function memory(User $actor, array $input = []): Scenario
    {
        return app(MemoryConfigurationService::class)->configure($actor, null, array_replace([
            'name' => 'Memoria de carga', 'ram_kb' => 2, 'page_kb' => 1, 'secondary_kb' => 64,
        ], $input));
    }

    private function runWorkload(User $actor, Scenario $scenario, array $input = []): array
    {
        return app(MemoryStressService::class)->run($actor, $scenario->id, array_replace(['process_count' => 3, 'size_kb' => 2], $input));
    }

    private function domainState(): array
    {
        $state = [];
        foreach (['scenarios', 'memory_configurations', 'memory_frames', 'processes', 'pages', 'segments', 'simulation_events'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }

        return $state;
    }

    private function assertValidationFailure(callable $operation, string $error): void
    {
        try {
            $operation();
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($error, $exception->errors());

            return;
        }

        $this->fail('La operacion debe rechazarse mediante validacion.');
    }
}
