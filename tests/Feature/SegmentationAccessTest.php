<?php

namespace Tests\Feature;

use App\Enums\ProcessStatus;
use App\Enums\ScenarioStatus;
use App\Enums\SegmentStatus;
use App\Enums\SimulationEventType;
use App\Enums\SimulationMode;
use App\Livewire\SegmentAccess;
use App\Models\Process;
use App\Models\Scenario;
use App\Models\Segment;
use App\Models\SimulationEvent;
use App\Models\User;
use App\Services\SegmentationService;
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

class SegmentationAccessTest extends TestCase
{
    use RefreshDatabase;

    public static function validAccesses(): array
    {
        return [
            'administrator first byte' => ['administrador', 0, 2048],
            'operator last byte' => ['operador', 1023, 3071],
        ];
    }

    #[DataProvider('validAccesses')]
    public function test_valid_access_uses_the_segment_base_and_records_one_canonical_event(string $role, int $offset, int $physicalAddress): void
    {
        [$scenario, $process, $segment] = $this->context($this->user('administrador'));
        $actor = $this->user($role);
        $segmentBefore = $segment->fresh()->getAttributes();
        $configurationBefore = $scenario->configuration->getAttributes();
        $eventCount = SimulationEvent::count();

        $result = $this->access($actor, $scenario, $process, 0, $offset);

        $this->assertSame([
            'event_id' => $result['event_id'], 'scenario_id' => $scenario->id, 'process_id' => $process->id,
            'segment_id' => $segment->id, 'segment_number' => 0, 'segment_name' => 'Codigo',
            'base' => 2048, 'size_bytes' => 1024, 'offset' => $offset, 'valid' => true,
            'physical_address' => $physicalAddress, 'outcome' => 'SEGMENT_ACCESS',
        ], $result);
        $event = SimulationEvent::findOrFail($result['event_id']);
        $this->assertSame($eventCount + 1, SimulationEvent::count());
        $this->assertSame(SimulationEventType::SegmentAccess, $event->type);
        $this->assertSame($actor->id, $event->user_id);
        $this->assertSame($scenario->id, $event->scenario_id);
        $this->assertSame($process->id, $event->process_id);
        $this->assertEquals([
            'mode' => 'SEGMENTATION', 'process_name' => $process->name, 'segment_id' => $segment->id,
            'segment_number' => 0, 'segment_name' => 'Codigo', 'base' => 2048, 'size_bytes' => 1024,
            'offset' => $offset, 'last_valid_offset' => 1023, 'physical_address' => $physicalAddress,
            'outcome' => 'SEGMENT_ACCESS',
        ], $event->metadata);
        $this->assertNotNull($event->occurred_at);
        $this->assertSame(ScenarioStatus::Running, $scenario->fresh()->status);
        $this->assertSame(ProcessStatus::Running, $process->fresh()->status);
        $this->assertSame($segmentBefore, $segment->fresh()->getAttributes());
        $this->assertSame($configurationBefore, $scenario->configuration->fresh()->getAttributes());
        $this->assertDatabaseCount('memory_frames', 0);
        $this->assertDatabaseCount('pages', 0);
    }

    public static function faultOffsets(): array
    {
        return ['first invalid byte' => [1024], 'larger offset' => [4096], 'unsigned limit' => [4294967295]];
    }

