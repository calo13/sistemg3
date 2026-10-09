<?php

namespace Tests\Feature;

use App\Livewire\PageRequest;
use App\Models\Process;
use App\Models\Scenario;
use App\Models\User;
use App\Services\MemoryConfigurationService;
use App\Services\PagingService;
use App\Services\ProcessManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PagingStepModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_fault_steps_one_through_five_only_inspect_then_step_six_commits_and_eight_finishes(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $component = $this->requester($this->user('operador'), $scenario, $process)
            ->set('mode', 'step')->call('requestPage')->assertHasNoErrors()
            ->assertSet('result', null)->assertSet('step', 1)
            ->assertSet('pending', fn ($pending) => $pending['page_number'] === 0 && $pending['present'] === false)
            ->assertViewHas('flowSteps', fn ($steps) => count($steps) === 8)
            ->assertViewHas('currentStep', 1)->assertDispatched('memory-updated')
            ->assertNotDispatched('memory-accessed');
        $pendingState = $this->domainState();

        for ($step = 2; $step <= 5; $step++) {
            $component->call('nextStep')->assertHasNoErrors()->assertSet('step', $step)
                ->assertSet('result', null)->assertViewHas('currentStep', $step);
            $this->assertSame($pendingState, $this->domainState());
        }
        $this->assertDatabaseCount('simulation_events', 4);
        $this->assertSame(0, $scenario->pages()->whereNotNull('frame_id')->count());

        $component->call('nextStep')->assertHasNoErrors()->assertSet('step', 6)
            ->assertSet('result', fn ($result) => $result['outcome'] === 'PAGE_FAULT' && $result['completed'] === true)
            ->assertDispatched('memory-updated')->assertDispatched('memory-accessed');
        $this->assertDatabaseCount('simulation_events', 6);
        $this->assertSame(1, $scenario->pages()->whereNotNull('frame_id')->count());
        $resolvedState = $this->domainState();

        $component->call('nextStep')->assertSet('step', 7)->call('nextStep')->assertSet('step', 8)
            ->assertViewHas('currentStep', 8)->call('nextStep')->assertSet('step', 8);

        $this->assertSame($resolvedState, $this->domainState());
    }

    public function test_hit_resolves_on_step_four_and_keeps_the_original_loaded_timestamp(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $frame = $scenario->frames()->where('frame_number', 4)->firstOrFail();
        $page = $process->pages()->where('page_number', 0)->firstOrFail();
        $page->update(['frame_id' => $frame->id, 'loaded_at' => '2026-10-07 12:00:00.123456']);
        $component = $this->requester($admin, $scenario, $process)
            ->set('mode', 'step')->call('requestPage')->assertSet('step', 1)
            ->assertViewHas('flowSteps', fn ($steps) => count($steps) === 4);
        $beforeResolution = $this->domainState();

        $component->call('nextStep')->assertSet('step', 2)->assertSet('result', null);
        $component->call('nextStep')->assertSet('step', 3)->assertSet('result', null);
        $this->assertSame($beforeResolution, $this->domainState());
        $component->call('nextStep')->assertSet('step', 4)
            ->assertSet('result', fn ($result) => $result['outcome'] === 'PAGE_HIT'
                && $result['frame_number'] === 4 && $result['completed'] === true)
            ->assertDispatched('memory-accessed');

        $this->assertDatabaseCount('simulation_events', 5);
        $this->assertSame('2026-10-07 12:00:00.123456', $page->fresh()->loaded_at->format('Y-m-d H:i:s.u'));
        $finished = $this->domainState();
        $component->call('nextStep')->assertSet('step', 4);
        $this->assertSame($finished, $this->domainState());
    }

    public function test_default_automatic_mode_resolves_the_access_and_shows_the_complete_flow(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);

        $this->requester($admin, $scenario, $process)->assertSet('mode', 'automatic')
            ->call('requestPage')->assertHasNoErrors()
            ->assertSet('result', fn ($result) => $result['completed'] === true && $result['outcome'] === 'PAGE_FAULT')
            ->assertViewHas('flowSteps', fn ($steps) => count($steps) === 8)
            ->assertViewHas('currentStep', 8)->assertDispatched('memory-accessed');

        $this->assertSame(1, $scenario->pages()->whereNotNull('frame_id')->count());
        $this->assertDatabaseCount('simulation_events', 6);
    }

    public function test_revoked_operator_cannot_advance_a_pending_request(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $operator = $this->user('operador');
        $component = $this->requester($operator, $scenario, $process)->set('mode', 'step')->call('requestPage');
        $operator->fresh()->syncRoles(['observador']);
        $before = $this->domainState();

        $component->call('nextStep')->assertForbidden();

        $this->assertSame($before, $this->domainState());
    }

    public static function lockedFlowProperties(): array
    {
        return [
            'step' => ['step', 6],
            'state change marker' => ['stateChanged', true],
            'pending request' => ['pending', ['request_event_id' => 999999]],
            'result' => ['result', ['completed' => true, 'outcome' => 'PAGE_HIT']],
        ];
    }

    #[DataProvider('lockedFlowProperties')]
    public function test_client_cannot_forge_flow_state_or_skip_to_a_resolution(string $property, mixed $value): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $component = $this->requester($admin, $scenario, $process)->set('mode', 'step')->call('requestPage');
        $before = $this->domainState();

        try {
            $component->set($property, $value);
            $this->fail('El cliente no puede modificar propiedades de resolución bloqueadas.');
        } catch (CannotUpdateLockedPropertyException) {
            $this->assertSame($before, $this->domainState());
        }
    }

    public function test_invalid_mode_is_rejected_without_recording_a_request(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $before = $this->domainState();

        $this->requester($admin, $scenario, $process)->set('mode', 'invalid')
            ->call('requestPage')->assertHasErrors(['mode']);

        $this->assertSame($before, $this->domainState());
    }

    public function test_changing_page_cancels_the_ui_flow_without_deleting_history_or_allocating_memory(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $component = $this->requester($admin, $scenario, $process)->set('mode', 'step')->call('requestPage');
        $before = $this->domainState();

        $component->set('pageNumber', 1)->assertSet('pending', null)->assertSet('result', null)->assertSet('step', 0)
            ->call('nextStep');

        $this->assertSame($before, $this->domainState());
        $this->assertDatabaseCount('simulation_events', 4);
        $this->assertSame(0, $scenario->pages()->whereNotNull('frame_id')->count());
    }

    public function test_changing_mode_cancels_a_pending_flow_and_keeps_the_inspection_event(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $component = $this->requester($admin, $scenario, $process)->set('mode', 'step')->call('requestPage');
        $before = $this->domainState();

        $component->set('mode', 'automatic')->assertSet('pending', null)->assertSet('result', null)->assertSet('step', 0);

        $this->assertSame($before, $this->domainState());
    }

    public function test_flow_becomes_a_four_step_hit_when_another_writer_loads_the_page_before_resolution(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $component = $this->requester($admin, $scenario, $process)->set('mode', 'step')->call('requestPage');
        app(PagingService::class)->requestPage($this->user('operador'), $scenario->id, $process->id, 0);
        $page = $process->pages()->where('page_number', 0)->firstOrFail();
        $loadedAt = $page->loaded_at->format('Y-m-d H:i:s.u');

        $this->advanceUntilResolved($component);
        $component->assertSet('result', fn ($result) => $result['outcome'] === 'PAGE_HIT')
            ->assertViewHas('flowSteps', fn ($steps) => count($steps) === 4)->assertViewHas('currentStep', 4);

        $this->assertSame($loadedAt, $page->fresh()->loaded_at->format('Y-m-d H:i:s.u'));
        $this->assertDatabaseCount('simulation_events', 8);
    }

    public function test_flow_becomes_an_eight_step_fault_when_another_writer_evicts_the_inspected_page(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin, ['ram_kb' => 1]);
        $process = $this->process($admin, $scenario);
        app(PagingService::class)->requestPage($admin, $scenario->id, $process->id, 0);
        $component = $this->requester($admin, $scenario, $process)->set('mode', 'step')->call('requestPage')
            ->assertViewHas('flowSteps', fn ($steps) => count($steps) === 4);
        app(PagingService::class)->requestPage($this->user('operador'), $scenario->id, $process->id, 1);

        $this->advanceUntilResolved($component);
        $component->assertSet('result', fn ($result) => $result['outcome'] === 'PAGE_FAULT')
            ->assertViewHas('flowSteps', fn ($steps) => count($steps) === 8);
        for ($attempt = 0; $attempt < 8 && $component->get('step') < 8; $attempt++) {
            $component->call('nextStep');
        }
        $component->assertViewHas('currentStep', 8);
        $this->assertTrue($process->pages()->where('page_number', 0)->firstOrFail()->present);
        $this->assertFalse($process->pages()->where('page_number', 1)->firstOrFail()->present);
        $this->assertDatabaseCount('simulation_events', 12);
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

    private function process(User $actor, Scenario $scenario): Process
    {
        return app(ProcessManagerService::class)->create($actor, $scenario->id, ['name' => 'Proceso de prueba', 'size_kb' => 2]);
    }

    private function requester(User $actor, Scenario $scenario, Process $process): Testable
    {
        return Livewire::actingAs($actor)->test(PageRequest::class, ['scenarioId' => $scenario->id, 'processId' => $process->id]);
    }

    private function advanceUntilResolved(Testable $component): void
    {
        for ($attempt = 0; $attempt < 8 && $component->get('result') === null; $attempt++) {
            $component->call('nextStep')->assertHasNoErrors();
        }
        $this->assertNotNull($component->get('result'));
    }

    private function domainState(): array
    {
        $state = [];
        foreach (['scenarios', 'memory_configurations', 'memory_frames', 'processes', 'pages', 'segments', 'simulation_events'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }

        return $state;
    }
}
