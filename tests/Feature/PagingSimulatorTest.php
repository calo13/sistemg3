<?php

namespace Tests\Feature;

use App\Enums\ProcessStatus;
use App\Enums\ScenarioStatus;
use App\Enums\SimulationEventType;
use App\Enums\SimulationMode;
use App\Livewire\PagingSimulator;
use App\Models\Page;
use App\Models\Process;
use App\Models\Scenario;
use App\Models\SimulationEvent;
use App\Models\User;
use App\Services\ActiveScenarioService;
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
use Tests\TestCase;

class PagingSimulatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_has_no_implicit_process_selection_and_counts_simulated_memory(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin, ['page_kb' => 2]);
        $first = $this->process($admin, $scenario, ['name' => 'Editor', 'size_kb' => 3]);
        $second = $this->process($admin, $scenario, ['name' => 'Navegador', 'size_kb' => 5]);
        $this->event($scenario, $first);

        $snapshot = $this->snapshot($this->user('observador'), $scenario);

        $this->assertTrue($snapshot['scenario']->is($scenario));
        $this->assertTrue($snapshot['configuration']->is($scenario->configuration));
        $this->assertSame([$first->id, $second->id], $snapshot['processes']->pluck('id')->all());
        $this->assertSame([2, 3], $snapshot['processes']->pluck('pages_count')->all());
        $this->assertSame([0, 0], $snapshot['processes']->pluck('resident_pages_count')->all());
        $this->assertNull($snapshot['selected_process']);
        $this->assertTrue($snapshot['pages']->isEmpty());
        $this->assertNull($snapshot['last_request']);
        $this->assertSame([
            'total_bytes' => 16384, 'used_bytes' => 0, 'available_bytes' => 16384,
            'frames_total' => 8, 'frames_used' => 0, 'frames_free' => 8, 'selected_frames_used' => 0,
        ], $snapshot['ram']);
        $this->assertSame([
            'total_bytes' => 65536, 'used_bytes' => 10240, 'available_bytes' => 55296,
            'selected_pages' => 0, 'selected_bytes' => 0,
        ], $snapshot['secondary']);
    }

    public function test_selected_process_pages_are_ordered_and_do_not_include_another_process_or_scenario(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin, ['page_kb' => 2]);
        $selected = $this->process($admin, $scenario, ['name' => 'Seleccionado', 'size_kb' => 3]);
        $otherProcess = $this->process($admin, $scenario, ['name' => 'Otro proceso', 'size_kb' => 5]);
        $otherScenario = $this->memory($admin, ['name' => 'Otra memoria']);
        $foreign = $this->process($admin, $otherScenario, ['name' => 'Proceso externo', 'size_kb' => 8]);

        $snapshot = $this->snapshot($this->user('observador'), $scenario, $selected);

        $this->assertTrue($snapshot['selected_process']->is($selected));
        $this->assertSame([0, 1], $snapshot['pages']->pluck('page_number')->all());
        $this->assertSame([$selected->id], $snapshot['pages']->pluck('process_id')->unique()->values()->all());
        $this->assertSame([$scenario->id], $snapshot['pages']->pluck('scenario_id')->unique()->values()->all());
        $this->assertTrue($snapshot['pages']->every(fn ($page) => ! $page->present && $page->relationLoaded('frame') && $page->frame === null));
        $this->assertSame(2, $snapshot['secondary']['selected_pages']);
        $this->assertSame(4096, $snapshot['secondary']['selected_bytes']);
        $this->assertSame(10240, $snapshot['secondary']['used_bytes']);
        $this->assertTrue($snapshot['processes']->contains('id', $otherProcess->id));
        $this->assertFalse($snapshot['processes']->contains('id', $foreign->id));
        $this->assertNull($snapshot['last_request']);
    }

    public function test_snapshot_reads_existing_ram_assignments_and_keeps_terminated_pages_in_secondary(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin, ['page_kb' => 2]);
        $selected = $this->process($admin, $scenario, ['name' => 'En memoria', 'size_kb' => 3]);
        $terminated = $this->process($admin, $scenario, ['name' => 'Finalizado', 'size_kb' => 5]);
        $terminated->update(['status' => ProcessStatus::Terminated]);
        $frame = $scenario->frames()->where('frame_number', 3)->firstOrFail();
        $selected->pages()->where('page_number', 0)->firstOrFail()->update(['frame_id' => $frame->id]);

        $snapshot = $this->snapshot($this->user('observador'), $scenario, $selected);

        $this->assertSame([
            'total_bytes' => 16384, 'used_bytes' => 2048, 'available_bytes' => 14336,
            'frames_total' => 8, 'frames_used' => 1, 'frames_free' => 7, 'selected_frames_used' => 1,
        ], $snapshot['ram']);
        $this->assertSame([
            'total_bytes' => 65536, 'used_bytes' => 8192, 'available_bytes' => 57344,
            'selected_pages' => 1, 'selected_bytes' => 2048,
        ], $snapshot['secondary']);
        $this->assertTrue($snapshot['pages']->first()->present);
        $this->assertTrue($snapshot['pages']->first()->frame->is($frame));
        $this->assertFalse($snapshot['pages']->last()->present);
        $this->assertSame(1, $snapshot['processes']->firstWhere('id', $selected->id)->resident_pages_count);
        $this->assertSame(ProcessStatus::Terminated, $snapshot['processes']->firstWhere('id', $terminated->id)->status);
    }

    public function test_repeated_snapshots_do_not_write_pages_frames_processes_or_events(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $observer = $this->user('observador');
        $before = $this->domainState();

        $this->snapshot($observer, $scenario);
        $this->snapshot($observer, $scenario, $process);
        $this->snapshot($observer, $scenario, $process);

        $this->assertSame($before, $this->domainState());
        $this->assertDatabaseCount('simulation_events', 3);
    }

    public static function readerRoles(): array
    {
        return [
            'administrator' => ['administrador'],
            'operator' => ['operador'],
            'observer' => ['observador'],
        ];
    }

    #[DataProvider('readerRoles')]
    public function test_all_three_roles_can_read_a_snapshot(string $role): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);

        $snapshot = $this->snapshot($this->user($role), $scenario, $process);

        $this->assertTrue($snapshot['selected_process']->is($process));
        $this->assertCount(4, $snapshot['pages']);
    }

    public static function missingReadPermissions(): array
    {
        return [
            'memory permission' => ['memory.view'],
            'tables permission' => ['tables.view'],
            'simulations permission' => ['simulations.view'],
        ];
    }

    #[DataProvider('missingReadPermissions')]
    public function test_partial_read_permissions_do_not_authorize_a_snapshot(string $missing): void
    {
        $scenario = $this->memory($this->user('administrador'));
        $actor = User::factory()->create();
        $actor->givePermissionTo(array_values(array_diff(['memory.view', 'tables.view', 'simulations.view'], [$missing])));
        $before = $this->domainState();

        try {
            $this->snapshot($actor, $scenario);
            $this->fail('Los tres permisos de consulta son obligatorios.');
        } catch (AuthorizationException) {
            $this->assertSame($before, $this->domainState());
        }
    }

    public function test_snapshot_rechecks_an_actors_permissions_after_the_role_is_revoked(): void
    {
        $scenario = $this->memory($this->user('administrador'));
        $observer = $this->user('observador');
        $this->assertTrue($observer->can('memory.view'));
        $this->assertTrue($observer->can('tables.view'));
        $this->assertTrue($observer->can('simulations.view'));
        $observer->fresh()->syncRoles([]);

        try {
            $this->snapshot($observer, $scenario);
            $this->fail('Una instancia anterior del usuario no conserva sus permisos.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('simulation_events', 2);
        }
    }

    public function test_cross_scenario_and_missing_process_selections_are_rejected_without_fallback(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $this->process($admin, $scenario);
        $other = $this->memory($admin, ['name' => 'Otra memoria']);
        $foreign = $this->process($admin, $other);
        $observer = $this->user('observador');
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => app(PagingService::class)->snapshot($observer, $scenario->id, $foreign->id), 'process_id');
        $this->assertValidationFailure(fn () => app(PagingService::class)->snapshot($observer, $scenario->id, 999999), 'process_id');

        $this->assertSame($before, $this->domainState());
    }

    public function test_missing_scenario_does_not_fall_back_to_an_existing_scenario(): void
    {
        $scenario = $this->memory($this->user('administrador'));

        try {
            app(PagingService::class)->snapshot($this->user('observador'), $scenario->id + 1000);
            $this->fail('Un escenario inexistente debe rechazarse.');
        } catch (ModelNotFoundException) {
            $this->assertDatabaseCount('scenarios', 1);
            $this->assertDatabaseCount('simulation_events', 2);
        }
    }

    public static function incompatibleModes(): array
    {
        return [
            'segmentation' => [SimulationMode::Segmentation],
            'contiguous allocation' => [SimulationMode::Contiguous],
        ];
    }

    #[DataProvider('incompatibleModes')]
    public function test_other_simulation_modes_do_not_produce_a_paging_snapshot(SimulationMode $mode): void
    {
        $scenario = $this->memory($this->user('administrador'));
        $scenario->update(['mode' => $mode]);

        $this->assertValidationFailure(fn () => $this->snapshot($this->user('observador'), $scenario), 'scenario_id');

        $this->assertSame($mode, $scenario->fresh()->mode);
        $this->assertDatabaseCount('simulation_events', 2);
    }

    public function test_unconfigured_scenario_does_not_display_fabricated_memory_counts(): void
    {
        $scenario = Scenario::create(['name' => 'Sin memoria']);

        $this->assertValidationFailure(fn () => $this->snapshot($this->user('observador'), $scenario), 'scenario_id');

        $this->assertDatabaseCount('memory_frames', 0);
        $this->assertDatabaseCount('simulation_events', 0);
    }

    public function test_completed_scenarios_can_still_be_read_without_changing_their_state(): void
    {
        $scenario = $this->memory($this->user('administrador'));
        $scenario->update(['status' => ScenarioStatus::Completed]);

        $snapshot = $this->snapshot($this->user('observador'), $scenario);

        $this->assertSame(ScenarioStatus::Completed, $snapshot['scenario']->status);
        $this->assertSame(ScenarioStatus::Completed, $scenario->fresh()->status);
        $this->assertDatabaseCount('simulation_events', 2);
    }

    public static function inconsistentFrames(): array
    {
        return ['missing frame' => ['missing'], 'noncontiguous frame' => ['gap']];
    }

    #[DataProvider('inconsistentFrames')]
    public function test_inconsistent_frames_are_rejected_without_repairing_memory(string $inconsistency): void
    {
        $scenario = $this->memory($this->user('administrador'));
        $frame = $scenario->frames()->where('frame_number', 0)->firstOrFail();
        if ($inconsistency === 'missing') {
            $frame->delete();
        } else {
            $frame->update(['frame_number' => 99]);
        }
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => $this->snapshot($this->user('observador'), $scenario), 'scenario_id');

        $this->assertSame($before, $this->domainState());
    }

    public function test_secondary_storage_over_capacity_is_rejected_without_mutating_pages(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario, ['size_kb' => 2]);
        $scenario->configuration->update(['secondary_storage_bytes' => 1024]);
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => $this->snapshot($this->user('observador'), $scenario, $process), 'scenario_id');

        $this->assertSame($before, $this->domainState());
    }

    public function test_processes_above_the_read_limit_are_rejected_without_loading_a_fallback_context(): void
    {
        $scenario = $this->memory($this->user('administrador'));
        $records = [];
        for ($number = 0; $number < 101; $number++) {
            $records[] = [
                'scenario_id' => $scenario->id, 'name' => 'Proceso '.$number,
                'size_bytes' => 1024, 'status' => ProcessStatus::Ready->value,
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        Process::insert($records);
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => $this->snapshot($this->user('observador'), $scenario), 'scenario_id');

        $this->assertSame($before, $this->domainState());
    }

    public function test_selected_process_pages_above_the_read_limit_are_rejected_without_mutation(): void
    {
        $scenario = $this->memory($this->user('administrador'), ['secondary_kb' => 2048]);
        $process = Process::create([
            'scenario_id' => $scenario->id, 'name' => 'Proceso fuera del límite',
            'size_bytes' => 1025 * 1024, 'status' => ProcessStatus::Ready,
        ]);
        $records = [];
        for ($number = 0; $number < 1025; $number++) {
            $records[] = [
                'scenario_id' => $scenario->id, 'process_id' => $process->id,
                'page_number' => $number, 'frame_id' => null,
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        Page::insert($records);
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => $this->snapshot($this->user('observador'), $scenario, $process), 'scenario_id');

        $this->assertSame($before, $this->domainState());
    }

    public function test_a_selected_process_at_the_page_limit_is_readable(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin, ['ram_kb' => 64, 'page_kb' => 64, 'secondary_kb' => 65536]);
        $process = $this->process($admin, $scenario, ['size_kb' => 65536]);

        $snapshot = $this->snapshot($this->user('observador'), $scenario, $process);

        $this->assertCount(1024, $snapshot['pages']);
        $this->assertSame(1023, $snapshot['pages']->last()->page_number);
        $this->assertSame(1024, $snapshot['secondary']['selected_pages']);
        $this->assertSame(67108864, $snapshot['secondary']['selected_bytes']);
        $this->assertSame(0, $snapshot['secondary']['available_bytes']);
        $this->assertSame(0, $snapshot['ram']['used_bytes']);
    }

    public function test_cpu_uses_the_latest_request_for_the_selected_process_with_id_as_a_tie_breaker(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $selected = $this->process($admin, $scenario, ['name' => 'Seleccionado']);
        $otherProcess = $this->process($admin, $scenario, ['name' => 'Otro proceso']);
        $otherScenario = $this->memory($admin, ['name' => 'Otra memoria']);
        $foreign = $this->process($admin, $otherScenario);
        $this->event($scenario, $selected, ['occurred_at' => '2026-10-07 12:00:00.123456', 'metadata' => ['page_number' => 0]]);
        $latest = $this->event($scenario, $selected, [
            'occurred_at' => '2026-10-07 12:00:00.123456', 'metadata' => ['page_number' => 3],
            'description' => 'Solicitud conservada en el historial.',
        ]);
        $this->event($scenario, $selected, ['occurred_at' => '2026-10-07 11:59:59.999999', 'metadata' => ['page_number' => 1]]);
        $this->event($scenario, $selected, ['type' => SimulationEventType::PageHit, 'occurred_at' => '2026-10-07 13:00:00.000000']);
        $this->event($scenario, $otherProcess, ['occurred_at' => '2026-10-07 14:00:00.000000']);
        $this->event($otherScenario, $foreign, ['occurred_at' => '2026-10-07 15:00:00.000000']);

        $snapshot = $this->snapshot($this->user('observador'), $scenario, $selected);

        $this->assertSame($latest->id, $snapshot['last_request']['event_id']);
        $this->assertSame(SimulationEventType::PageRequest->value, $snapshot['last_request']['type']);
        $this->assertSame(3, $snapshot['last_request']['page_number']);
        $this->assertSame('Solicitud conservada en el historial.', $snapshot['last_request']['description']);
        $this->assertSame('2026-10-07 12:00:00.123456', $snapshot['last_request']['occurred_at']->format('Y-m-d H:i:s.u'));
    }

    public static function invalidRecordedPages(): array
    {
        return [
            'string page number' => [['page_number' => '1']],
            'negative page number' => [['page_number' => -1]],
            'fractional page number' => [['page_number' => 1.5]],
            'missing page number' => [[]],
        ];
    }

    #[DataProvider('invalidRecordedPages')]
    public function test_cpu_does_not_invent_a_page_number_from_invalid_event_metadata(array $metadata): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $event = $this->event($scenario, $process, ['metadata' => $metadata]);

        $snapshot = $this->snapshot($this->user('observador'), $scenario, $process);

        $this->assertSame($event->id, $snapshot['last_request']['event_id']);
        $this->assertNull($snapshot['last_request']['page_number']);
    }

    public function test_route_requires_authentication_and_all_three_read_permissions(): void
    {
        $this->get('/paginacion')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/paginacion')->assertForbidden();
        $partial = User::factory()->create();
        $partial->givePermissionTo(['memory.view', 'simulations.view']);
        $this->actingAs($partial)->get('/paginacion')->assertForbidden();

        foreach (['administrador', 'operador', 'observador'] as $role) {
            $this->actingAs($this->user($role))->get('/paginacion')->assertOk();
        }
    }

    public function test_livewire_does_not_select_a_scenario_or_a_process_implicitly(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $this->process($admin, $scenario);
        $observer = $this->user('observador');

        Livewire::actingAs($observer)->test(PagingSimulator::class)
            ->assertSet('scenarioId', null)->assertSet('processId', null)->assertViewHas('snapshot', null)
            ->set('scenarioId', (string) $scenario->id)
            ->assertSet('processId', null)
            ->assertViewHas('snapshot', fn ($snapshot) => $snapshot['selected_process'] === null && $snapshot['pages']->isEmpty());

        $this->assertSame($scenario->id, session('memorylab.active_scenario_id'));
    }

    public function test_livewire_selection_refresh_and_process_switching_preserve_all_simulation_records(): void
    {
        $admin = $this->user('administrador');
        $first = $this->memory($admin, ['name' => 'Primera memoria']);
        $firstProcess = $this->process($admin, $first, ['name' => 'Proceso A', 'size_kb' => 2]);
        $second = $this->memory($admin, ['name' => 'Segunda memoria', 'page_kb' => 2]);
        $secondProcess = $this->process($admin, $second, ['name' => 'Proceso B', 'size_kb' => 3]);
        $observer = $this->user('observador');
        $this->actingAs($observer);
        app(ActiveScenarioService::class)->select($observer, $first->id);
        $before = $this->domainState();

        $component = Livewire::test(PagingSimulator::class)
            ->assertSet('scenarioId', $first->id)->assertSet('processId', null)
            ->set('processId', (string) $firstProcess->id)
            ->assertViewHas('snapshot', fn ($snapshot) => $snapshot['selected_process']->is($firstProcess) && $snapshot['pages']->count() === 2)
            ->call('$refresh')
            ->set('scenarioId', (string) $second->id)->assertSet('processId', null)
            ->assertViewHas('snapshot', fn ($snapshot) => $snapshot['pages']->isEmpty()
                && $snapshot['processes']->pluck('id')->all() === [$secondProcess->id])
            ->set('processId', (string) $secondProcess->id)
            ->assertViewHas('snapshot', fn ($snapshot) => $snapshot['selected_process']->is($secondProcess)
                && $snapshot['secondary']['selected_bytes'] === 4096)
            ->call('$refresh');

        $this->assertStringNotContainsString('wire:submit=', $component->html());
        $this->assertSame($second->id, session('memorylab.active_scenario_id'));
        $this->assertSame($before, $this->domainState());
    }

    public function test_forged_livewire_process_id_cannot_display_pages_from_another_scenario(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $this->process($admin, $scenario, ['name' => 'Proceso local']);
        $other = $this->memory($admin, ['name' => 'Otra memoria']);
        $foreign = $this->process($admin, $other, ['name' => 'Proceso externo oculto']);
        $observer = $this->user('observador');
        $before = $this->domainState();

        Livewire::actingAs($observer)->test(PagingSimulator::class)
            ->set('scenarioId', (string) $scenario->id)
            ->set('processId', (string) $foreign->id)->assertHasErrors(['processId'])
            ->assertViewHas('snapshot', fn ($snapshot) => $snapshot === null
                || ($snapshot['selected_process'] === null && $snapshot['pages']->isEmpty()))
            ->assertDontSee('Proceso externo oculto');

        $this->assertSame($scenario->id, session('memorylab.active_scenario_id'));
        $this->assertSame($before, $this->domainState());
    }

    public function test_invalid_livewire_scenario_does_not_replace_the_previous_session_selection(): void
    {
        $scenario = $this->memory($this->user('administrador'));
        $observer = $this->user('observador');
        $this->actingAs($observer);
        app(ActiveScenarioService::class)->select($observer, $scenario->id);

        Livewire::test(PagingSimulator::class)
            ->set('scenarioId', '999999')->assertHasErrors(['scenarioId'])->assertViewHas('snapshot', null);

        $this->assertSame($scenario->id, session('memorylab.active_scenario_id'));
        $this->assertDatabaseCount('simulation_events', 2);
    }

    public function test_livewire_reports_unconfigured_memory_without_creating_frames_or_events(): void
    {
        $scenario = Scenario::create(['name' => 'Memoria pendiente']);

        Livewire::actingAs($this->user('observador'))->test(PagingSimulator::class)
            ->set('scenarioId', (string) $scenario->id)
            ->assertHasErrors(['scenarioId'])->assertViewHas('snapshot', null);

        $this->assertDatabaseCount('memory_frames', 0);
        $this->assertDatabaseCount('simulation_events', 0);
    }

    public function test_livewire_refresh_rechecks_permissions_after_the_observer_role_is_revoked(): void
    {
        $scenario = $this->memory($this->user('administrador'));
        $observer = $this->user('observador');
        $component = Livewire::actingAs($observer)->test(PagingSimulator::class)
            ->set('scenarioId', (string) $scenario->id);
        $observer->fresh()->syncRoles([]);

        $component->call('$refresh')->assertForbidden();

        $this->assertDatabaseCount('simulation_events', 2);
    }

    public function test_page_rows_show_fifteen_then_two_pages_and_reset_when_the_process_changes(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $first = $this->process($admin, $scenario, ['name' => 'Proceso largo', 'size_kb' => 17]);
        $second = $this->process($admin, $scenario, ['name' => 'Proceso corto', 'size_kb' => 3]);
        $before = $this->domainState();

        Livewire::actingAs($this->user('observador'))->test(PagingSimulator::class)
            ->set('scenarioId', (string) $scenario->id)->set('processId', (string) $first->id)
            ->assertViewHas('pageRows', fn ($rows) => $rows->total() === 17
                && $rows->currentPage() === 1
                && $rows->count() === 15
                && $rows->getCollection()->pluck('page_number')->all() === range(0, 14))
            ->call('gotoPage', 2, 'pagesPage')
            ->assertViewHas('pageRows', fn ($rows) => $rows->currentPage() === 2
                && $rows->count() === 2
                && $rows->getCollection()->pluck('page_number')->all() === [15, 16])
            ->set('processId', (string) $second->id)
            ->assertViewHas('pageRows', fn ($rows) => $rows->currentPage() === 1
                && $rows->total() === 3
                && $rows->getCollection()->every(fn ($page) => $page->process_id === $second->id))
            ->call('$refresh');

        $this->assertSame($before, $this->domainState());
    }

    public static function malformedPageNumbers(): array
    {
        return [
            'non numeric page' => ['abc', 1],
            'array page' => [['invalid'], 1],
            'zero page' => [0, 1],
            'page beyond last page' => [999, 2],
        ];
    }

    #[DataProvider('malformedPageNumbers')]
    public function test_malformed_page_numbers_are_normalized_without_mutating_the_simulation(mixed $requested, int $expected): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario, ['size_kb' => 17]);
        $before = $this->domainState();

        Livewire::actingAs($this->user('observador'))->test(PagingSimulator::class)
            ->set('scenarioId', (string) $scenario->id)->set('processId', (string) $process->id)
            ->set('paginators.pagesPage', $requested)
            ->assertSet('paginators.pagesPage', $expected)
            ->assertViewHas('pageRows', fn ($rows) => $rows->currentPage() === $expected
                && $rows->total() === 17
                && $rows->count() === ($expected === 1 ? 15 : 2)
                && $rows->getCollection()->every(fn ($page) => $page->process_id === $process->id))
            ->call('$refresh');

        $this->assertSame($before, $this->domainState());
    }

    public function test_malformed_paginator_state_is_replaced_with_a_valid_first_page(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario, ['size_kb' => 17]);
        $before = $this->domainState();

        Livewire::actingAs($this->user('observador'))->test(PagingSimulator::class)
            ->set('scenarioId', (string) $scenario->id)->set('processId', (string) $process->id)
            ->set('paginators', 'invalid')
            ->assertSet('paginators.pagesPage', 1)
            ->assertViewHas('pageRows', fn ($rows) => $rows->currentPage() === 1 && $rows->count() === 15);

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

    private function process(User $actor, Scenario $scenario, array $input = []): Process
    {
        return app(ProcessManagerService::class)->create($actor, $scenario->id, array_replace([
            'name' => 'Proceso de prueba', 'size_kb' => 4,
        ], $input));
    }

    private function snapshot(User $actor, Scenario $scenario, ?Process $process = null): array
    {
        return app(PagingService::class)->snapshot($actor, $scenario->id, $process?->id);
    }

    private function event(Scenario $scenario, Process $process, array $attributes = []): SimulationEvent
    {
        return SimulationEvent::create(array_replace([
            'scenario_id' => $scenario->id, 'process_id' => $process->id,
            'type' => SimulationEventType::PageRequest, 'description' => 'Solicitud registrada.',
            'metadata' => ['page_number' => 0], 'occurred_at' => '2026-10-07 12:00:00.000000',
        ], $attributes));
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
