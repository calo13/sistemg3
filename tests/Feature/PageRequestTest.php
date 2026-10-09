<?php

namespace Tests\Feature;

use App\Enums\ProcessStatus;
use App\Enums\ScenarioStatus;
use App\Enums\SimulationEventType;
use App\Enums\SimulationMode;
use App\Livewire\PageRequest;
use App\Models\Process;
use App\Models\Scenario;
use App\Models\SimulationEvent;
use App\Models\User;
use App\Services\MemoryConfigurationService;
use App\Services\PagingService;
use App\Services\ProcessManagerService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class PageRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_records_the_selected_page_and_server_actor_without_resolving_the_access(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin, ['page_kb' => 2]);
        $process = $this->process($admin, $scenario, 3);
        $operator = $this->user('operador');
        $page = $process->pages()->where('page_number', 1)->firstOrFail();
        $before = $this->domainState(false);
        $previousEvents = $scenario->events()->orderBy('id')->pluck('id')->all();

        $result = $this->request($operator, $scenario, $process, 1);

        $event = SimulationEvent::findOrFail($result['request_event_id']);
        $this->assertSame([
            'request_event_id' => $event->id, 'scenario_id' => $scenario->id,
            'process_id' => $process->id, 'page_number' => 1, 'frame_number' => null,
            'present' => false, 'page_size_bytes' => 2048,
        ], $result);
        $this->assertSame(SimulationEventType::PageRequest, $event->type);
        $this->assertSame($operator->id, $event->user_id);
        $this->assertSame($scenario->id, $event->scenario_id);
        $this->assertSame($process->id, $event->process_id);
        $this->assertEquals([
            'page_number' => 1, 'page_id' => $page->id, 'page_size_bytes' => 2048,
            'process_name' => $process->name, 'frame_number' => null,
        ], $event->metadata);
        $this->assertSame($previousEvents, $scenario->events()->orderBy('id')->limit(3)->pluck('id')->all());
        $this->assertDatabaseCount('simulation_events', 4);
        $this->assertSame($before, $this->domainState(false));
        $snapshot = app(PagingService::class)->snapshot($operator, $scenario->id, $process->id);
        $this->assertSame($event->id, $snapshot['last_request']['event_id']);
        $this->assertSame(0, $snapshot['ram']['frames_used']);
        $this->assertSame(0, $scenario->events()->whereIn('type', [SimulationEventType::PageHit, SimulationEventType::PageFault, SimulationEventType::PageLoaded])->count());
    }

    public function test_request_of_a_resident_page_reports_its_frame_without_logging_a_hit_or_changing_state(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $scenario->update(['status' => ScenarioStatus::Running]);
        $process = $this->process($admin, $scenario);
        $process->update(['status' => ProcessStatus::Waiting]);
        $frame = $scenario->frames()->where('frame_number', 4)->firstOrFail();
        $process->pages()->where('page_number', 0)->firstOrFail()->update(['frame_id' => $frame->id]);
        $before = $this->domainState(false);

        $result = $this->request($admin, $scenario, $process, 0);

        $this->assertTrue($result['present']);
        $this->assertSame(4, $result['frame_number']);
        $this->assertSame(4, SimulationEvent::findOrFail($result['request_event_id'])->metadata['frame_number']);
        $this->assertSame($before, $this->domainState(false));
        $this->assertSame(1, $scenario->events()->where('type', SimulationEventType::PageRequest)->count());
        $this->assertSame(0, $scenario->events()->where('type', SimulationEventType::PageHit)->count());
    }

    public static function writerRoles(): array
    {
        return ['administrator' => ['administrador'], 'operator' => ['operador']];
    }

    #[DataProvider('writerRoles')]
    public function test_both_writer_roles_can_register_a_request(string $role): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $actor = $this->user($role);

        $result = $this->request($actor, $scenario, $process);

        $this->assertSame($actor->id, SimulationEvent::findOrFail($result['request_event_id'])->user_id);
        $this->assertDatabaseCount('simulation_events', 4);
    }

    public function test_observer_cannot_register_a_request_through_the_service(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $before = $this->domainState();

        try {
            $this->request($this->user('observador'), $scenario, $process);
            $this->fail('La consulta de memoria no autoriza solicitudes de CPU.');
        } catch (AuthorizationException) {
            $this->assertSame($before, $this->domainState());
        }
    }

    public static function missingWriterPermissions(): array
    {
        return ['request permission' => ['pages.request'], 'execute permission' => ['simulations.execute']];
    }

    #[DataProvider('missingWriterPermissions')]
    public function test_each_writer_permission_is_required(string $missing): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $actor = User::factory()->create();
        $actor->givePermissionTo(array_values(array_diff([
            'memory.view', 'tables.view', 'simulations.view', 'pages.request', 'simulations.execute',
        ], [$missing])));
        $before = $this->domainState();

        try {
            $this->request($actor, $scenario, $process);
            $this->fail('Ambos permisos de operación son obligatorios.');
        } catch (AuthorizationException) {
            $this->assertSame($before, $this->domainState());
        }
    }

    public function test_service_rechecks_permissions_after_the_operator_has_been_revoked(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $operator = $this->user('operador');
        $this->assertTrue($operator->can('pages.request'));
        $operator->fresh()->syncRoles(['observador']);
        $before = $this->domainState();

        try {
            $this->request($operator, $scenario, $process);
            $this->fail('El actor revocado no conserva la autorización.');
        } catch (AuthorizationException) {
            $this->assertSame($before, $this->domainState());
        }
    }

    public static function invalidPageNumbers(): array
    {
        return ['negative page' => [-1], 'absent process page' => [99], 'page above limit' => [1024]];
    }

    #[DataProvider('invalidPageNumbers')]
    public function test_invalid_page_numbers_do_not_record_a_request(int $pageNumber): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => $this->request($admin, $scenario, $process, $pageNumber), 'page_number');

        $this->assertSame($before, $this->domainState());
    }

    public function test_foreign_and_missing_processes_do_not_fall_back_to_a_local_process(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $this->process($admin, $scenario);
        $other = $this->memory($admin, ['name' => 'Otra memoria']);
        $foreign = $this->process($admin, $other);
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => $this->request($admin, $scenario, $foreign), 'process_id');
        $this->assertValidationFailure(fn () => app(PagingService::class)->beginRequest($admin, $scenario->id, 999999, 0), 'process_id');

        $this->assertSame($before, $this->domainState());
    }

    public function test_terminated_process_cannot_receive_a_request(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $process->update(['status' => ProcessStatus::Terminated]);
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => $this->request($admin, $scenario, $process), 'process_id');

        $this->assertSame($before, $this->domainState());
    }

    public static function invalidScenarioStates(): array
    {
        return ['draft' => [ScenarioStatus::Draft], 'completed' => [ScenarioStatus::Completed]];
    }

    #[DataProvider('invalidScenarioStates')]
    public function test_requests_require_a_ready_or_running_scenario(ScenarioStatus $status): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $scenario->update(['status' => $status]);
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => $this->request($admin, $scenario, $process), 'scenario_id');

        $this->assertSame($before, $this->domainState());
    }

    public function test_requests_reject_other_memory_modes_and_inconsistent_frames(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $scenario->update(['mode' => SimulationMode::Segmentation]);
        $this->assertValidationFailure(fn () => $this->request($admin, $scenario, $process), 'scenario_id');
        $scenario->update(['mode' => SimulationMode::Paging]);
        $scenario->frames()->where('frame_number', 0)->firstOrFail()->update(['frame_number' => 99]);
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => $this->request($admin, $scenario, $process), 'scenario_id');

        $this->assertSame($before, $this->domainState());
        $this->assertDatabaseCount('simulation_events', 3);
    }

    public function test_unconfigured_scenario_and_missing_scenario_cannot_record_a_request(): void
    {
        $admin = $this->user('administrador');
        $scenario = Scenario::create(['name' => 'Sin configuración', 'status' => ScenarioStatus::Ready]);
        $process = Process::create(['scenario_id' => $scenario->id, 'name' => 'Sin páginas', 'size_bytes' => 1024]);
        $before = $this->domainState();
        $this->assertValidationFailure(fn () => $this->request($admin, $scenario, $process), 'scenario_id');

        try {
            app(PagingService::class)->beginRequest($admin, 999999, $process->id, 0);
            $this->fail('Un escenario inexistente debe rechazarse.');
        } catch (ModelNotFoundException) {
            $this->assertSame($before, $this->domainState());
        }
    }

    public static function inconsistentPages(): array
    {
        return ['missing page' => ['missing'], 'noncontiguous page number' => ['gap']];
    }

    #[DataProvider('inconsistentPages')]
    public function test_inconsistent_process_pages_cannot_record_a_request(string $inconsistency): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario, 3);
        $lastPage = $process->pages()->where('page_number', 2)->firstOrFail();
        if ($inconsistency === 'missing') {
            $lastPage->delete();
        } else {
            $lastPage->update(['page_number' => 99]);
        }
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => $this->request($admin, $scenario, $process), 'process_id');

        $this->assertSame($before, $this->domainState());
    }

    public function test_repeated_requests_record_distinct_events_without_allocating_a_frame(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $before = $this->domainState(false);

        $first = $this->request($admin, $scenario, $process);
        $second = $this->request($admin, $scenario, $process);

        $this->assertNotSame($first['request_event_id'], $second['request_event_id']);
        $this->assertSame(2, $scenario->events()->where('type', SimulationEventType::PageRequest)->count());
        $this->assertSame($before, $this->domainState(false));
    }

    public function test_event_failure_keeps_the_history_and_memory_unchanged(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $before = $this->domainState();
        $dispatcher = SimulationEvent::getEventDispatcher();
        SimulationEvent::setEventDispatcher(clone $dispatcher);
        SimulationEvent::creating(function (SimulationEvent $event): void {
            if ($event->type === SimulationEventType::PageRequest) {
                throw new RuntimeException('Falló el registro de la solicitud de prueba.');
            }
        });

        try {
            try {
                $this->request($admin, $scenario, $process);
                $this->fail('Una solicitud sin evento no debe completarse.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Falló el registro de la solicitud de prueba.', $exception->getMessage());
            }
        } finally {
            SimulationEvent::setEventDispatcher($dispatcher);
        }

        $this->assertSame($before, $this->domainState());
    }

    #[DataProvider('writerRoles')]
    public function test_livewire_writer_can_request_a_page_and_dispatch_the_memory_refresh(string $role): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $component = Livewire::actingAs($this->user($role))->test(PageRequest::class, [
            'scenarioId' => $scenario->id, 'processId' => $process->id,
        ])->assertViewHas('canRequest', true)
            ->set('pageNumber', 1)->call('requestPage')->assertHasNoErrors()
            ->assertDispatched('memory-updated')
            ->assertSet('result', fn ($result) => $result['process_id'] === $process->id
                && $result['page_number'] === 1 && $result['present'] === true && $result['frame_number'] === 0
                && $result['outcome'] === 'PAGE_FAULT' && $result['completed'] === true);

        $this->assertStringContainsString('wire:submit="requestPage"', $component->html());
        $component->assertSee('CPU solicita P1')->assertSee('Acceso completado');
        $this->assertDatabaseHas('pages', ['process_id' => $process->id, 'page_number' => 1, 'frame_id' => $scenario->frames()->where('frame_number', 0)->value('id')]);
        $this->assertDatabaseCount('simulation_events', 6);
    }

    public function test_livewire_observer_sees_no_request_form_and_cannot_forge_a_request_action(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $before = $this->domainState();
        $component = Livewire::actingAs($this->user('observador'))->test(PageRequest::class, [
            'scenarioId' => $scenario->id, 'processId' => $process->id,
        ])->assertViewHas('canRequest', false);

        $this->assertStringNotContainsString('wire:submit="requestPage"', $component->html());
        $component->call('requestPage')->assertForbidden();
        $this->assertSame($before, $this->domainState());
    }

    public function test_livewire_rechecks_the_operator_role_before_recording_a_request(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $operator = $this->user('operador');
        $component = Livewire::actingAs($operator)->test(PageRequest::class, [
            'scenarioId' => $scenario->id, 'processId' => $process->id,
        ]);
        $operator->fresh()->syncRoles(['observador']);

        $component->call('requestPage')->assertForbidden();

        $this->assertDatabaseCount('simulation_events', 3);
    }

    public static function invalidFormPageNumbers(): array
    {
        return ['negative' => [-1], 'fractional' => [1.5], 'non numeric' => ['invalid'], 'too high' => [1024], 'absent page' => [99]];
    }

    #[DataProvider('invalidFormPageNumbers')]
    public function test_livewire_invalid_page_numbers_are_reported_without_recording_a_request(mixed $number): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $before = $this->domainState();

        Livewire::actingAs($admin)->test(PageRequest::class, ['scenarioId' => $scenario->id, 'processId' => $process->id])
            ->set('pageNumber', $number)->call('requestPage')->assertHasErrors(['pageNumber']);

        $this->assertSame($before, $this->domainState());
    }

    public function test_livewire_foreign_process_and_missing_context_use_the_form_errors(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $this->process($admin, $scenario);
        $other = $this->memory($admin, ['name' => 'Otra memoria']);
        $foreign = $this->process($admin, $other);
        $before = $this->domainState();

        Livewire::actingAs($admin)->test(PageRequest::class, ['scenarioId' => $scenario->id, 'processId' => $foreign->id])
            ->call('requestPage')->assertHasErrors(['processId']);
        Livewire::test(PageRequest::class)->call('requestPage')->assertHasErrors(['scenarioId', 'processId']);

        $this->assertSame($before, $this->domainState());
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

    private function process(User $actor, Scenario $scenario, int $size = 4): Process
    {
        return app(ProcessManagerService::class)->create($actor, $scenario->id, ['name' => 'Proceso de prueba', 'size_kb' => $size]);
    }

    private function request(User $actor, Scenario $scenario, Process $process, int $pageNumber = 0): array
    {
        return app(PagingService::class)->beginRequest($actor, $scenario->id, $process->id, $pageNumber);
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

        $this->fail('La operación debe rechazarse mediante validación.');
    }
}
