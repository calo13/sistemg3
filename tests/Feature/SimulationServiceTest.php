<?php

namespace Tests\Feature;

use App\Enums\ProcessStatus;
use App\Enums\ScenarioStatus;
use App\Enums\SegmentStatus;
use App\Enums\SimulationEventType;
use App\Enums\SimulationMode;
use App\Livewire\SimulationDemo;
use App\Models\Process;
use App\Models\Scenario;
use App\Models\SimulationEvent;
use App\Models\User;
use App\Services\MemoryConfigurationService;
use App\Services\PagingService;
use App\Services\ProcessManagerService;
use App\Services\SegmentationService;
use App\Services\SimulationService;
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

class SimulationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_demo_builds_two_real_memory_models_with_canonical_processes_and_five_loaded_pages(): void
    {
        $admin = $this->user('administrador');

        $result = app(SimulationService::class)->startDemo($admin);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $result['run_id']);
        $this->assertNotSame($result['paging_scenario_id'], $result['segmentation_scenario_id']);
        $paging = Scenario::with('configuration')->findOrFail($result['paging_scenario_id']);
        $segmentation = Scenario::with('configuration')->findOrFail($result['segmentation_scenario_id']);
        $this->assertTrue($paging->is_demo);
        $this->assertTrue($segmentation->is_demo);
        $this->assertSame($admin->id, $paging->created_by);
        $this->assertSame($admin->id, $segmentation->created_by);
        $this->assertSame(SimulationMode::Paging, $paging->mode);
        $this->assertSame(SimulationMode::Segmentation, $segmentation->mode);
        $this->assertSame(16384, $paging->configuration->ram_size_bytes);
        $this->assertSame(1024, $paging->configuration->page_size_bytes);
        $this->assertSame(65536, $paging->configuration->secondary_storage_bytes);
        $this->assertSame(16, $paging->frames()->count());
        $this->assertSame(15, $paging->pages()->count());
        $this->assertSame(16384, $segmentation->configuration->ram_size_bytes);
        $this->assertSame(0, $segmentation->configuration->secondary_storage_bytes);
        $this->assertSame(0, $segmentation->frames()->count());
        $this->assertSame(0, $segmentation->pages()->count());
        $processes = $paging->processes()->orderBy('id')->get();
        $this->assertSame(['Chrome', 'Spotify', 'VS Code', 'MemoryLab'], $processes->pluck('name')->all());
        $this->assertSame([4096, 3072, 6144, 2048], $processes->pluck('size_bytes')->all());
        $chrome = $processes->firstWhere('name', 'Chrome');
        $this->assertSame($chrome->id, $result['paging_process_id']);
        $loadedPages = $paging->pages()->whereNotNull('frame_id')->with('process')->orderBy('frame_id')->get();
        $this->assertSame([
            ['Chrome', 0], ['Chrome', 1], ['Spotify', 0], ['VS Code', 0], ['VS Code', 1],
        ], $loadedPages->map(fn ($page) => [$page->process->name, $page->page_number])->all());
        $this->assertSame([0, 1, 2, 3, 4], $loadedPages->map(fn ($page) => $page->frame->frame_number)->all());
        $this->assertNull($chrome->pages()->where('page_number', 3)->firstOrFail()->frame_id);
        $this->assertSame(5, $paging->events()->where('type', SimulationEventType::PageRequest->value)->count());
        $this->assertSame(5, $paging->events()->where('type', SimulationEventType::PageFault->value)->count());
        $this->assertSame(5, $paging->events()->where('type', SimulationEventType::PageLoaded->value)->count());
        $snapshot = app(PagingService::class)->snapshot($admin, $paging->id);
        $this->assertSame(5120, $snapshot['ram']['used_bytes']);
        $this->assertSame(10240, $snapshot['secondary']['used_bytes']);
        $editor = $segmentation->processes()->sole();
        $this->assertSame($editor->id, $result['segmentation_process_id']);
        $this->assertSame('Editor', $editor->name);
        $this->assertSame(4096, $editor->size_bytes);
        $segments = $editor->segments()->orderBy('segment_number')->get();
        $this->assertSame([0, 1, 2, 3], $segments->pluck('segment_number')->all());
        $this->assertSame([1000, 4000, 7000, 9000], $segments->pluck('base')->all());
        $this->assertSame([1200, 800, 600, 1000], $segments->pluck('size_bytes')->all());
        $this->assertTrue($segments->every(fn ($segment) => $segment->status === SegmentStatus::Active));
        $segSnapshot = app(SegmentationService::class)->snapshot($admin, $segmentation->id, $editor->id);
        $this->assertSame(3600, $segSnapshot['ram']['used_bytes']);
        $this->assertTrue(SimulationEvent::get()->every(fn ($event) => $event->user_id === $admin->id));
    }

    public function test_reset_paging_deletes_pages_and_terminates_processes_but_keeps_frames_configuration_and_history(): void
    {
        $admin = $this->user('administrador');
        [$scenario, $process] = $this->pagingContext($admin);
        $framesBefore = $scenario->frames()->orderBy('id')->get()->map(fn ($frame) => $frame->getAttributes())->all();
        $configurationBefore = $scenario->configuration->getAttributes();
        $historyBefore = $this->eventRows($scenario);

        $result = app(SimulationService::class)->reset($admin, $scenario->id);

        $this->assertSame(['scenario_id' => $scenario->id, 'terminated_processes' => 1, 'released_pages' => 3, 'released_segments' => 0], $result);
        $this->assertSame(ScenarioStatus::Ready, $scenario->fresh()->status);
        $this->assertSame(ProcessStatus::Terminated, $process->fresh()->status);
        $this->assertSame(0, $scenario->pages()->count());
        $this->assertSame($framesBefore, $scenario->frames()->orderBy('id')->get()->map(fn ($frame) => $frame->getAttributes())->all());
        $this->assertSame($configurationBefore, $scenario->configuration->fresh()->getAttributes());
        $this->assertSame($historyBefore, array_slice($this->eventRows($scenario), 0, count($historyBefore)));
        $event = $scenario->events()->latest('id')->firstOrFail();
        $this->assertSame(SimulationEventType::MemoryReset, $event->type);
        $this->assertSame($admin->id, $event->user_id);
        $this->assertEquals(['mode' => 'PAGING'] + $result, $event->metadata);
        $this->assertSame(count($historyBefore) + 1, $scenario->events()->count());
        $snapshot = app(PagingService::class)->snapshot($admin, $scenario->id);
        $this->assertSame(0, $snapshot['ram']['used_bytes']);
        $this->assertSame(2, $snapshot['ram']['frames_free']);
        $this->assertSame(0, $snapshot['secondary']['used_bytes']);
    }

    public function test_reset_segmentation_releases_active_segments_and_preserves_released_records_and_numbers(): void
    {
        $admin = $this->user('administrador');
        $demo = app(SimulationService::class)->startDemo($admin);
        $scenario = Scenario::with('configuration')->findOrFail($demo['segmentation_scenario_id']);
        $process = Process::findOrFail($demo['segmentation_process_id']);
        $segmentsBefore = $scenario->segments()->orderBy('id')->get();
        $segmentsBefore->first()->update(['status' => SegmentStatus::Released]);
        $historyBefore = $this->eventRows($scenario);

        $result = app(SimulationService::class)->reset($admin, $scenario->id);

        $this->assertSame(['scenario_id' => $scenario->id, 'terminated_processes' => 1, 'released_pages' => 0, 'released_segments' => 3], $result);
        $segmentsAfter = $scenario->segments()->orderBy('id')->get();
        $this->assertSame($segmentsBefore->pluck('id')->all(), $segmentsAfter->pluck('id')->all());
        $this->assertSame([0, 1, 2, 3], $segmentsAfter->pluck('segment_number')->all());
        $this->assertSame([1000, 4000, 7000, 9000], $segmentsAfter->pluck('base')->all());
        $this->assertTrue($segmentsAfter->every(fn ($segment) => $segment->status === SegmentStatus::Released));
        $this->assertSame(ProcessStatus::Terminated, $process->fresh()->status);
        $this->assertSame(ScenarioStatus::Ready, $scenario->fresh()->status);
        $snapshot = app(SegmentationService::class)->snapshot($admin, $scenario->id, $process->id);
        $this->assertSame(0, $snapshot['ram']['used_bytes']);
        $this->assertCount(1, $snapshot['blocks']);
        $this->assertSame('FREE', $snapshot['blocks'][0]['state']);
        $this->assertSame(16384, $snapshot['blocks'][0]['size_bytes']);
        $this->assertSame($historyBefore, array_slice($this->eventRows($scenario), 0, count($historyBefore)));
    }

    public function test_repeated_reset_records_an_explicit_zero_count_event_and_changes_no_other_domain_data(): void
    {
        $admin = $this->user('administrador');
        [$scenario] = $this->pagingContext($admin);
        app(SimulationService::class)->reset($admin, $scenario->id);
        $before = $this->domainState(false);
        $eventCount = $scenario->events()->count();

        $result = app(SimulationService::class)->reset($admin, $scenario->id);

        $this->assertSame(['scenario_id' => $scenario->id, 'terminated_processes' => 0, 'released_pages' => 0, 'released_segments' => 0], $result);
        $this->assertSame($before, $this->domainState(false));
        $this->assertSame($eventCount + 1, $scenario->events()->count());
        $this->assertSame(2, $scenario->events()->where('type', SimulationEventType::MemoryReset->value)->count());
    }

    public function test_reset_rejects_pending_requests_but_completed_results_remain_available_as_history(): void
    {
        $admin = $this->user('administrador');
        [$scenario, $process, $completed] = $this->pagingContext($admin);
        $pending = app(PagingService::class)->beginRequest($admin, $scenario->id, $process->id, 2);
        app(SimulationService::class)->reset($admin, $scenario->id);
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => app(PagingService::class)->resolveRequest($admin, $pending['request_event_id']), 'request_event_id');
        $this->assertEquals($completed, app(PagingService::class)->resolveRequest($admin, $completed['request_event_id']));

        $this->assertSame($before, $this->domainState());
    }

    public function test_reset_is_scoped_to_its_explicit_scenario_and_does_not_change_the_active_selection(): void
    {
        $admin = $this->user('administrador');
        [$target] = $this->pagingContext($admin);
        [$other] = $this->pagingContext($admin, 'Otra memoria');
        session(['memorylab.active_scenario_id' => $other->id]);
        $otherBefore = $other->fresh()->getAttributes();
        $otherHistory = $this->eventRows($other);
        $otherPages = $other->pages()->orderBy('id')->get()->map(fn ($page) => $page->getAttributes())->all();

        app(SimulationService::class)->reset($admin, $target->id);

        $this->assertSame($otherBefore, $other->fresh()->getAttributes());
        $this->assertSame($otherHistory, $this->eventRows($other));
        $this->assertSame($otherPages, $other->pages()->orderBy('id')->get()->map(fn ($page) => $page->getAttributes())->all());
        $this->assertSame($other->id, session('memorylab.active_scenario_id'));
    }

    public function test_restart_completes_the_old_pair_and_creates_a_fresh_pair_without_discarding_history(): void
    {
        $admin = $this->user('administrador');
        $old = app(SimulationService::class)->startDemo($admin);
        $paging = Scenario::findOrFail($old['paging_scenario_id']);
        $segmentation = Scenario::findOrFail($old['segmentation_scenario_id']);
        $historyBefore = SimulationEvent::orderBy('id')->get()->map(fn ($event) => $event->getAttributes())->all();
        $oldFrames = $paging->frames()->pluck('id')->all();
        session(['memorylab.active_scenario_id' => $paging->id]);

        $new = app(SimulationService::class)->restartDemo($admin, $paging->id, $segmentation->id);

        $this->assertNotSame($old['run_id'], $new['run_id']);
        $this->assertNotSame($old['paging_scenario_id'], $new['paging_scenario_id']);
        $this->assertNotSame($old['segmentation_scenario_id'], $new['segmentation_scenario_id']);
        $this->assertNotSame($old['paging_process_id'], $new['paging_process_id']);
        $this->assertNotSame($old['segmentation_process_id'], $new['segmentation_process_id']);
        $this->assertSame(ScenarioStatus::Completed, $paging->fresh()->status);
        $this->assertSame(ScenarioStatus::Completed, $segmentation->fresh()->status);
        $this->assertSame($oldFrames, $paging->frames()->pluck('id')->all());
        $this->assertSame(0, $paging->pages()->count());
        $this->assertSame(4, $paging->processes()->where('status', ProcessStatus::Terminated->value)->count());
        $this->assertSame(4, $segmentation->segments()->where('status', SegmentStatus::Released->value)->count());
        $this->assertSame(1, $paging->events()->where('type', SimulationEventType::MemoryReset->value)->count());
        $this->assertSame(1, $segmentation->events()->where('type', SimulationEventType::MemoryReset->value)->count());
        $this->assertSame($historyBefore, SimulationEvent::orderBy('id')->limit(count($historyBefore))->get()->map(fn ($event) => $event->getAttributes())->all());
        $newPaging = Scenario::findOrFail($new['paging_scenario_id']);
        $newSegmentation = Scenario::findOrFail($new['segmentation_scenario_id']);
        $this->assertTrue($newPaging->is_demo);
        $this->assertTrue($newSegmentation->is_demo);
        $this->assertSame(15, $newPaging->pages()->count());
        $this->assertSame(5, $newPaging->pages()->whereNotNull('frame_id')->count());
        $this->assertSame(4, $newSegmentation->segments()->where('status', SegmentStatus::Active->value)->count());
        $this->assertSame($paging->id, session('memorylab.active_scenario_id'));
        $this->assertDatabaseCount('scenarios', 4);
    }

    public static function invalidDemoPairs(): array
    {
        return [
            'same scenario' => ['same', 'segmentation_scenario_id'],
            'swapped modes' => ['swapped', 'paging_scenario_id'],
            'paging is not a demo' => ['paging', 'paging_scenario_id'],
            'segmentation is not a demo' => ['segmentation', 'segmentation_scenario_id'],
            'old pair is completed' => ['completed', 'scenario_id'],
        ];
    }

    #[DataProvider('invalidDemoPairs')]
    public function test_invalid_restart_pairs_leave_the_entire_domain_unchanged(string $kind, string $error): void
    {
        $admin = $this->user('administrador');
        $demo = app(SimulationService::class)->startDemo($admin);
        $pagingId = $demo['paging_scenario_id'];
        $segmentationId = $demo['segmentation_scenario_id'];
        if ($kind === 'same') {
            $segmentationId = $pagingId;
        } elseif ($kind === 'swapped') {
            [$pagingId, $segmentationId] = [$segmentationId, $pagingId];
        } elseif ($kind === 'completed') {
            Scenario::findOrFail($pagingId)->update(['status' => ScenarioStatus::Completed]);
        } else {
            Scenario::findOrFail($kind === 'paging' ? $pagingId : $segmentationId)->update(['is_demo' => false]);
        }
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => app(SimulationService::class)->restartDemo($admin, $pagingId, $segmentationId), $error);

        $this->assertSame($before, $this->domainState());
    }

    public function test_start_failure_in_the_second_mode_rolls_back_both_new_scenarios(): void
    {
        $admin = $this->user('administrador');
        $this->assertEventFailureRollsBack(
            fn (SimulationEvent $event) => $event->type === SimulationEventType::ProcessCreated && Process::find($event->process_id)?->name === 'Editor',
            fn () => app(SimulationService::class)->startDemo($admin),
        );
    }

    public function test_restart_failure_rolls_back_old_resets_and_all_new_demo_records(): void
    {
        $admin = $this->user('administrador');
        $demo = app(SimulationService::class)->startDemo($admin);
        $this->assertEventFailureRollsBack(
            fn (SimulationEvent $event) => $event->type === SimulationEventType::MemoryConfigured && $event->scenario->mode === SimulationMode::Segmentation,
            fn () => app(SimulationService::class)->restartDemo($admin, $demo['paging_scenario_id'], $demo['segmentation_scenario_id']),
        );
    }

    public function test_reset_event_failure_rolls_back_page_deletion_process_termination_and_scenario_state(): void
    {
        $admin = $this->user('administrador');
        [$scenario] = $this->pagingContext($admin);
        $this->assertEventFailureRollsBack(
            fn (SimulationEvent $event) => $event->type === SimulationEventType::MemoryReset,
            fn () => app(SimulationService::class)->reset($admin, $scenario->id),
        );
    }

    public static function missingStartPermissions(): array
    {
        return array_map(fn ($permission) => [$permission], self::startPermissions());
    }

    #[DataProvider('missingStartPermissions')]
    public function test_each_configuration_creation_execution_and_read_permission_is_required_for_a_demo(string $missing): void
    {
        $actor = User::factory()->create();
        $actor->givePermissionTo(array_values(array_diff(self::startPermissions(), [$missing])));
        $before = $this->domainState();

        try {
            app(SimulationService::class)->startDemo($actor);
            $this->fail('La demostracion exige todos los permisos de sus operaciones reales.');
        } catch (AuthorizationException) {
            $this->assertSame($before, $this->domainState());
        }
    }

    public static function missingResetPermissions(): array
    {
        return array_map(fn ($permission) => [$permission], ['memory.reset', 'simulations.execute', 'memory.view', 'tables.view', 'simulations.view']);
    }

    #[DataProvider('missingResetPermissions')]
    public function test_reset_requires_all_its_permissions_and_keeps_allocated_memory_when_denied(string $missing): void
    {
        $admin = $this->user('administrador');
        [$scenario] = $this->pagingContext($admin);
        $actor = User::factory()->create();
        $actor->givePermissionTo(array_values(array_diff(['memory.reset', 'simulations.execute', 'memory.view', 'tables.view', 'simulations.view'], [$missing])));
        $before = $this->domainState();

        try {
            app(SimulationService::class)->reset($actor, $scenario->id);
            $this->fail('La memoria solo se libera con todos los permisos de reset.');
        } catch (AuthorizationException) {
            $this->assertSame($before, $this->domainState());
        }
    }

    public function test_restart_requires_reset_permission_in_addition_to_demo_creation_permissions(): void
    {
        $admin = $this->user('administrador');
        $demo = app(SimulationService::class)->startDemo($admin);
        $actor = User::factory()->create();
        $actor->givePermissionTo(self::startPermissions());
        $before = $this->domainState();

        try {
            app(SimulationService::class)->restartDemo($actor, $demo['paging_scenario_id'], $demo['segmentation_scenario_id']);
            $this->fail('Reiniciar una demostracion tambien requiere liberar la memoria previa.');
        } catch (AuthorizationException) {
            $this->assertSame($before, $this->domainState());
        }
    }

    public function test_revoked_admin_permissions_are_rechecked_for_start_reset_and_restart(): void
    {
        $admin = $this->user('administrador');
        $demo = app(SimulationService::class)->startDemo($admin);
        $this->assertTrue($admin->can('memory.reset'));
        $admin->fresh()->syncRoles(['observador']);
        $before = $this->domainState();

        foreach ([
            fn () => app(SimulationService::class)->startDemo($admin),
            fn () => app(SimulationService::class)->reset($admin, $demo['paging_scenario_id']),
            fn () => app(SimulationService::class)->restartDemo($admin, $demo['paging_scenario_id'], $demo['segmentation_scenario_id']),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Los permisos de una instancia anterior no deben autorizar operaciones.');
            } catch (AuthorizationException) {
                $this->assertSame($before, $this->domainState());
            }
        }
    }

    public function test_reset_rejects_completed_scenarios_and_invalid_memory_geometry_without_changes(): void
    {
        $admin = $this->user('administrador');
        foreach (['completed', 'frames', 'configuration'] as $kind) {
            [$scenario] = $this->pagingContext($admin, 'Memoria '.$kind);
            if ($kind === 'frames') {
                $frame = $scenario->frames()->where('frame_number', 1)->firstOrFail();
                $scenario->pages()->where('frame_id', $frame->id)->update(['frame_id' => null, 'loaded_at' => null]);
                $frame->delete();
            } elseif ($kind === 'completed') {
                $scenario->update(['status' => ScenarioStatus::Completed]);
            } else {
                $scenario->configuration->delete();
            }
            $before = $this->domainState();
            $this->assertValidationFailure(fn () => app(SimulationService::class)->reset($admin, $scenario->id), 'scenario_id');
            $this->assertSame($before, $this->domainState());
        }
    }

    public function test_missing_reset_or_restart_scenario_ids_do_not_create_a_replacement_demo(): void
    {
        $admin = $this->user('administrador');
        $demo = app(SimulationService::class)->startDemo($admin);
        $before = $this->domainState();

        foreach ([
            fn () => app(SimulationService::class)->reset($admin, 999999),
            fn () => app(SimulationService::class)->restartDemo($admin, $demo['paging_scenario_id'], 999999),
        ] as $operation) {
            try {
                $operation();
                $this->fail('La operacion requiere escenarios existentes.');
            } catch (ModelNotFoundException) {
                $this->assertSame($before, $this->domainState());
            }
        }
    }

    public static function readerRoles(): array
    {
        return ['administrator' => ['administrador'], 'operator' => ['operador'], 'observer' => ['observador']];
    }

    #[DataProvider('readerRoles')]
    public function test_all_roles_can_read_demonstrations_without_starting_or_resetting_memory(string $role): void
    {
        $actor = $this->user($role);
        $before = $this->domainState();

        $this->actingAs($actor)->get(route('demo.index'))->assertOk();
        Livewire::actingAs($actor)->test(SimulationDemo::class)->assertSet('demo', null)
            ->assertSet('resetResult', null)->assertViewHas('canStart', $role === 'administrador')
            ->assertViewHas('canReset', $role === 'administrador')->call('$refresh');

        $this->assertSame($before, $this->domainState());
    }

    public static function missingReaderPermissions(): array
    {
        return array_map(fn ($permission) => [$permission], ['memory.view', 'tables.view', 'simulations.view']);
    }

    #[DataProvider('missingReaderPermissions')]
    public function test_route_and_component_require_each_memory_read_permission(string $missing): void
    {
        $actor = User::factory()->create();
        $actor->givePermissionTo(array_values(array_diff(['memory.view', 'tables.view', 'simulations.view'], [$missing])));
        $before = $this->domainState();

        $this->actingAs($actor)->get(route('demo.index'))->assertForbidden();
        Livewire::actingAs($actor)->test(SimulationDemo::class)->assertForbidden();

        $this->assertSame($before, $this->domainState());
    }

    public function test_route_requires_authentication_and_only_the_three_memory_read_permissions_for_consultation(): void
    {
        $this->get(route('demo.index'))->assertRedirect(route('login'));
        $reader = User::factory()->create();
        $reader->givePermissionTo(['memory.view', 'tables.view', 'simulations.view']);

        $this->actingAs($reader)->get(route('demo.index'))->assertOk();
    }

    public function test_administrator_start_remembers_the_pair_and_restart_replaces_the_session_context(): void
    {
        $component = Livewire::actingAs($this->user('administrador'))->test(SimulationDemo::class)
            ->call('startDemo')->assertHasNoErrors()->assertDispatched('memory-updated');
        $old = session(SimulationDemo::SESSION_KEY);
        $this->assertIsArray($old);
        $component->assertSet('demo', $old)->assertSet('resetScenarioId', $old['paging_scenario_id']);
        $this->assertSame($old['paging_scenario_id'], session('memorylab.active_scenario_id'));
        $beforeReload = $this->domainState();
        Livewire::test(SimulationDemo::class)->assertSet('demo', $old)->call('$refresh');
        $this->assertSame($beforeReload, $this->domainState());

        $component->call('restartDemo')->assertHasNoErrors()->assertDispatched('memory-updated');
        $new = session(SimulationDemo::SESSION_KEY);

        $this->assertNotSame($old['run_id'], $new['run_id']);
        $component->assertSet('demo', $new)->assertSet('resetScenarioId', $new['paging_scenario_id'])->assertSet('resetResult', null);
        $this->assertSame($new['paging_scenario_id'], session('memorylab.active_scenario_id'));
        $this->assertSame(ScenarioStatus::Completed, Scenario::findOrFail($old['paging_scenario_id'])->status);
        $this->assertSame(ScenarioStatus::Completed, Scenario::findOrFail($old['segmentation_scenario_id'])->status);
    }

    public function test_observer_has_no_mutation_forms_and_forged_start_restart_and_reset_are_forbidden(): void
    {
        $admin = $this->user('administrador');
        $demo = app(SimulationService::class)->startDemo($admin);
        session([SimulationDemo::SESSION_KEY => $demo]);
        $observer = $this->user('observador');
        $before = $this->domainState();

        foreach (['startDemo', 'restartDemo', 'resetMemory'] as $action) {
            $component = Livewire::actingAs($observer)->test(SimulationDemo::class)
                ->assertSet('demo', $demo)->assertViewHas('canStart', false)->assertViewHas('canReset', false)
                ->set('resetScenarioId', $demo['paging_scenario_id']);
            $this->assertStringNotContainsString('wire:submit="resetMemory"', $component->html());
            $this->assertStringNotContainsString('wire:click="startDemo"', $component->html());
            $this->assertStringNotContainsString('wire:click="restartDemo"', $component->html());
            $component->call($action)->assertForbidden();
            $this->assertSame($before, $this->domainState());
        }
    }

    public function test_reader_can_select_either_demo_scenario_without_mutating_memory(): void
    {
        $demo = app(SimulationService::class)->startDemo($this->user('administrador'));
        session([SimulationDemo::SESSION_KEY => $demo]);
        $observer = $this->user('observador');
        $before = $this->domainState();

        Livewire::actingAs($observer)->test(SimulationDemo::class)->call('selectScenario', $demo['paging_scenario_id'])
            ->assertRedirect(route('paging.index'));
        $this->assertSame($demo['paging_scenario_id'], session('memorylab.active_scenario_id'));
        Livewire::test(SimulationDemo::class)->call('selectScenario', $demo['segmentation_scenario_id'])
            ->assertRedirect(route('segmentation.index'));
        $this->assertSame($demo['segmentation_scenario_id'], session('memorylab.active_scenario_id'));
        $this->assertSame($before, $this->domainState());
    }

    public function test_reset_form_uses_the_selected_scenario_and_changing_selection_clears_its_result(): void
    {
        $admin = $this->user('administrador');
        [$first] = $this->pagingContext($admin);
        [$second] = $this->pagingContext($admin, 'Segunda memoria');
        $component = Livewire::actingAs($admin)->test(SimulationDemo::class)->set('resetScenarioId', $first->id)
            ->call('resetMemory')->assertHasNoErrors()->assertSet('resetResult.scenario_id', $first->id)
            ->assertSet('resetResult.terminated_processes', 1)->assertSet('resetResult.released_pages', 3)
            ->assertSeeHtml('data-memory-reset')->assertDispatched('memory-updated');
        $before = $this->domainState();

        $component->set('resetScenarioId', $second->id)->assertSet('resetResult', null);

        $this->assertSame($before, $this->domainState());
        $this->assertSame(3, $second->pages()->count());
    }

    public function test_reset_form_maps_missing_and_non_executable_scenarios_and_restart_requires_a_pair(): void
    {
        $admin = $this->user('administrador');
        [$scenario] = $this->pagingContext($admin);
        $scenario->update(['status' => ScenarioStatus::Completed]);
        $before = $this->domainState();
        $component = Livewire::actingAs($admin)->test(SimulationDemo::class);

        $component->call('restartDemo')->assertHasErrors(['demo']);
        $component->set('resetScenarioId', null)->call('resetMemory')->assertHasErrors(['resetScenarioId']);
        $component->set('resetScenarioId', 999999)->call('resetMemory')->assertHasErrors(['resetScenarioId']);
        $component->set('resetScenarioId', $scenario->id)->call('resetMemory')->assertHasErrors(['resetScenarioId']);

        $this->assertSame($before, $this->domainState());
    }

    public function test_resetting_a_current_demo_scenario_discards_the_saved_pair_and_prevents_restart(): void
    {
        $component = Livewire::actingAs($this->user('administrador'))->test(SimulationDemo::class)
            ->call('startDemo')->assertHasNoErrors();
        $demo = session(SimulationDemo::SESSION_KEY);

        $component->set('resetScenarioId', $demo['paging_scenario_id'])->call('resetMemory')->assertHasNoErrors()
            ->assertSet('demo', null)->assertSet('resetResult.released_pages', 15)->assertSet('resetResult.terminated_processes', 4);

        $this->assertFalse(session()->has(SimulationDemo::SESSION_KEY));
        $before = $this->domainState();
        $component->call('restartDemo')->assertHasErrors(['demo']);
        Livewire::test(SimulationDemo::class)->assertSet('demo', null);
        $this->assertSame($before, $this->domainState());
    }

    public static function lockedResults(): array
    {
        return ['demo' => ['demo'], 'reset result' => ['resetResult']];
    }

    #[DataProvider('lockedResults')]
    public function test_client_cannot_replace_demo_ids_or_reset_results(string $field): void
    {
        $component = Livewire::actingAs($this->user('administrador'))->test(SimulationDemo::class);
        $before = $this->domainState();

        try {
            $component->set($field, ['paging_scenario_id' => 999999, 'scenario_id' => 999999]);
            $this->fail('El cliente no puede fabricar los resultados ni el par demo.');
        } catch (CannotUpdateLockedPropertyException) {
            $this->assertSame($before, $this->domainState());
        }
    }

    public static function staleSessionPairs(): array
    {
        return [
            'string ID' => ['string'], 'missing scenario' => ['missing'], 'completed demo' => ['completed'],
            'wrong mode' => ['mode'], 'no active processes' => ['inactive'],
        ];
    }

    #[DataProvider('staleSessionPairs')]
    public function test_mount_discards_invalid_saved_demo_pairs_without_creating_a_replacement(string $kind): void
    {
        $demo = app(SimulationService::class)->startDemo($this->user('administrador'));
        if ($kind === 'string') {
            $demo['paging_scenario_id'] = (string) $demo['paging_scenario_id'];
        } elseif ($kind === 'missing') {
            $demo['segmentation_scenario_id'] = 999999;
        } elseif ($kind === 'completed') {
            Scenario::findOrFail($demo['paging_scenario_id'])->update(['status' => ScenarioStatus::Completed]);
        } elseif ($kind === 'inactive') {
            Scenario::findOrFail($demo['paging_scenario_id'])->processes()->update(['status' => ProcessStatus::Terminated->value]);
        } else {
            Scenario::findOrFail($demo['segmentation_scenario_id'])->update(['mode' => SimulationMode::Paging]);
        }
        session([SimulationDemo::SESSION_KEY => $demo]);
        $before = $this->domainState();

        Livewire::actingAs($this->user('observador'))->test(SimulationDemo::class)->assertSet('demo', null);

        $this->assertFalse(session()->has(SimulationDemo::SESSION_KEY));
        $this->assertSame($before, $this->domainState());
    }

    public function test_mounted_forms_recheck_admin_permissions_before_start_restart_and_reset(): void
    {
        $admin = $this->user('administrador');
        $demo = app(SimulationService::class)->startDemo($admin);
        session([SimulationDemo::SESSION_KEY => $demo]);
        $components = [];
        foreach (['startDemo', 'restartDemo', 'resetMemory'] as $action) {
            $components[$action] = Livewire::actingAs($admin)->test(SimulationDemo::class)->set('resetScenarioId', $demo['paging_scenario_id']);
        }
        $admin->fresh()->syncRoles(['observador']);
        $before = $this->domainState();

        foreach ($components as $action => $component) {
            $component->call($action)->assertForbidden();
            $this->assertSame($before, $this->domainState());
        }
    }

    private static function startPermissions(): array
    {
        return [
            'memory.view', 'tables.view', 'simulations.view', 'scenarios.create', 'memory.configure',
            'processes.create', 'pages.request', 'simulations.execute', 'segmentation.execute',
        ];
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function pagingContext(User $actor, string $name = 'Memoria para reset'): array
    {
        $scenario = app(MemoryConfigurationService::class)->configure($actor, null, [
            'name' => $name, 'ram_kb' => 2, 'page_kb' => 1, 'secondary_kb' => 64,
        ]);
        $process = app(ProcessManagerService::class)->create($actor, $scenario->id, ['name' => 'Proceso para reset', 'size_kb' => 3]);
        $completed = app(PagingService::class)->requestPage($actor, $scenario->id, $process->id, 0);
        app(PagingService::class)->requestPage($actor, $scenario->id, $process->id, 1);

        return [$scenario, $process, $completed];
    }

    private function eventRows(Scenario $scenario): array
    {
        return $scenario->events()->orderBy('id')->get()->map(fn ($event) => $event->getAttributes())->all();
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

    private function assertEventFailureRollsBack(callable $rejectEvent, callable $operation): void
    {
        $before = $this->domainState();
        $dispatcher = SimulationEvent::getEventDispatcher();
        SimulationEvent::setEventDispatcher(clone $dispatcher);
        SimulationEvent::creating(function (SimulationEvent $event) use ($rejectEvent): void {
            if ($rejectEvent($event)) {
                throw new RuntimeException('Fallo del evento de simulacion de prueba.');
            }
        });

        try {
            try {
                $operation();
                $this->fail('Toda la operacion debe revertirse si falla su evento.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Fallo del evento de simulacion de prueba.', $exception->getMessage());
            }
        } finally {
            SimulationEvent::setEventDispatcher($dispatcher);
        }

        $this->assertSame($before, $this->domainState());
    }
}
