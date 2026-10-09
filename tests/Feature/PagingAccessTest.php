<?php

namespace Tests\Feature;

use App\Enums\ProcessStatus;
use App\Enums\ScenarioStatus;
use App\Enums\SimulationEventType;
use App\Livewire\PageRequest;
use App\Models\Process;
use App\Models\Scenario;
use App\Models\SimulationEvent;
use App\Models\User;
use App\Services\MemoryConfigurationService;
use App\Services\MemoryStatisticsService;
use App\Services\PagingService;
use App\Services\ProcessManagerService;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class PagingAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_fault_uses_the_lowest_free_frame_and_starts_the_process_and_scenario(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin, ['ram_kb' => 4]);
        $process = $this->process($admin, $scenario, 4);
        $this->assign($scenario, $process, 0, 0, '2026-10-07 12:00:00.000001');
        $this->assign($scenario, $process, 1, 2, '2026-10-07 12:00:00.000002');
        $lastEventId = SimulationEvent::max('id');

        $result = $this->access($admin, $scenario, $process, 2);

        $this->assertSame('PAGE_FAULT', $result['outcome']);
        $this->assertTrue($result['completed']);
        $this->assertTrue($result['present']);
        $this->assertSame(1, $result['frame_number']);
        $this->assertSame(1024, $result['physical_address']);
        $this->assertNull($result['evicted']);
        $page = $process->pages()->where('page_number', 2)->firstOrFail();
        $this->assertSame(1, $page->frame->frame_number);
        $this->assertInstanceOf(DateTimeImmutable::class, $page->loaded_at);
        $this->assertSame(ProcessStatus::Running, $process->fresh()->status);
        $this->assertSame(ScenarioStatus::Running, $scenario->fresh()->status);
        $this->assertSame([
            SimulationEventType::PageRequest, SimulationEventType::PageFault, SimulationEventType::PageLoaded,
        ], $this->eventTypesSince($scenario, $lastEventId));
        $loaded = $scenario->events()->where('type', SimulationEventType::PageLoaded)->sole();
        $this->assertSame($result['request_event_id'], $loaded->metadata['request_event_id']);
        $this->assertEquals($result, $loaded->metadata['result']);
        $snapshot = app(PagingService::class)->snapshot($admin, $scenario->id, $process->id);
        $this->assertSame(3, $snapshot['ram']['frames_used']);
        $this->assertSame(1024, $snapshot['secondary']['used_bytes']);
    }

    public function test_hit_keeps_the_fifo_timestamp_and_the_next_fault_evicts_the_oldest_loaded_page(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin, ['ram_kb' => 2]);
        $process = $this->process($admin, $scenario, 3);
        $this->travelTo(CarbonImmutable::parse('2026-10-07 12:00:00.000001', 'UTC'));
        $this->access($admin, $scenario, $process, 0);
        $this->travelTo(CarbonImmutable::parse('2026-10-07 12:00:00.000002', 'UTC'));
        $this->access($admin, $scenario, $process, 1);
        $first = $process->pages()->where('page_number', 0)->firstOrFail();
        $firstLoadedAt = $first->loaded_at->format('Y-m-d H:i:s.u');
        $lastEventId = SimulationEvent::max('id');
        $this->travelTo(CarbonImmutable::parse('2026-10-07 12:00:00.000003', 'UTC'));

        $hit = $this->access($admin, $scenario, $process, 0);

        $this->assertSame('PAGE_HIT', $hit['outcome']);
        $this->assertTrue($hit['completed']);
        $this->assertSame(0, $hit['physical_address']);
        $this->assertNull($hit['evicted']);
        $this->assertSame('2026-10-07 12:00:00.000001', $firstLoadedAt);
        $this->assertSame($firstLoadedAt, $first->fresh()->loaded_at->format('Y-m-d H:i:s.u'));
        $this->assertSame([SimulationEventType::PageRequest, SimulationEventType::PageHit], $this->eventTypesSince($scenario, $lastEventId));
        $this->travelTo(CarbonImmutable::parse('2026-10-07 12:00:00.000004', 'UTC'));
        $fault = $this->access($admin, $scenario, $process, 2);
        $this->assertSame($first->id, $fault['evicted']['page_id']);
        $this->assertSame(0, $fault['evicted']['page_number']);
        $this->assertNull($first->fresh()->frame_id);
        $this->assertNull($first->fresh()->loaded_at);
        $this->travelBack();
    }

    public static function fifoTimestamps(): array
    {
        return ['same timestamp' => ['2026-10-07 12:00:00.123456'], 'legacy null timestamps' => [null]];
    }

    #[DataProvider('fifoTimestamps')]
    public function test_fifo_breaks_timestamp_ties_by_page_id_instead_of_frame_number(?string $loadedAt): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin, ['ram_kb' => 2]);
        $process = $this->process($admin, $scenario, 3);
        $this->assign($scenario, $process, 0, 1, $loadedAt);
        $this->assign($scenario, $process, 1, 0, $loadedAt);
        $first = $process->pages()->where('page_number', 0)->firstOrFail();

        $result = $this->access($admin, $scenario, $process, 2);

        $this->assertSame($first->id, $result['evicted']['page_id']);
        $this->assertSame(1, $result['frame_number']);
        $this->assertSame(1024, $result['physical_address']);
        $this->assertNull($first->fresh()->loaded_at);
    }

    public function test_fifo_evicts_a_legacy_null_timestamp_before_a_timestamped_page(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin, ['ram_kb' => 2]);
        $process = $this->process($admin, $scenario, 3);
        $this->assign($scenario, $process, 0, 0, '2026-10-07 12:00:00.000001');
        $this->assign($scenario, $process, 1, 1, null);

        $result = $this->access($admin, $scenario, $process, 2);

        $this->assertSame(1, $result['evicted']['page_number']);
        $this->assertSame(1, $result['frame_number']);
        $this->assertTrue($process->pages()->where('page_number', 0)->firstOrFail()->present);
    }

    public function test_fifo_swap_succeeds_when_secondary_storage_is_full_and_keeps_both_capacities_consistent(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin, ['ram_kb' => 2, 'secondary_kb' => 2]);
        $first = $this->process($admin, $scenario, 2, 'Proceso residente');
        $this->travelTo(CarbonImmutable::parse('2026-10-07 12:00:00.000001', 'UTC'));
        $this->access($admin, $scenario, $first, 0);
        $this->travelTo(CarbonImmutable::parse('2026-10-07 12:00:00.000002', 'UTC'));
        $this->access($admin, $scenario, $first, 1);
        $second = $this->process($admin, $scenario, 2, 'Proceso en secundaria');
        $evictedPage = $first->pages()->where('page_number', 0)->firstOrFail();

        $result = $this->access($admin, $scenario, $second, 0);

        $this->assertEquals([
            'page_id' => $evictedPage->id, 'process_id' => $first->id,
            'process_name' => 'Proceso residente', 'page_number' => 0, 'frame_number' => 0,
        ], $result['evicted']);
        $this->assertNull($evictedPage->fresh()->frame_id);
        $this->assertNull($evictedPage->fresh()->loaded_at);
        $this->assertTrue($second->pages()->where('page_number', 0)->firstOrFail()->present);
        $snapshot = app(PagingService::class)->snapshot($admin, $scenario->id, $second->id);
        $this->assertSame(2, $snapshot['ram']['frames_used']);
        $this->assertSame(0, $snapshot['ram']['frames_free']);
        $this->assertSame(2048, $snapshot['secondary']['used_bytes']);
        $this->assertSame(0, $snapshot['secondary']['available_bytes']);
        $this->travelBack();
    }

    public function test_resolution_is_idempotent_even_after_the_completed_request_page_was_evicted(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin, ['ram_kb' => 1]);
        $process = $this->process($admin, $scenario, 2);
        $first = $this->access($admin, $scenario, $process, 0);
        $this->access($admin, $scenario, $process, 1);
        $before = $this->domainState();

        $again = app(PagingService::class)->resolveRequest($admin, $first['request_event_id']);

        $this->assertEquals($first, $again);
        $this->assertSame($before, $this->domainState());
        $this->assertFalse($process->pages()->where('page_number', 0)->firstOrFail()->present);
    }

    public function test_resolution_uses_current_residency_if_another_writer_loaded_the_page_after_inspection(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $pending = app(PagingService::class)->beginRequest($admin, $scenario->id, $process->id, 0);
        $operator = $this->user('operador');
        $loaded = $this->access($operator, $scenario, $process, 0);
        $page = $process->pages()->where('page_number', 0)->firstOrFail();
        $loadedAt = $page->loaded_at->format('Y-m-d H:i:s.u');
        $lastEventId = SimulationEvent::max('id');

        $result = app(PagingService::class)->resolveRequest($admin, $pending['request_event_id']);

        $this->assertSame('PAGE_HIT', $result['outcome']);
        $this->assertSame($loaded['frame_number'], $result['frame_number']);
        $this->assertSame($loadedAt, $page->fresh()->loaded_at->format('Y-m-d H:i:s.u'));
        $this->assertSame([SimulationEventType::PageHit], $this->eventTypesSince($scenario, $lastEventId));
    }

    public function test_a_request_cannot_be_resolved_by_another_authorized_writer(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $request = app(PagingService::class)->beginRequest($admin, $scenario->id, $process->id, 0);
        $before = $this->domainState();

        try {
            app(PagingService::class)->resolveRequest($this->user('operador'), $request['request_event_id']);
            $this->fail('Cada solicitud solo puede resolverse por su actor original.');
        } catch (AuthorizationException) {
            $this->assertSame($before, $this->domainState());
        }
    }

    public function test_revoked_permissions_cannot_resolve_a_pending_or_completed_request(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $operator = $this->user('operador');
        $completed = $this->access($operator, $scenario, $process, 0);
        $pending = app(PagingService::class)->beginRequest($operator, $scenario->id, $process->id, 1);
        $this->assertTrue($operator->can('pages.request'));
        $operator->fresh()->syncRoles(['observador']);
        $before = $this->domainState();

        foreach ([$pending['request_event_id'], $completed['request_event_id']] as $requestId) {
            try {
                app(PagingService::class)->resolveRequest($operator, $requestId);
                $this->fail('El permiso debe comprobarse incluso para resultados guardados.');
            } catch (AuthorizationException) {
                $this->assertSame($before, $this->domainState());
            }
        }
    }

    public function test_observer_cannot_execute_the_atomic_access_wrapper(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $before = $this->domainState();

        try {
            $this->access($this->user('observador'), $scenario, $process, 0);
            $this->fail('La consulta no permite ejecutar un acceso.');
        } catch (AuthorizationException) {
            $this->assertSame($before, $this->domainState());
        }
    }

    public static function malformedRequestMetadata(): array
    {
        return [
            'missing page ID' => [['page_id' => null]],
            'unknown page ID' => [['page_id' => 999999]],
            'changed page number' => [['page_number' => 1]],
            'string page number' => [['page_number' => '0']],
            'changed page size' => [['page_size_bytes' => 2048]],
        ];
    }

    #[DataProvider('malformedRequestMetadata')]
    public function test_malformed_request_metadata_does_not_allocate_or_record_a_resolution(array $changes): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $request = app(PagingService::class)->beginRequest($admin, $scenario->id, $process->id, 0);
        $event = SimulationEvent::findOrFail($request['request_event_id']);
        $event->update(['metadata' => array_replace($event->metadata, $changes)]);
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => app(PagingService::class)->resolveRequest($admin, $event->id), 'request_event_id');

        $this->assertSame($before, $this->domainState());
    }

    public function test_only_a_real_request_event_can_be_resolved_and_missing_events_are_rejected(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $wrongEvent = $process->events()->sole();
        $before = $this->domainState();
        $this->assertValidationFailure(fn () => app(PagingService::class)->resolveRequest($admin, $wrongEvent->id), 'request_event_id');

        try {
            app(PagingService::class)->resolveRequest($admin, 999999);
            $this->fail('Un evento inexistente debe rechazarse.');
        } catch (ModelNotFoundException) {
            $this->assertSame($before, $this->domainState());
        }
    }

    public function test_deleting_a_page_after_inspection_does_not_resolve_the_stale_request(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $request = app(PagingService::class)->beginRequest($admin, $scenario->id, $process->id, 0);
        $process->pages()->where('page_number', 0)->delete();
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => app(PagingService::class)->resolveRequest($admin, $request['request_event_id']), 'process_id');

        $this->assertSame($before, $this->domainState());
    }

    public function test_pending_request_is_revalidated_after_process_termination_or_scenario_completion(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $request = app(PagingService::class)->beginRequest($admin, $scenario->id, $process->id, 0);
        $process->update(['status' => ProcessStatus::Terminated]);
        $this->assertValidationFailure(fn () => app(PagingService::class)->resolveRequest($admin, $request['request_event_id']), 'process_id');
        $process->update(['status' => ProcessStatus::Ready]);
        $scenario->update(['status' => ScenarioStatus::Completed]);
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => app(PagingService::class)->resolveRequest($admin, $request['request_event_id']), 'scenario_id');

        $this->assertSame($before, $this->domainState());
        $this->assertDatabaseCount('simulation_events', 4);
    }

    public function test_atomic_wrapper_rolls_back_request_fault_allocation_and_states_when_loaded_event_fails(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $before = $this->domainState();
        $dispatcher = SimulationEvent::getEventDispatcher();
        SimulationEvent::setEventDispatcher(clone $dispatcher);
        SimulationEvent::creating(function (SimulationEvent $event): void {
            if ($event->type === SimulationEventType::PageLoaded) {
                throw new RuntimeException('Falló el evento final del acceso de prueba.');
            }
        });

        try {
            try {
                $this->access($admin, $scenario, $process, 0);
                $this->fail('El acceso completo debe revertirse si falta su evento final.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Falló el evento final del acceso de prueba.', $exception->getMessage());
            }
        } finally {
            SimulationEvent::setEventDispatcher($dispatcher);
        }

        $this->assertSame($before, $this->domainState());
    }

    public function test_livewire_resolves_fault_then_hit_and_refreshes_memory_without_an_observer_form(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $operator = $this->user('operador');
        $component = Livewire::actingAs($operator)->test(PageRequest::class, [
            'scenarioId' => $scenario->id, 'processId' => $process->id,
        ])->call('requestPage')->assertHasNoErrors()->assertDispatched('memory-updated')
            ->assertSet('result', fn ($result) => $result['outcome'] === 'PAGE_FAULT'
                && $result['completed'] === true && $result['physical_address'] === 0);

        $this->assertDatabaseCount('simulation_events', 6);
        $statistics = app(MemoryStatisticsService::class)->forScenario($scenario);
        $this->assertSame(1, $statistics['frames-used']);
        $this->assertSame(1, $statistics['page-faults']);
        $loadedAt = $process->pages()->where('page_number', 0)->firstOrFail()->loaded_at;
        $component->call('requestPage')->assertHasNoErrors()->assertDispatched('memory-updated')
            ->assertSet('result', fn ($result) => $result['outcome'] === 'PAGE_HIT' && $result['completed'] === true);
        $this->assertDatabaseCount('simulation_events', 8);
        $statistics = app(MemoryStatisticsService::class)->forScenario($scenario);
        $this->assertSame(1, $statistics['frames-used']);
        $this->assertSame(1, $statistics['page-faults']);
        $this->assertSame($loadedAt->format('Y-m-d H:i:s.u'), $process->pages()->where('page_number', 0)->firstOrFail()->loaded_at->format('Y-m-d H:i:s.u'));
        $observer = Livewire::actingAs($this->user('observador'))->test(PageRequest::class, [
            'scenarioId' => $scenario->id, 'processId' => $process->id,
        ])->assertViewHas('canRequest', false);
        $this->assertStringNotContainsString('wire:submit="requestPage"', $observer->html());
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

    private function process(User $actor, Scenario $scenario, int $size = 4, string $name = 'Proceso de prueba'): Process
    {
        return app(ProcessManagerService::class)->create($actor, $scenario->id, ['name' => $name, 'size_kb' => $size]);
    }

    private function assign(Scenario $scenario, Process $process, int $pageNumber, int $frameNumber, ?string $loadedAt): void
    {
        $frame = $scenario->frames()->where('frame_number', $frameNumber)->firstOrFail();
        $process->pages()->where('page_number', $pageNumber)->firstOrFail()->update([
            'frame_id' => $frame->id, 'loaded_at' => $loadedAt,
        ]);
    }

    private function access(User $actor, Scenario $scenario, Process $process, int $pageNumber): array
    {
        return app(PagingService::class)->requestPage($actor, $scenario->id, $process->id, $pageNumber);
    }

    private function eventTypesSince(Scenario $scenario, int $eventId): array
    {
        return $scenario->events()->where('id', '>', $eventId)->orderBy('id')->get()->pluck('type')->all();
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

        $this->fail('La operación debe rechazarse mediante validación.');
    }
}