    #[DataProvider('faultOffsets')]
    public function test_out_of_segment_access_records_a_fault_without_a_physical_address_or_memory_changes(int $offset): void
    {
        $admin = $this->user('administrador');
        [$scenario, $process, $segment] = $this->context($admin);
        $before = $this->domainState(false);
        $eventCount = SimulationEvent::count();

        $result = $this->access($admin, $scenario, $process, 0, $offset);

        $this->assertFalse($result['valid']);
        $this->assertNull($result['physical_address']);
        $this->assertSame('SEGMENTATION_FAULT', $result['outcome']);
        $this->assertSame($segment->id, $result['segment_id']);
        $event = SimulationEvent::findOrFail($result['event_id']);
        $this->assertSame($eventCount + 1, SimulationEvent::count());
        $this->assertSame(SimulationEventType::SegmentationFault, $event->type);
        $this->assertSame($admin->id, $event->user_id);
        $this->assertSame($offset, $event->metadata['offset']);
        $this->assertSame(1023, $event->metadata['last_valid_offset']);
        $this->assertNull($event->metadata['physical_address']);
        $this->assertSame($before, $this->domainState(false));
        $this->assertSame(ScenarioStatus::Ready, $scenario->fresh()->status);
        $this->assertSame(ProcessStatus::Ready, $process->fresh()->status);
    }

    public function test_a_fault_after_success_preserves_running_states_and_does_not_allocate_memory(): void
    {
        $admin = $this->user('administrador');
        [$scenario, $process] = $this->context($admin);
        $this->access($admin, $scenario, $process, 0, 1);
        $before = $this->domainState(false);

        $this->access($admin, $scenario, $process, 0, 1024);

        $this->assertSame($before, $this->domainState(false));
        $this->assertSame(1, $scenario->events()->where('type', SimulationEventType::SegmentAccess->value)->count());
        $this->assertSame(1, $scenario->events()->where('type', SimulationEventType::SegmentationFault->value)->count());
    }

    public static function invalidArguments(): array
    {
        return [
            'negative offset' => [0, -1, 'offset'],
            'offset exceeds unsigned limit' => [0, 4294967296, 'offset'],
            'negative segment' => [-1, 0, 'segment_number'],
            'segment exceeds unsigned limit' => [4294967296, 0, 'segment_number'],
            'missing segment' => [99, 0, 'segment_number'],
        ];
    }

    #[DataProvider('invalidArguments')]
    public function test_invalid_access_arguments_produce_no_event_or_state_change(int $number, int $offset, string $error): void
    {
        $admin = $this->user('administrador');
        [$scenario, $process] = $this->context($admin);
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => $this->access($admin, $scenario, $process, $number, $offset), $error);

