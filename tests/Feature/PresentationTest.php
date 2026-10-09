<?php

namespace Tests\Feature;

use App\Enums\SimulationEventType;
use App\Livewire\PageRequest;
use App\Livewire\PagingSimulator;
use App\Livewire\SegmentAccess;
use App\Models\SimulationEvent;
use App\Models\User;
use App\Services\MemoryConfigurationService;
use App\Services\PagingService;
use App\Services\ProcessManagerService;
use App\Services\SegmentationService;
use App\Services\SimulationService;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_observer_recovers_another_actors_completed_result_with_frame_zero_without_reexecuting_it(): void
    {
        $admin = $this->user('administrador');
        [$scenario, $process] = $this->pagingContext($admin);
        $writer = $this->user('operador');
        $completed = app(PagingService::class)->requestPage($writer, $scenario->id, $process->id, 0);
        $observer = $this->user('observador');
        $before = $this->domainState();

        $snapshot = app(PagingService::class)->snapshot($observer, $scenario->id, $process->id);

        $this->assertStoredResult($completed, $snapshot['last_result']);
        $this->assertSame(0, $snapshot['last_result']['frame_number']);
        $this->assertSame(0, $snapshot['last_result']['physical_address']);
        $this->assertSame(1, $snapshot['page_fault_count']);
        $this->assertSame($completed['request_event_id'], $snapshot['last_request']['event_id']);
        $component = Livewire::actingAs($observer)->test(PageRequest::class, ['scenarioId' => $scenario->id, 'processId' => $process->id])
            ->assertSet('result', null)->assertSet('pending', null)->assertViewHas('sharedResult', true)
            ->assertViewHas('displayResult', fn ($result) => $result['physical_address'] === 0 && $result['request_event_id'] === $completed['request_event_id'])
            ->assertViewHas('flowSteps', fn ($steps) => count($steps) === 8)->assertViewHas('currentStep', 8)
            ->assertSee('Último acceso guardado')->assertSeeHtml('data-cpu-outcome="PAGE_FAULT"')
            ->assertNotDispatched('memory-accessed');
        $component->call('$refresh')->assertNotDispatched('memory-accessed');
        $this->assertSame($before, $this->domainState());
    }

    public function test_shared_hit_has_four_completed_steps_and_fifo_fault_retains_its_canonical_victim(): void
    {
        $admin = $this->user('administrador');
        [$scenario, $process] = $this->pagingContext($admin, ['ram_kb' => 1]);
        app(PagingService::class)->requestPage($admin, $scenario->id, $process->id, 0);
        $hit = app(PagingService::class)->requestPage($admin, $scenario->id, $process->id, 0);
        $observer = $this->user('observador');
        $beforeHitRead = $this->domainState();

        Livewire::actingAs($observer)->test(PageRequest::class, ['scenarioId' => $scenario->id, 'processId' => $process->id])
            ->assertViewHas('displayResult', fn ($result) => $result['outcome'] === 'PAGE_HIT' && $result['request_event_id'] === $hit['request_event_id'])
            ->assertViewHas('flowSteps', fn ($steps) => count($steps) === 4)->assertViewHas('currentStep', 4)->assertViewHas('sharedResult', true);
        $this->assertSame($beforeHitRead, $this->domainState());
        $fault = app(PagingService::class)->requestPage($admin, $scenario->id, $process->id, 1);
        $before = $this->domainState();
        $snapshot = app(PagingService::class)->snapshot($observer, $scenario->id, $process->id);

        $this->assertStoredResult($fault, $snapshot['last_result']);
        $this->assertSame(0, $snapshot['last_result']['evicted']['page_number']);
        $this->assertSame($process->id, $snapshot['last_result']['evicted']['process_id']);
        $this->assertSame(2, $snapshot['page_fault_count']);
        Livewire::test(PageRequest::class, ['scenarioId' => $scenario->id, 'processId' => $process->id])
            ->assertSee('RAM llena: FIFO devuelve Chrome P0 a secundaria.')->assertNotDispatched('memory-accessed');
        $this->assertSame($before, $this->domainState());
    }

    public function test_stored_results_are_scoped_by_process_and_scenario_with_no_implicit_selection(): void
    {
        $admin = $this->user('administrador');
        [$first, $selected] = $this->pagingContext($admin);
        $otherProcess = app(ProcessManagerService::class)->create($admin, $first->id, ['name' => 'Otro proceso', 'size_kb' => 1]);
        [$second, $foreign] = $this->pagingContext($admin, ['name' => 'Otra memoria']);
        $selectedAccess = app(PagingService::class)->requestPage($admin, $first->id, $selected->id, 0);
        $otherAccess = app(PagingService::class)->requestPage($admin, $first->id, $otherProcess->id, 0);
        $foreignAccess = app(PagingService::class)->requestPage($admin, $second->id, $foreign->id, 0);
        $observer = $this->user('observador');
        $before = $this->domainState();
        $service = app(PagingService::class);

        $this->assertStoredResult($selectedAccess, $service->snapshot($observer, $first->id, $selected->id)['last_result']);
        $this->assertStoredResult($otherAccess, $service->snapshot($observer, $first->id, $otherProcess->id)['last_result']);
        $this->assertStoredResult($foreignAccess, $service->snapshot($observer, $second->id, $foreign->id)['last_result']);
        $unselected = $service->snapshot($observer, $first->id);
        $this->assertNull($unselected['last_result']);
        $this->assertNull($unselected['last_request']);
        $this->assertSame(2, $unselected['page_fault_count']);
        $this->assertSame(1, $service->snapshot($observer, $second->id)['page_fault_count']);
        $this->assertSame($before, $this->domainState());
    }

    public function test_the_latest_pending_request_hides_an_older_completed_result_without_resolving_anything(): void
    {
        $admin = $this->user('administrador');
        [$scenario, $process] = $this->pagingContext($admin);
        app(PagingService::class)->requestPage($admin, $scenario->id, $process->id, 0);
        $pending = app(PagingService::class)->beginRequest($admin, $scenario->id, $process->id, 1);
        $observer = $this->user('observador');
        $before = $this->domainState();

        $snapshot = app(PagingService::class)->snapshot($observer, $scenario->id, $process->id);

        $this->assertSame($pending['request_event_id'], $snapshot['last_request']['event_id']);
        $this->assertNull($snapshot['last_result']);
        Livewire::actingAs($observer)->test(PageRequest::class, ['scenarioId' => $scenario->id, 'processId' => $process->id])
            ->assertViewHas('displayResult', null)->assertViewHas('flowSteps', [])
            ->assertDontSee('Último acceso guardado')->call('$refresh');
        $this->assertSame($before, $this->domainState());
        $this->assertNull($process->pages()->where('page_number', 1)->firstOrFail()->frame_id);
    }

    public static function malformedPagingResults(): array
    {
        return array_map(fn ($kind) => [$kind], ['address', 'string-frame', 'foreign-process', 'incomplete', 'page', 'request-page', 'evicted', 'missing-evicted']);
    }

    #[DataProvider('malformedPagingResults')]
    public function test_malformed_stored_paging_metadata_returns_null_and_never_fabricates_a_result(string $kind): void
    {
        $admin = $this->user('administrador');
        [$scenario, $process] = $this->pagingContext($admin);
        $result = app(PagingService::class)->requestPage($admin, $scenario->id, $process->id, 0);
        $terminal = $scenario->events()->where('type', SimulationEventType::PageLoaded->value)->sole();
        $metadata = $terminal->metadata;
        if ($kind === 'address') {
            $metadata['result']['physical_address'] = 99;
        } elseif ($kind === 'string-frame') {
            $metadata['result']['frame_number'] = '0';
        } elseif ($kind === 'foreign-process') {
            $metadata['result']['process_id'] = 999999;
        } elseif ($kind === 'incomplete') {
            $metadata['result']['completed'] = false;
        } elseif ($kind === 'page') {
            $metadata['page_number'] = 1;
        } elseif ($kind === 'request-page') {
            $request = SimulationEvent::findOrFail($result['request_event_id']);
            $requestMetadata = $request->metadata;
            $requestMetadata['page_id'] = $process->pages()->where('page_number', 1)->value('id');
            $request->update(['metadata' => $requestMetadata]);
        } elseif ($kind === 'evicted') {
            $metadata['result']['evicted'] = [
                'page_id' => 999999, 'process_id' => $process->id, 'process_name' => $process->name,
                'page_number' => 1, 'frame_number' => 0,
            ];
            $metadata['evicted'] = $metadata['result']['evicted'];
        } else {
            unset($metadata['result']['evicted']);
        }
        $terminal->update(['metadata' => $metadata]);
        $before = $this->domainState();

        $snapshot = app(PagingService::class)->snapshot($this->user('observador'), $scenario->id, $process->id);

        $this->assertNull($snapshot['last_result']);
        $this->assertNotNull($snapshot['last_request']);
        $this->assertSame(1, $snapshot['page_fault_count']);
        $this->assertSame($before, $this->domainState());
    }

    public function test_an_operators_pending_step_flow_keeps_priority_over_another_actors_stored_access(): void
    {
        $admin = $this->user('administrador');
        [$scenario, $process] = $this->pagingContext($admin);
        $component = Livewire::actingAs($this->user('operador'))->test(PageRequest::class, ['scenarioId' => $scenario->id, 'processId' => $process->id])
            ->set('mode', 'step')->set('pageNumber', 1)->call('requestPage')->assertHasNoErrors()->assertSet('step', 1);
        app(PagingService::class)->requestPage($admin, $scenario->id, $process->id, 0);
        $before = $this->domainState();

        $component->call('$refresh')->assertSet('pending.page_number', 1)->assertSet('result', null)->assertSet('step', 1)
            ->assertViewHas('displayResult', null)->assertViewHas('currentStep', 1)->assertViewHas('sharedResult', false)
            ->assertViewHas('flowSteps', fn ($steps) => count($steps) === 8)->assertNotDispatched('memory-accessed');

        $this->assertNull($process->pages()->where('page_number', 1)->firstOrFail()->frame_id);
        $this->assertSame($before, $this->domainState());
    }

    public function test_local_result_keeps_priority_until_reset_clears_it_and_polling_performs_no_writes(): void
    {
        $admin = $this->user('administrador');
        [$scenario, $process] = $this->pagingContext($admin);
        $component = Livewire::actingAs($this->user('operador'))->test(PageRequest::class, ['scenarioId' => $scenario->id, 'processId' => $process->id])
            ->call('requestPage')->assertHasNoErrors()->assertSet('result.page_number', 0);
        app(PagingService::class)->requestPage($admin, $scenario->id, $process->id, 1);
        $before = $this->domainState();

        $component->call('$refresh')->assertViewHas('displayResult', fn ($result) => $result['page_number'] === 0)
            ->assertViewHas('sharedResult', false)->assertViewHas('snapshot', fn ($snapshot) => $snapshot['last_result']['page_number'] === 1);
        $this->assertSame($before, $this->domainState());
        app(SimulationService::class)->reset($admin, $scenario->id);
        $afterReset = $this->domainState();
        $component->call('$refresh')->assertSet('result', null)->assertSet('pending', null)->assertSet('step', 0)
            ->assertViewHas('displayResult', null)->assertViewHas('sharedResult', false);
        $this->assertNull(app(PagingService::class)->snapshot($this->user('observador'), $scenario->id, $process->id)['last_result']);
        $this->assertSame($afterReset, $this->domainState());
    }

    public function test_observer_recovers_shared_segmentation_success_and_fault_with_no_mutation(): void
    {
        $admin = $this->user('administrador');
        [$scenario, $process] = $this->segmentationContext($admin);
        $writer = $this->user('operador');
        $valid = app(SegmentationService::class)->access($writer, $scenario->id, $process->id, 0, 0);
        $observer = $this->user('observador');
        $before = $this->domainState();
        $service = app(SegmentationService::class);

        $this->assertStoredResult($valid, $service->snapshot($observer, $scenario->id, $process->id)['last_access']);
        Livewire::actingAs($observer)->test(SegmentAccess::class, ['scenarioId' => $scenario->id, 'processId' => $process->id])
            ->assertSet('result', null)->assertViewHas('sharedResult', true)
            ->assertViewHas('displayResult', fn ($result) => $result['valid'] === true && $result['physical_address'] === 1000)
            ->assertSee('Último acceso guardado')->assertSeeHtml('data-segment-physical')->call('$refresh');
        $this->assertSame($before, $this->domainState());
        $fault = $service->access($writer, $scenario->id, $process->id, 0, 1200);
        $beforeFaultRead = $this->domainState();
        $this->assertStoredResult($fault, $service->snapshot($observer, $scenario->id, $process->id)['last_access']);
        Livewire::test(SegmentAccess::class, ['scenarioId' => $scenario->id, 'processId' => $process->id])
            ->assertViewHas('displayResult', fn ($result) => $result['valid'] === false && $result['physical_address'] === null)
            ->assertSeeHtml('data-segment-outcome="SEGMENTATION_FAULT"')->assertDontSeeHtml('data-segment-physical');
        $this->assertNull($service->snapshot($observer, $scenario->id)['last_access']);
        $this->assertSame($beforeFaultRead, $this->domainState());
    }

    public static function malformedSegmentAccesses(): array
    {
        return array_map(fn ($kind) => [$kind], ['address', 'foreign-segment', 'offset', 'limit', 'outcome', 'process-name']);
    }

    #[DataProvider('malformedSegmentAccesses')]
    public function test_invalid_segment_metadata_is_hidden_and_does_not_fall_back_to_an_older_access(string $kind): void
    {
        $admin = $this->user('administrador');
        [$scenario, $process] = $this->segmentationContext($admin);
        $service = app(SegmentationService::class);
        $service->access($admin, $scenario->id, $process->id, 0, 0);
        $latest = $service->access($admin, $scenario->id, $process->id, 0, 1);
        $event = SimulationEvent::findOrFail($latest['event_id']);
        $metadata = $event->metadata;
        match ($kind) {
            'address' => $metadata['physical_address'] = 99,
            'foreign-segment' => $metadata['segment_id'] = 999999,
            'offset' => $metadata['offset'] = -1,
            'limit' => $metadata['last_valid_offset'] = 1200,
            'outcome' => $metadata['outcome'] = 'SEGMENTATION_FAULT',
            'process-name' => $metadata['process_name'] = 'Otro proceso',
        };
        $event->update(['metadata' => $metadata]);
        $before = $this->domainState();

        $this->assertNull($service->snapshot($this->user('observador'), $scenario->id, $process->id)['last_access']);

        $this->assertSame($before, $this->domainState());
    }

    public function test_segment_result_is_scoped_and_a_reset_hides_both_stored_and_local_access(): void
    {
        $admin = $this->user('administrador');
        [$scenario, $process] = $this->segmentationContext($admin);
        [$other, $foreign] = $this->segmentationContext($admin, 'Otra segmentacion');
        $foreignAccess = app(SegmentationService::class)->access($admin, $other->id, $foreign->id, 0, 100);
        $component = Livewire::actingAs($this->user('operador'))->test(SegmentAccess::class, ['scenarioId' => $scenario->id, 'processId' => $process->id])
            ->call('access')->assertHasNoErrors()->assertSet('result.valid', true);
        $observer = $this->user('observador');
        $this->assertStoredResult($foreignAccess, app(SegmentationService::class)->snapshot($observer, $other->id, $foreign->id)['last_access']);
        app(SimulationService::class)->reset($admin, $scenario->id);
        $before = $this->domainState();

        $this->assertNull(app(SegmentationService::class)->snapshot($observer, $scenario->id, $process->id)['last_access']);
        $component->call('$refresh')->assertSet('result', null)->assertViewHas('displayResult', null)->assertViewHas('sharedResult', false);
        $this->assertStoredResult($foreignAccess, app(SegmentationService::class)->snapshot($observer, $other->id, $foreign->id)['last_access']);
        $this->assertSame($before, $this->domainState());
    }

    public static function readerRoles(): array
    {
        return ['administrator' => ['administrador'], 'operator' => ['operador'], 'observer' => ['observador']];
    }

    #[DataProvider('readerRoles')]
    public function test_all_roles_can_open_the_presentation_layout_without_sidebar_or_administration_links(string $role): void
    {
        $actor = $this->user($role);
        $before = $this->domainState();
        $response = $this->actingAs($actor)->get(route('presentation.index'))->assertOk();
        $xpath = $this->xpath($response->getContent());

        $this->assertCount(1, $xpath->query('//body[contains(@class,"memorylab-presentation")]'));
        $this->assertCount(1, $xpath->query('//*[@data-presentation]'));
        $this->assertCount(0, $xpath->query('//*[@id="layout-menu"]'));
        $this->assertCount(0, $xpath->query('//a[contains(@href,"/administracion/usuarios")]'));
        $this->assertCount(0, $xpath->query('//a[contains(@href,"/memoria/configuracion")]'));
        $this->assertStringContainsString('requestFullscreen', $response->getContent());
        $this->assertSame($before, $this->domainState());
    }

    public function test_presentation_shows_five_live_panels_with_real_faults_pages_and_memory_without_admin_actions(): void
    {
        $admin = $this->user('administrador');
        [$scenario, $process] = $this->pagingContext($admin);
        app(PagingService::class)->requestPage($admin, $scenario->id, $process->id, 0);
        $before = $this->domainState();
        $component = Livewire::actingAs($this->user('observador'))->test(PagingSimulator::class, ['presentation' => true])
            ->set('scenarioId', $scenario->id)->set('processId', $process->id)
            ->assertSeeHtml('data-presentation')
            ->assertViewHas('snapshot', fn ($snapshot) => $snapshot['page_fault_count'] === 1 && $snapshot['ram']['used_bytes'] === 1024);
        $xpath = $this->xpath($component->html());

        foreach (['processes', 'cpu', 'pages', 'ram', 'secondary'] as $panel) {
            $this->assertCount(1, $xpath->query('//*[@data-paging-panel="'.$panel.'"]'));
        }
        $this->assertSame('1', trim($xpath->query('//*[@data-presentation-faults]')->item(0)->textContent));
        $this->assertCount(1, $xpath->query('//*[@data-paging-page="0"]//*[@data-page-frame="0"]'));
        $this->assertCount(1, $xpath->query('//*[@data-memory-frame="0"][@data-frame-state="OCCUPIED"]'));
        $this->assertCount(3, $xpath->query('//*[@data-secondary-page]'));
        $this->assertStringContainsString('wire:poll.5s.visible', $component->html());
        $this->assertStringNotContainsString('wire:submit="createProcess"', $component->html());
        $component->call('$refresh')->assertNotDispatched('memory-accessed');
        $this->assertSame($before, $this->domainState());
    }

    public function test_presentation_does_not_select_a_process_automatically_and_foreign_processes_produce_no_snapshot(): void
    {
        $admin = $this->user('administrador');
        [$first, $own] = $this->pagingContext($admin);
        [$second, $foreign] = $this->pagingContext($admin, ['name' => 'Otra memoria']);
        app(PagingService::class)->requestPage($admin, $second->id, $foreign->id, 0);
        session(['memorylab.active_scenario_id' => $first->id]);
        $before = $this->domainState();
        $component = Livewire::actingAs($this->user('observador'))->test(PagingSimulator::class, ['presentation' => true])
            ->assertSet('scenarioId', $first->id)->assertSet('processId', null)
            ->assertViewHas('snapshot', fn ($snapshot) => $snapshot['last_result'] === null && $snapshot['selected_process'] === null)
            ->set('processId', $own->id)->assertHasNoErrors();

        $component->set('processId', $foreign->id)->assertHasErrors(['processId'])->assertViewHas('snapshot', null)
            ->assertViewHas('pageRows', fn ($rows) => $rows->isEmpty());
        $component->set('scenarioId', $second->id)->assertSet('processId', null)
            ->assertViewHas('snapshot', fn ($snapshot) => $snapshot['selected_process'] === null && $snapshot['page_fault_count'] === 1);
        $this->assertSame($before, $this->domainState());
    }

    public function test_a_segmentation_session_is_preserved_without_becoming_the_presentation_scenario(): void
    {
        $admin = $this->user('administrador');
        [$paging] = $this->pagingContext($admin);
        [$segmentation] = $this->segmentationContext($admin);
        session(['memorylab.active_scenario_id' => $segmentation->id]);
        $before = $this->domainState();

        Livewire::actingAs($this->user('observador'))->test(PagingSimulator::class, ['presentation' => true])
            ->assertSet('scenarioId', null)->assertSet('processId', null)->assertViewHas('snapshot', null)
            ->assertViewHas('scenarios', fn ($scenarios) => $scenarios->pluck('id')->all() === [$paging->id]);

        $this->assertSame($segmentation->id, session('memorylab.active_scenario_id'));
        $this->assertSame($before, $this->domainState());
    }

    public static function missingPresentationPermissions(): array
    {
        return array_map(fn ($permission) => [$permission], ['memory.view', 'tables.view', 'simulations.view', 'results.view']);
    }

    #[DataProvider('missingPresentationPermissions')]
    public function test_route_and_presentation_component_require_each_of_the_four_read_permissions(string $missing): void
    {
        $actor = User::factory()->create();
        $actor->givePermissionTo(array_values(array_diff(['memory.view', 'tables.view', 'simulations.view', 'results.view'], [$missing])));
        $before = $this->domainState();

        $this->actingAs($actor)->get(route('presentation.index'))->assertForbidden();
        Livewire::actingAs($actor)->test(PagingSimulator::class, ['presentation' => true])->assertForbidden();
        $this->assertSame($before, $this->domainState());
    }

    public function test_results_permission_is_rechecked_on_poll_and_normal_paging_keeps_its_three_read_permissions(): void
    {
        $reader = User::factory()->create();
        $reader->givePermissionTo(['memory.view', 'tables.view', 'simulations.view', 'results.view']);
        $component = Livewire::actingAs($reader)->test(PagingSimulator::class, ['presentation' => true]);
        $reader->fresh()->revokePermissionTo('results.view');
        $before = $this->domainState();

        $component->call('$refresh')->assertForbidden();
        $normal = Livewire::test(PagingSimulator::class)->assertSet('presentation', false);
        $this->assertStringNotContainsString('data-presentation', $normal->html());
        $this->assertSame($before, $this->domainState());
    }

    public function test_presentation_mode_cannot_be_changed_by_the_client_and_the_route_requires_authentication(): void
    {
        $this->get(route('presentation.index'))->assertRedirect(route('login'));
        $component = Livewire::actingAs($this->user('administrador'))->test(PagingSimulator::class, ['presentation' => true]);
        $before = $this->domainState();

        try {
            $component->set('presentation', false);
            $this->fail('El cliente no puede cambiar la variante y sus permisos de lectura.');
        } catch (CannotUpdateLockedPropertyException) {
            $this->assertSame($before, $this->domainState());
        }
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function pagingContext(User $actor, array $input = []): array
    {
        $scenario = app(MemoryConfigurationService::class)->configure($actor, null, array_replace([
            'name' => 'Memoria de presentacion', 'ram_kb' => 2, 'page_kb' => 1, 'secondary_kb' => 64,
        ], $input));
        $process = app(ProcessManagerService::class)->create($actor, $scenario->id, ['name' => 'Chrome', 'size_kb' => 4]);

        return [$scenario, $process];
    }

    private function segmentationContext(User $actor, string $name = 'Segmentacion de presentacion'): array
    {
        $scenario = app(SegmentationService::class)->configure($actor, ['name' => $name, 'ram_kb' => 16]);
        $process = app(SegmentationService::class)->createProcess($actor, $scenario->id, ['name' => 'Editor', 'size_kb' => 4]);
        app(SegmentationService::class)->createSegment($actor, $scenario->id, $process->id, ['name' => 'Codigo', 'base' => 1000, 'size_bytes' => 1200]);

        return [$scenario, $process];
    }

    private function assertStoredResult(array $expected, ?array $stored): void
    {
        $this->assertNotNull($stored);
        $this->assertInstanceOf(CarbonImmutable::class, $stored['occurred_at']);
        unset($stored['occurred_at']);
        $this->assertEquals($expected, $stored);
    }

    private function domainState(): array
    {
        $state = [];
        foreach (['scenarios', 'memory_configurations', 'memory_frames', 'processes', 'pages', 'segments', 'simulation_events'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }

        return $state;
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8" ?>'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($document);
    }
}
