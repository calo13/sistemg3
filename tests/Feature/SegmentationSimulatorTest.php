<?php

namespace Tests\Feature;

use App\Enums\ProcessStatus;
use App\Enums\ScenarioStatus;
use App\Enums\SegmentStatus;
use App\Enums\SimulationEventType;
use App\Enums\SimulationMode;
use App\Livewire\SegmentationSimulator;
use App\Models\Process;
use App\Models\Scenario;
use App\Models\Segment;
use App\Models\SimulationEvent;
use App\Models\User;
use App\Services\MemoryStatisticsService;
use App\Services\SegmentationService;
use DOMDocument;
use DOMXPath;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class SegmentationSimulatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_configuration_creates_a_segmentation_scenario_without_paging_memory(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin, ['name' => '  Memoria segmentada  ']);

        $this->assertSame('Memoria segmentada', $scenario->name);
        $this->assertSame(SimulationMode::Segmentation, $scenario->mode);
        $this->assertSame(ScenarioStatus::Ready, $scenario->status);
        $this->assertSame($admin->id, $scenario->created_by);
        $this->assertSame(16384, $scenario->configuration->ram_size_bytes);
        $this->assertSame(1024, $scenario->configuration->page_size_bytes);
        $this->assertSame(0, $scenario->configuration->secondary_storage_bytes);
        $this->assertDatabaseCount('memory_frames', 0);
        $this->assertDatabaseCount('pages', 0);
        $this->assertSame([
            SimulationEventType::ScenarioCreated, SimulationEventType::MemoryConfigured,
        ], $scenario->events()->orderBy('id')->get()->pluck('type')->all());
    }

    public static function writerRoles(): array
    {
        return ['administrator' => ['administrador'], 'operator' => ['operador']];
    }

    #[DataProvider('writerRoles')]
    public function test_writers_create_ready_processes_without_pages_and_active_segments_with_server_numbers(string $role): void
    {
        $scenario = $this->memory($this->user('administrador'));
        $actor = $this->user($role);
        $process = $this->process($actor, $scenario, ['name' => '  Editor  ']);
        $segment = $this->segment($actor, $scenario, $process, [
            'name' => '  Código  ', 'segment_number' => 99, 'status' => SegmentStatus::Released->value,
            'scenario_id' => 999999, 'process_id' => 999999,
        ]);

        $this->assertSame('Editor', $process->name);
        $this->assertSame(ProcessStatus::Ready, $process->status);
        $this->assertSame(4096, $process->size_bytes);
        $this->assertSame('Código', $segment->name);
        $this->assertSame(0, $segment->segment_number);
        $this->assertSame(SegmentStatus::Active, $segment->status);
        $this->assertSame($scenario->id, $segment->scenario_id);
        $this->assertSame($process->id, $segment->process_id);
        $this->assertSame($actor->id, $process->events()->sole()->user_id);
        $this->assertDatabaseCount('pages', 0);
        $this->assertDatabaseCount('memory_frames', 0);
        $this->assertDatabaseCount('simulation_events', 3);
    }

    public function test_adjacent_segments_and_global_gaps_produce_scoped_memory_blocks_and_real_statistics(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin, ['ram_kb' => 8]);
        $selected = $this->process($admin, $scenario, ['name' => 'Seleccionado']);
        $other = $this->process($admin, $scenario, ['name' => 'Otro proceso']);
        $code = $this->segment($admin, $scenario, $selected);
        $data = $this->segment($admin, $scenario, $selected, ['name' => 'Datos', 'base' => 1024, 'size_bytes' => 2048]);
        $foreign = $this->segment($admin, $scenario, $other, ['base' => 4096]);
        $before = $this->domainState();

        $snapshot = app(SegmentationService::class)->snapshot($this->user('observador'), $scenario->id, $selected->id);

        $this->assertSame([$code->id, $data->id], $snapshot['segments']->pluck('id')->all());
        $this->assertSame([$code->id, $data->id, $foreign->id], $snapshot['all_segments']->pluck('id')->all());
        $this->assertTrue($snapshot['all_segments']->every(fn (Segment $segment) => $segment->relationLoaded('process')));
        $this->assertSame(['total_bytes' => 8192, 'used_bytes' => 4096, 'available_bytes' => 4096], $snapshot['ram']);
        $this->assertSame([
            [0, 1024, 'ACTIVE', $code->id], [1024, 2048, 'ACTIVE', $data->id],
            [3072, 1024, 'FREE', null], [4096, 1024, 'ACTIVE', $foreign->id], [5120, 3072, 'FREE', null],
        ], array_map(fn ($block) => [$block['base'], $block['size_bytes'], $block['state'], $block['segment']?->id], $snapshot['blocks']));
        $statistics = app(MemoryStatisticsService::class)->forScenario($scenario);
        $this->assertEquals(8, $statistics['ram-total']);
        $this->assertEquals(4, $statistics['ram-used']);
        $this->assertEquals(4, $statistics['ram-available']);
        $this->assertSame(2, $statistics['processes-active']);
        foreach (['frames-total', 'frames-used', 'frames-free', 'page-faults'] as $key) {
            $this->assertNull($statistics[$key]);
        }
        $this->assertSame($before, $this->domainState());
    }

    public static function invalidSegments(): array
    {
        return [
            'blank name' => [['name' => '   '], 'name'],
            'name exceeds limit' => [['name' => str_repeat('a', 101)], 'name'],
            'negative base' => [['base' => -1], 'base'],
            'fractional base' => [['base' => 0.5], 'base'],
            'zero size' => [['size_bytes' => 0], 'size_bytes'],
            'negative size' => [['size_bytes' => -1], 'size_bytes'],
            'fractional size' => [['size_bytes' => 1.5], 'size_bytes'],
            'base at RAM end' => [['base' => 8192, 'size_bytes' => 1], 'base'],
            'range extends past RAM' => [['base' => 8191, 'size_bytes' => 2], 'base'],
            'size exceeds RAM' => [['size_bytes' => 8193], 'size_bytes'],
            'size exceeds process' => [['size_bytes' => 4097], 'size_bytes'],
        ];
    }

    #[DataProvider('invalidSegments')]
    public function test_invalid_segment_geometry_does_not_change_memory(array $input, string $error): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin, ['ram_kb' => 8]);
        $process = $this->process($admin, $scenario);
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => $this->segment($admin, $scenario, $process, $input), $error);

        $this->assertSame($before, $this->domainState());
    }

    public function test_global_overlap_is_rejected_even_for_another_process_and_process_size_limits_sum_active_segments(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $first = $this->process($admin, $scenario, ['size_kb' => 2]);
        $second = $this->process($admin, $scenario);
        $this->segment($admin, $scenario, $first, ['size_bytes' => 1536]);
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => $this->segment($admin, $scenario, $second, ['base' => 1024]), 'base');
        $this->assertValidationFailure(fn () => $this->segment($admin, $scenario, $first, ['base' => 1536, 'size_bytes' => 513]), 'size_bytes');

        $this->assertSame($before, $this->domainState());
    }

    public function test_last_ram_byte_is_valid_and_released_segments_do_not_occupy_space_or_reuse_numbers(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin, ['ram_kb' => 8]);
        $process = $this->process($admin, $scenario);
        $released = $this->segment($admin, $scenario, $process);
        $released->update(['status' => SegmentStatus::Released]);
        $active = $this->segment($admin, $scenario, $process, ['base' => 8191, 'size_bytes' => 1]);
        $reused = $this->segment($admin, $scenario, $process);

        $this->assertSame(1, $active->segment_number);
        $this->assertSame(2, $reused->segment_number);
        $snapshot = app(SegmentationService::class)->snapshot($this->user('observador'), $scenario->id, $process->id);
        $this->assertSame(3, $snapshot['segments']->count());
        $this->assertSame(2, $snapshot['all_segments']->count());
        $this->assertSame(1025, $snapshot['ram']['used_bytes']);
        $this->assertSame(7167, $snapshot['ram']['available_bytes']);
    }

    public function test_process_segment_limit_counts_released_records_and_accepts_the_last_slot(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $records = [];
        for ($number = 0; $number < 15; $number++) {
            $records[] = $this->releasedRecord($scenario, $process, $number);
        }
        Segment::insert($records);

        $last = $this->segment($admin, $scenario, $process, ['size_bytes' => 1]);
        $this->assertSame(15, $last->segment_number);
        $before = $this->domainState();
        $this->assertValidationFailure(fn () => $this->segment($admin, $scenario, $process, ['base' => 1, 'size_bytes' => 1]), 'process_id');

        $this->assertSame($before, $this->domainState());
        $this->assertSame(16, $process->segments()->count());
    }

    public function test_scenario_segment_limit_counts_released_records_from_other_processes(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $records = [];
        for ($processNumber = 0; $processNumber < 64; $processNumber++) {
            $process = Process::create([
                'scenario_id' => $scenario->id, 'name' => 'Histórico '.$processNumber,
                'size_bytes' => 1024, 'status' => ProcessStatus::Ready,
            ]);
            $count = $processNumber === 63 ? 15 : 16;
            for ($number = 0; $number < $count; $number++) {
                $records[] = $this->releasedRecord($scenario, $process, $number);
            }
        }
        Segment::insert($records);
        $selected = $this->process($admin, $scenario);
        $this->segment($admin, $scenario, $selected, ['size_bytes' => 1]);
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => $this->segment($admin, $scenario, $selected, ['base' => 1, 'size_bytes' => 1]), 'scenario_id');

        $this->assertSame($before, $this->domainState());
        $this->assertDatabaseCount('segments', 1024);
    }

    public function test_foreign_or_terminated_processes_cannot_receive_segments(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $other = $this->memory($admin, ['name' => 'Otra memoria']);
        $foreign = $this->process($admin, $other);
        $this->assertValidationFailure(fn () => $this->segment($admin, $scenario, $foreign), 'process_id');
        $process->update(['status' => ProcessStatus::Terminated]);
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => $this->segment($admin, $scenario, $process), 'process_id');

        $this->assertSame($before, $this->domainState());
    }

    public function test_observer_and_revoked_operator_cannot_change_segmentation_memory(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $operator = $this->user('operador');
        $this->assertTrue($operator->can('segmentation.execute'));
        $operator->fresh()->syncRoles(['observador']);
        $before = $this->domainState();

        foreach ([$this->user('observador'), $operator] as $actor) {
            try {
                $this->segment($actor, $scenario, $process);
                $this->fail('La consulta de memoria no permite crear segmentos.');
            } catch (AuthorizationException) {
                $this->assertSame($before, $this->domainState());
            }
        }
        try {
            $this->memory($this->user('operador'));
            $this->fail('La configuración requiere permisos de administrador.');
        } catch (AuthorizationException) {
            $this->assertSame($before, $this->domainState());
        }
    }

    public static function missingSegmentPermissions(): array
    {
        return ['segmentation' => ['segmentation.execute'], 'simulations' => ['simulations.execute']];
    }

    #[DataProvider('missingSegmentPermissions')]
    public function test_both_execution_permissions_are_required_to_create_a_segment(string $missing): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $actor = User::factory()->create();
        $actor->givePermissionTo(array_values(array_diff([
            'memory.view', 'tables.view', 'simulations.view', 'segmentation.execute', 'simulations.execute',
        ], [$missing])));
        $before = $this->domainState();

        try {
            $this->segment($actor, $scenario, $process);
            $this->fail('La asignación de segmentos requiere ambos permisos de ejecución.');
        } catch (AuthorizationException) {
            $this->assertSame($before, $this->domainState());
        }
    }

    public function test_segmentation_rejects_other_modes_and_configuration_with_secondary_storage(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $scenario->update(['mode' => SimulationMode::Paging]);
        $this->assertValidationFailure(fn () => $this->segment($admin, $scenario, $process), 'scenario_id');
        $scenario->update(['mode' => SimulationMode::Segmentation]);
        $scenario->configuration->update(['secondary_storage_bytes' => 1024]);
        $before = $this->domainState();

        $this->assertValidationFailure(fn () => app(SegmentationService::class)->snapshot($this->user('observador'), $scenario->id), 'scenario_id');

        $this->assertSame($before, $this->domainState());
    }

    public function test_process_event_failure_rolls_back_the_new_process_without_creating_pages(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $before = $this->domainState();
        $dispatcher = SimulationEvent::getEventDispatcher();
        SimulationEvent::setEventDispatcher(clone $dispatcher);
        SimulationEvent::creating(function (SimulationEvent $event): void {
            if ($event->type === SimulationEventType::ProcessCreated) {
                throw new RuntimeException('Falló el evento del proceso segmentado de prueba.');
            }
        });

        try {
            try {
                $this->process($admin, $scenario);
                $this->fail('El proceso debe revertirse si no se registra su evento.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Falló el evento del proceso segmentado de prueba.', $exception->getMessage());
            }
        } finally {
            SimulationEvent::setEventDispatcher($dispatcher);
        }

        $this->assertSame($before, $this->domainState());
    }

    public function test_read_only_snapshot_handles_completed_scenarios_without_implicit_process_selection(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $this->segment($admin, $scenario, $process);
        $scenario->update(['status' => ScenarioStatus::Completed]);
        $before = $this->domainState();

        $snapshot = app(SegmentationService::class)->snapshot($this->user('observador'), $scenario->id);

        $this->assertNull($snapshot['selected_process']);
        $this->assertTrue($snapshot['segments']->isEmpty());
        $this->assertCount(1, $snapshot['all_segments']);
        $this->assertSame(ScenarioStatus::Completed, $snapshot['scenario']->status);
        $this->assertSame($before, $this->domainState());
    }

    public function test_route_requires_authentication_and_allows_the_three_roles_to_read(): void
    {
        $this->get('/segmentacion')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/segmentacion')->assertForbidden();
        foreach (['administrador', 'operador', 'observador'] as $role) {
            $this->actingAs($this->user($role))->get('/segmentacion')->assertOk();
        }
    }

    public function test_administrator_can_configure_a_scenario_create_a_process_and_create_a_segment_from_the_form(): void
    {
        $admin = $this->user('administrador');

        Livewire::actingAs($admin)->test(SegmentationSimulator::class)
            ->set('scenarioName', 'Memoria desde el panel')->set('ramKb', 8)->call('configure')->assertHasNoErrors()
            ->set('processName', 'Editor')->set('processSizeKb', 4)->call('createProcess')->assertHasNoErrors()
            ->set('segmentName', 'Código')->set('segmentBase', 0)->set('segmentSize', 1024)
            ->call('createSegment')->assertHasNoErrors();

        $scenario = Scenario::sole();
        $this->assertSame(SimulationMode::Segmentation, $scenario->mode);
        $this->assertSame($scenario->id, session('memorylab.active_scenario_id'));
        $this->assertSame(1, $scenario->processes()->count());
        $this->assertSame(1, $scenario->segments()->count());
        $this->assertDatabaseCount('pages', 0);
        $this->assertDatabaseCount('memory_frames', 0);
    }

    public function test_observer_sees_no_mutation_forms_and_forged_actions_are_forbidden(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $observer = $this->user('observador');
        $before = $this->domainState();

        foreach (['configure', 'createProcess', 'createSegment'] as $action) {
            $component = Livewire::actingAs($observer)->test(SegmentationSimulator::class)
                ->set('scenarioId', (string) $scenario->id)->set('processId', (string) $process->id)
                ->assertViewHas('canConfigure', false)->assertViewHas('canCreateProcess', false)->assertViewHas('canCreateSegment', false);
            $this->assertStringNotContainsString('wire:submit=', $component->html());
            $component->call($action)->assertForbidden();
            $this->assertSame($before, $this->domainState());
        }
    }

    public function test_operator_reads_memory_blocks_and_segment_table_with_safe_names_and_geometry_errors(): void
    {
        $admin = $this->user('administrador');
        $scenario = $this->memory($admin);
        $process = $this->process($admin, $scenario);
        $unsafeName = '<img src=x onerror="alert(1)">';
        $this->segment($admin, $scenario, $process, ['name' => $unsafeName]);
        $component = Livewire::actingAs($this->user('operador'))->test(SegmentationSimulator::class)
            ->set('scenarioId', (string) $scenario->id)->set('processId', (string) $process->id)
            ->assertViewHas('canConfigure', false)->assertViewHas('canCreateProcess', true)->assertViewHas('canCreateSegment', true)
            ->assertSee($unsafeName);
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8" ?>'.$component->html(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $this->assertCount(0, $xpath->query('//*[@data-segment-number]//img'));
        $this->assertCount(1, $xpath->query('//*[@data-segment-block="0"][@data-segment-state="ACTIVE"]'));
        $this->assertCount(1, $xpath->query('//*[@data-segment-number="0"]'));
        $this->assertSame('0', trim($xpath->query('//*[@data-segment-number="0"]//*[@data-segment-base]')->item(0)->textContent));
        $this->assertSame('1024 bytes', trim($xpath->query('//*[@data-segment-number="0"]//*[@data-segment-size]')->item(0)->textContent));
        $before = $this->domainState();

        $component->set('segmentName', 'Datos')->set('segmentBase', 512)->set('segmentSize', 1024)
            ->call('createSegment')->assertHasErrors(['segmentBase']);
        $component->set('segmentBase', 1024)->set('segmentSize', 4097)
            ->call('createSegment')->assertHasErrors(['segmentSize']);

        $this->assertSame($before, $this->domainState());
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function memory(User $actor, array $input = []): Scenario
    {
        return app(SegmentationService::class)->configure($actor, array_replace(['name' => 'Memoria segmentada', 'ram_kb' => 16], $input));
    }

    private function process(User $actor, Scenario $scenario, array $input = []): Process
    {
        return app(SegmentationService::class)->createProcess($actor, $scenario->id, array_replace(['name' => 'Proceso segmentado', 'size_kb' => 4], $input));
    }

    private function segment(User $actor, Scenario $scenario, Process $process, array $input = []): Segment
    {
        return app(SegmentationService::class)->createSegment($actor, $scenario->id, $process->id, array_replace([
            'name' => 'Código', 'base' => 0, 'size_bytes' => 1024,
        ], $input));
    }

    private function releasedRecord(Scenario $scenario, Process $process, int $number): array
    {
        return [
            'scenario_id' => $scenario->id, 'process_id' => $process->id,
            'segment_number' => $number, 'name' => 'Liberado '.$number,
            'base' => 0, 'size_bytes' => 1, 'status' => SegmentStatus::Released->value,
            'created_at' => now(), 'updated_at' => now(),
        ];
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