        $this->assertSame($before, $this->domainState());
    }

    public function test_released_and_other_process_segments_cannot_be_accessed_through_the_selected_process(): void
    {
        $admin = $this->user('administrador');
        [$scenario, $selected, $released] = $this->context($admin);
        $released->update(['status' => SegmentStatus::Released]);
        $other = $this->process($admin, $scenario, 'Otro proceso');
        $this->segment($admin, $scenario, $other, ['base' => 4096]);
        $this->segment($admin, $scenario, $other, ['base' => 5120]);
        [$otherScenario, $foreign] = $this->context($admin, 'Otra memoria');
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => $this->access($admin, $scenario, $selected, 0, 0), 'segment_number');
        $this->assertValidationFailure(fn () => $this->access($admin, $scenario, $selected, 1, 0), 'segment_number');
        $this->assertValidationFailure(fn () => $this->access($admin, $scenario, $foreign, 0, 0), 'process_id');
        $this->assertValidationFailure(fn () => app(SegmentationService::class)->access($admin, $otherScenario->id, 999999, 0, 0), 'process_id');

        $this->assertSame($before, $this->domainState());
    }

    public static function forbiddenScenarioStates(): array
    {
        return ['draft' => [ScenarioStatus::Draft], 'completed' => [ScenarioStatus::Completed]];
    }

    #[DataProvider('forbiddenScenarioStates')]
    public function test_non_executable_scenario_states_reject_access(ScenarioStatus $status): void
    {
        $admin = $this->user('administrador');
        [$scenario, $process] = $this->context($admin);
        $scenario->update(['status' => $status]);
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => $this->access($admin, $scenario, $process, 0, 0), 'scenario_id');

        $this->assertSame($before, $this->domainState());
    }

    public function test_terminated_processes_and_deleted_scenarios_are_rejected_without_an_access_event(): void
    {
        $admin = $this->user('administrador');
        [$scenario, $process] = $this->context($admin);
        $process->update(['status' => ProcessStatus::Terminated]);
        $before = $this->domainState();
        $this->assertValidationFailure(fn () => $this->access($admin, $scenario, $process, 0, 0), 'process_id');
        $this->assertSame($before, $this->domainState());

        try {
            app(SegmentationService::class)->access($admin, 999999, $process->id, 0, 0);
            $this->fail('No se puede acceder a un escenario inexistente.');
        } catch (ModelNotFoundException) {
            $this->assertSame($before, $this->domainState());
        }
    }

    public static function malformedMemory(): array
    {
        return ['wrong mode' => ['mode'], 'secondary storage' => ['secondary'], 'missing configuration' => ['configuration'], 'segment outside RAM' => ['segment']];
    }

    #[DataProvider('malformedMemory')]
    public function test_inconsistent_memory_is_rejected_before_computing_any_physical_address(string $kind): void
    {
        $admin = $this->user('administrador');
        [$scenario, $process, $segment] = $this->context($admin);
        match ($kind) {
            'mode' => $scenario->update(['mode' => SimulationMode::Paging]),
            'secondary' => $scenario->configuration->update(['secondary_storage_bytes' => 1024]),
            'configuration' => $scenario->configuration->delete(),
            'segment' => $segment->update(['base' => 16384]),
        };
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => $this->access($admin, $scenario, $process, 0, 0), 'scenario_id');

        $this->assertSame($before, $this->domainState());
    }

    public static function missingAccessPermissions(): array
    {
        return array_map(fn ($permission) => [$permission], [
            'memory.view', 'tables.view', 'simulations.view', 'segmentation.execute', 'simulations.execute',
        ]);
    }

    #[DataProvider('missingAccessPermissions')]
    public function test_all_read_and_execution_permissions_are_required(string $missing): void
    {
        $admin = $this->user('administrador');
        [$scenario, $process] = $this->context($admin);
        $actor = User::factory()->create();
        $actor->givePermissionTo(array_values(array_diff([
            'memory.view', 'tables.view', 'simulations.view', 'segmentation.execute', 'simulations.execute',
        ], [$missing])));
        $before = $this->domainState();

        try {
            $this->access($actor, $scenario, $process, 0, 0);
            $this->fail('Cada permiso de lectura y ejecucion es necesario para acceder.');
        } catch (AuthorizationException) {
            $this->assertSame($before, $this->domainState());
        }
    }

    public function test_service_reloads_permissions_for_an_operator_whose_role_was_revoked(): void
    {
        $admin = $this->user('administrador');
        [$scenario, $process] = $this->context($admin);
        $operator = $this->user('operador');
        $this->assertTrue($operator->can('segmentation.execute'));
        $operator->fresh()->syncRoles(['observador']);
        $before = $this->domainState();

        try {
            $this->access($operator, $scenario, $process, 0, 0);
            $this->fail('El rol previo no debe conservar acceso a la CPU.');
        } catch (AuthorizationException) {
            $this->assertSame($before, $this->domainState());
        }
    }

    public function test_failure_updating_the_process_rolls_back_the_event_and_scenario_status(): void
    {
        $admin = $this->user('administrador');
        [$scenario, $process] = $this->context($admin);
        $before = $this->domainState();
        $dispatcher = Process::getEventDispatcher();
        Process::setEventDispatcher(clone $dispatcher);
        Process::updating(function (Process $process): void {
            if ($process->status === ProcessStatus::Running) {
                throw new RuntimeException('Fallo al iniciar el proceso de prueba.');
            }
        });

        try {
            try {
                $this->access($admin, $scenario, $process, 0, 0);
                $this->fail('El evento y el estado deben revertirse juntos.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Fallo al iniciar el proceso de prueba.', $exception->getMessage());
            }
        } finally {
            Process::setEventDispatcher($dispatcher);
        }

        $this->assertSame($before, $this->domainState());
    }

    public function test_operator_form_shows_the_physical_result_and_a_fault_and_dispatches_both_updates(): void
    {
        [$scenario, $process] = $this->context($this->user('administrador'));
        $operator = $this->user('operador');
        $component = Livewire::actingAs($operator)->test(SegmentAccess::class, [
            'scenarioId' => $scenario->id, 'processId' => $process->id,
        ])->assertViewHas('canAccess', true)->assertSeeHtml('wire:submit="access"');
        $before = $this->domainState();
        $component->call('$refresh');
        $this->assertSame($before, $this->domainState());

        $component->set('offset', 1023)->call('access')->assertHasNoErrors()
            ->assertSet('result.valid', true)->assertSet('result.physical_address', 3071)
            ->assertSeeHtml('data-segment-outcome="SEGMENT_ACCESS"')->assertDispatched('memory-updated');
        $beforeFault = $this->domainState(false);
        $component->set('offset', 1024)->assertSet('result', null)->call('access')->assertHasNoErrors()
            ->assertSet('result.valid', false)->assertSet('result.physical_address', null)
            ->assertSeeHtml('data-segment-outcome="SEGMENTATION_FAULT"')->assertDispatched('memory-updated');
        $this->assertStringNotContainsString('data-segment-physical', $component->html());
        $this->assertSame($beforeFault, $this->domainState(false));
        $this->assertSame(5, SimulationEvent::count());
        $this->assertSame($operator->id, SimulationEvent::latest('id')->firstOrFail()->user_id);
        $component->set('segmentNumber', 0)->assertSet('result', null);
    }

    public function test_observer_can_read_the_context_but_has_no_form_and_cannot_forge_an_access(): void
    {
        [$scenario, $process] = $this->context($this->user('administrador'));
        $before = $this->domainState();
        $component = Livewire::actingAs($this->user('observador'))->test(SegmentAccess::class, [
            'scenarioId' => $scenario->id, 'processId' => $process->id,
        ])->assertViewHas('canAccess', false)->assertViewHas('snapshot', fn ($snapshot) => $snapshot['selected_process']->id === $process->id);
        $this->assertStringNotContainsString('wire:submit=', $component->html());
        $component->call('$refresh');
        $this->assertSame($before, $this->domainState());
        $component->call('access')->assertForbidden();
        $this->assertSame($before, $this->domainState());
    }

    public function test_component_validates_input_and_maps_service_errors_without_recording_attempts(): void
    {
        [$scenario, $process, $segment] = $this->context($this->user('administrador'));
        $segment->update(['status' => SegmentStatus::Released]);
        $before = $this->domainState();
        $component = Livewire::actingAs($this->user('operador'))->test(SegmentAccess::class, [
            'scenarioId' => $scenario->id, 'processId' => $process->id,
        ]);

        $component->set('offset', -1)->call('access')->assertHasErrors(['offset'])->assertSet('result', null);
        $component->set('offset', 'abc')->call('access')->assertHasErrors(['offset']);
        $component->set('offset', 0)->set('segmentNumber', 0)->call('access')->assertHasErrors(['segmentNumber']);
        $this->assertSame($before, $this->domainState());

        $scenario->update(['status' => ScenarioStatus::Completed]);
        $before = $this->domainState();
        $component->set('segmentNumber', 0)->call('access')->assertHasErrors(['scenarioId']);
        $this->assertSame($before, $this->domainState());
    }

    public function test_mount_selects_the_first_active_segment_when_segment_zero_is_released(): void
    {
        $admin = $this->user('administrador');
        [$scenario, $process, $released] = $this->context($admin);
        $released->update(['status' => SegmentStatus::Released]);
        $active = $this->segment($admin, $scenario, $process, ['base' => 4096]);
        $before = $this->domainState();
        $component = Livewire::actingAs($this->user('operador'))->test(SegmentAccess::class, [
            'scenarioId' => $scenario->id, 'processId' => $process->id,
        ])->assertSet('segmentNumber', 1)->assertSet('result', null);

        $this->assertSame($before, $this->domainState());
        $component->call('access')->assertHasNoErrors()->assertSet('result.valid', true)
            ->assertSet('result.segment_id', $active->id)->assertSet('result.segment_number', 1)
            ->assertSet('result.physical_address', 4096)->assertDispatched('memory-updated');
        $this->assertSame(SegmentStatus::Released, $released->fresh()->status);
        $this->assertSame(1, $scenario->events()->where('type', SimulationEventType::SegmentAccess->value)->count());
    }

    public function test_component_has_no_fallback_context_or_foreign_process_leak(): void
    {
        $admin = $this->user('administrador');
        [$scenario] = $this->context($admin);
        [, $foreign] = $this->context($admin, 'Memoria ajena', 'Proceso privado');
        $operator = $this->user('operador');
        $before = $this->domainState();

        Livewire::actingAs($operator)->test(SegmentAccess::class)->assertViewHas('snapshot', null)
            ->call('access')->assertHasErrors(['scenarioId', 'processId'])->assertSet('result', null);
        Livewire::test(SegmentAccess::class, ['scenarioId' => $scenario->id, 'processId' => $foreign->id])
            ->assertViewHas('snapshot', null)->assertDontSee('Proceso privado')
            ->call('access')->assertHasErrors(['processId'])->assertSet('result', null);
        Livewire::test(SegmentAccess::class, ['scenarioId' => 999999, 'processId' => $foreign->id])
            ->call('access')->assertHasErrors(['scenarioId'])->assertSet('result', null);

        $this->assertSame($before, $this->domainState());
    }

    public function test_result_is_locked_and_a_revoked_operator_cannot_execute_from_a_mounted_form(): void
    {
        [$scenario, $process] = $this->context($this->user('administrador'));
        $operator = $this->user('operador');
        $params = ['scenarioId' => $scenario->id, 'processId' => $process->id];
        $component = Livewire::actingAs($operator)->test(SegmentAccess::class, $params);
        $before = $this->domainState();

        try {
            $component->set('result', ['valid' => true, 'physical_address' => 999999]);
            $this->fail('El cliente no puede fabricar una direccion fisica.');
        } catch (CannotUpdateLockedPropertyException) {
            $this->assertSame($before, $this->domainState());
        }
        $component = Livewire::test(SegmentAccess::class, $params);
        $operator->fresh()->syncRoles(['observador']);
        $component->call('access')->assertForbidden();
        $this->assertSame($before, $this->domainState());
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function context(User $actor, string $scenarioName = 'Memoria segmentada', string $processName = 'Proceso segmentado'): array
    {
        $scenario = app(SegmentationService::class)->configure($actor, ['name' => $scenarioName, 'ram_kb' => 16]);
        $process = $this->process($actor, $scenario, $processName);
        $segment = $this->segment($actor, $scenario, $process);

        return [$scenario, $process, $segment];
    }

    private function process(User $actor, Scenario $scenario, string $name): Process
    {
        return app(SegmentationService::class)->createProcess($actor, $scenario->id, ['name' => $name, 'size_kb' => 4]);
    }

    private function segment(User $actor, Scenario $scenario, Process $process, array $input = []): Segment
    {
        return app(SegmentationService::class)->createSegment($actor, $scenario->id, $process->id, array_replace([
            'name' => 'Codigo', 'base' => 2048, 'size_bytes' => 1024,
        ], $input));
    }

    private function access(User $actor, Scenario $scenario, Process $process, int $number, int $offset): array
    {
        return app(SegmentationService::class)->access($actor, $scenario->id, $process->id, $number, $offset);
    }

    private function domainState(bool $includeEvents = true): array
    {
        $state = [];
        $tables = ['scenarios', 'memory_configurations', 'memory_frames', 'processes', 'pages', 'segments'];
        if ($includeEvents) {
            $tables[] = 'simulation_events';
        }
        foreach ($tables as $table) {
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
