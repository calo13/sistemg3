<?php

namespace Tests\Feature;

use App\Enums\ProcessStatus;
use App\Enums\ScenarioStatus;
use App\Enums\SegmentStatus;
use App\Enums\SimulationEventType;
use App\Enums\SimulationMode;
use App\Models\MemoryConfiguration;
use App\Models\MemoryFrame;
use App\Models\Page;
use App\Models\Process;
use App\Models\Scenario;
use App\Models\Segment;
use App\Models\SimulationEvent;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MemorySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_models_reconstruct_a_scenario_with_its_configuration_memory_and_history(): void
    {
        $user = User::factory()->create();
        $scenario = $this->scenario(['created_by' => $user->id]);
        $configuration = $this->configuration($scenario);
        $process = $this->process($scenario);
        $frame = $this->frame($scenario);
        $page = $this->page($scenario, $process, ['frame_id' => $frame->id]);
        $segment = $this->segment($scenario, $process);
        $event = $this->event($scenario, [
            'process_id' => $process->id,
            'user_id' => $user->id,
            'metadata' => ['page_number' => 0, 'hit' => false],
            'occurred_at' => '2026-10-07 12:34:56.123456',
        ]);

        $scenario = $scenario->fresh();
        $this->assertTrue($scenario->creator->is($user));
        $this->assertTrue($scenario->configuration->is($configuration));
        $this->assertTrue($scenario->processes->sole()->is($process));
        $this->assertTrue($scenario->frames->sole()->is($frame));
        $this->assertTrue($scenario->pages->sole()->is($page));
        $this->assertTrue($scenario->segments->sole()->is($segment));
        $this->assertTrue($scenario->events->sole()->is($event));
        $this->assertSame(SimulationMode::Paging, $scenario->mode);
        $this->assertSame(ScenarioStatus::Draft, $scenario->status);
        $this->assertFalse($scenario->is_demo);

        $configuration = $configuration->fresh();
        $this->assertTrue($configuration->scenario->is($scenario));
        $this->assertSame(4096, $configuration->ram_size_bytes);
        $this->assertSame(1024, $configuration->page_size_bytes);
        $this->assertSame(8192, $configuration->secondary_storage_bytes);
        $this->assertSame(4, $configuration->frame_count);

        $process = $process->fresh();
        $this->assertTrue($process->scenario->is($scenario));
        $this->assertTrue($process->pages->sole()->is($page));
        $this->assertTrue($process->segments->sole()->is($segment));
        $this->assertTrue($process->events->sole()->is($event));
        $this->assertSame(ProcessStatus::Ready, $process->status);
        $this->assertSame(2048, $process->size_bytes);

        $page = $page->fresh();
        $frame = $frame->fresh();
        $this->assertTrue($page->scenario->is($scenario));
        $this->assertTrue($page->process->is($process));
        $this->assertTrue($page->frame->is($frame));
        $this->assertTrue($frame->scenario->is($scenario));
        $this->assertTrue($frame->page->is($page));
        $this->assertSame(0, $page->page_number);
        $this->assertSame(0, $frame->frame_number);
        $this->assertTrue($page->present);

        $segment = $segment->fresh();
        $this->assertTrue($segment->scenario->is($scenario));
        $this->assertTrue($segment->process->is($process));
        $this->assertSame(SegmentStatus::Active, $segment->status);
        $this->assertSame(0, $segment->base);
        $this->assertSame(1024, $segment->size_bytes);
        $this->assertSame(0, $segment->segment_number);

        $event = $event->fresh();
        $this->assertTrue($event->scenario->is($scenario));
        $this->assertTrue($event->process->is($process));
        $this->assertTrue($event->user->is($user));
        $this->assertSame(SimulationEventType::PageFault, $event->type);
        $this->assertCount(2, $event->metadata);
        $this->assertSame(0, $event->metadata['page_number']);
        $this->assertFalse($event->metadata['hit']);
        $this->assertInstanceOf(DateTimeInterface::class, $event->occurred_at);
        $this->assertSame('2026-10-07 12:34:56.123456', $event->occurred_at->format('Y-m-d H:i:s.u'));
    }

    public function test_frame_count_and_page_presence_are_derived_from_their_source_values(): void
    {
        $scenario = $this->scenario();
        $configuration = $this->configuration($scenario);
        $process = $this->process($scenario);
        $page = $this->page($scenario, $process);
        $frame = $this->frame($scenario);

        $this->assertFalse(Schema::hasColumn('memory_configurations', 'frame_count'));
        $this->assertFalse(Schema::hasColumn('pages', 'present'));
        $this->assertNull((new MemoryConfiguration(['ram_size_bytes' => 1536, 'page_size_bytes' => 1024]))->frame_count);
        $this->assertNull((new MemoryConfiguration(['ram_size_bytes' => 512, 'page_size_bytes' => 1024]))->frame_count);
        $this->assertSame(4, $configuration->frame_count);
        $configuration->update(['page_size_bytes' => 512]);
        $this->assertSame(8, $configuration->fresh()->frame_count);
        $this->assertFalse($page->fresh()->present);
        $page->update(['frame_id' => $frame->id]);
        $this->assertTrue($page->fresh()->present);
        $page->update(['frame_id' => null]);
        $this->assertFalse($page->fresh()->present);
    }

    public function test_byte_counts_support_values_above_two_gigabytes_without_allocating_frames(): void
    {
        $scenario = $this->scenario();
        $configuration = $this->configuration($scenario, [
            'ram_size_bytes' => 3221225472,
            'page_size_bytes' => 1048576,
            'secondary_storage_bytes' => 4294967295,
        ]);
        $process = $this->process($scenario, ['size_bytes' => 3221225472]);
        $segment = $this->segment($scenario, $process, ['base' => 2147483648, 'size_bytes' => 1073741824]);

        $this->assertSame(3221225472, $configuration->fresh()->ram_size_bytes);
        $this->assertSame(4294967295, $configuration->fresh()->secondary_storage_bytes);
        $this->assertSame(3072, $configuration->fresh()->frame_count);
        $this->assertSame(3221225472, $process->fresh()->size_bytes);
        $this->assertSame(2147483648, $segment->fresh()->base);
        $this->assertDatabaseCount('memory_frames', 0);
    }

    public static function uniqueRecords(): array
    {
        return [
            'one configuration per scenario' => ['memory_configurations'],
            'one frame number per scenario' => ['memory_frames'],
            'one page number per process' => ['pages'],
            'one segment number per process' => ['segments'],
        ];
    }

    #[DataProvider('uniqueRecords')]
    public function test_configuration_and_memory_positions_cannot_be_duplicated(string $table): void
    {
        $scenario = $this->scenario();
        $process = $this->process($scenario);
        $record = match ($table) {
            'memory_configurations' => $this->configuration($scenario),
            'memory_frames' => $this->frame($scenario),
            'pages' => $this->page($scenario, $process),
            'segments' => $this->segment($scenario, $process),
        };
        $attributes = $record->getAttributes();
        unset($attributes['id']);

        $this->assertQueryRejected(fn () => DB::table($table)->insert($attributes), [1062]);
        $this->assertDatabaseCount($table, 1);
    }

    public function test_a_frame_hosts_only_one_page_while_multiple_pages_can_be_absent(): void
    {
        $scenario = $this->scenario();
        $first = $this->process($scenario);
        $second = $this->process($scenario);
        $frame = $this->frame($scenario);
        $this->page($scenario, $first, ['frame_id' => $frame->id]);

        $this->assertQueryRejected(fn () => $this->page($scenario, $second, ['frame_id' => $frame->id]), [1062]);
        $this->assertFalse($this->page($scenario, $first, ['page_number' => 1])->present);
        $this->assertFalse($this->page($scenario, $second, ['page_number' => 1])->present);
        $this->assertDatabaseCount('pages', 3);
    }

    public function test_position_numbers_are_local_to_their_scenario_or_process(): void
    {
        $firstScenario = $this->scenario();
        $secondScenario = $this->scenario();
        $firstProcess = $this->process($firstScenario);
        $secondProcess = $this->process($firstScenario);

        $this->frame($firstScenario);
        $this->frame($secondScenario);
        $this->page($firstScenario, $firstProcess);
        $this->page($firstScenario, $secondProcess);
        $this->segment($firstScenario, $firstProcess);
        $this->segment($firstScenario, $secondProcess);

        $this->assertDatabaseCount('memory_frames', 2);
        $this->assertDatabaseCount('pages', 2);
        $this->assertDatabaseCount('segments', 2);
    }

    public static function crossScenarioReferences(): array
    {
        return [
            'page process' => ['pages', 'process_id'],
            'page frame' => ['pages', 'frame_id'],
            'segment process' => ['segments', 'process_id'],
            'event process' => ['simulation_events', 'process_id'],
        ];
    }

    #[DataProvider('crossScenarioReferences')]
    public function test_references_cannot_link_memory_or_history_to_another_scenario(string $table, string $column): void
    {
        $scenario = $this->scenario();
        $otherScenario = $this->scenario();
        $process = $this->process($scenario);
        $otherProcess = $this->process($otherScenario);
        $otherFrame = $this->frame($otherScenario);
        $record = match ($table) {
            'pages' => $this->page($scenario, $process),
            'segments' => $this->segment($scenario, $process),
            'simulation_events' => $this->event($scenario),
        };

        $this->assertQueryRejected(fn () => DB::table($table)->where('id', $record->id)->update([
            $column => $column === 'frame_id' ? $otherFrame->id : $otherProcess->id,
        ]), [1452]);
    }

    public static function invalidNumbersAndNames(): array
    {
        return [
            'zero RAM' => ['memory_configurations', ['ram_size_bytes' => 0], [3819]],
            'zero page size' => ['memory_configurations', ['page_size_bytes' => 0], [3819]],
            'page larger than RAM' => ['memory_configurations', ['page_size_bytes' => 8192], [3819]],
            'RAM not divisible by page size' => ['memory_configurations', ['ram_size_bytes' => 4097], [3819]],
            'negative RAM' => ['memory_configurations', ['ram_size_bytes' => -1], [1264]],
            'negative page size' => ['memory_configurations', ['page_size_bytes' => -1], [1264]],
            'negative secondary storage' => ['memory_configurations', ['secondary_storage_bytes' => -1], [1264]],
            'zero process size' => ['processes', ['size_bytes' => 0], [3819]],
            'negative process size' => ['processes', ['size_bytes' => -1], [1264]],
            'negative frame number' => ['memory_frames', ['frame_number' => -1], [1264]],
            'negative page number' => ['pages', ['page_number' => -1], [1264]],
            'zero segment size' => ['segments', ['size_bytes' => 0], [3819]],
            'negative segment size' => ['segments', ['size_bytes' => -1], [1264]],
            'negative segment base' => ['segments', ['base' => -1], [1264]],
            'negative segment number' => ['segments', ['segment_number' => -1], [1264]],
            'empty scenario name' => ['scenarios', ['name' => ''], [3819]],
            'blank scenario name' => ['scenarios', ['name' => '   '], [3819]],
            'empty process name' => ['processes', ['name' => ''], [3819]],
            'blank process name' => ['processes', ['name' => '   '], [3819]],
            'empty segment name' => ['segments', ['name' => ''], [3819]],
            'blank segment name' => ['segments', ['name' => '   '], [3819]],
        ];
    }

    #[DataProvider('invalidNumbersAndNames')]
    public function test_database_rejects_invalid_sizes_positions_and_blank_names(string $table, array $changes, array $codes): void
    {
        $scenario = $this->scenario();
        $process = $this->process($scenario);
        $record = match ($table) {
            'scenarios' => $scenario,
            'memory_configurations' => $this->configuration($scenario),
            'processes' => $process,
            'memory_frames' => $this->frame($scenario),
            'pages' => $this->page($scenario, $process),
            'segments' => $this->segment($scenario, $process),
        };

        $this->assertQueryRejected(fn () => DB::table($table)->where('id', $record->id)->update($changes), $codes);
    }

    public static function invalidEnumColumns(): array
    {
        return [
            'scenario mode' => ['scenarios', 'mode'],
            'scenario status' => ['scenarios', 'status'],
            'process status' => ['processes', 'status'],
            'segment status' => ['segments', 'status'],
            'event type' => ['simulation_events', 'type'],
        ];
    }

    #[DataProvider('invalidEnumColumns')]
    public function test_database_rejects_unsupported_modes_states_and_event_types(string $table, string $column): void
    {
        $scenario = $this->scenario();
        $process = $this->process($scenario);
        $record = match ($table) {
            'scenarios' => $scenario,
            'processes' => $process,
            'segments' => $this->segment($scenario, $process),
            'simulation_events' => $this->event($scenario),
        };

        $this->assertQueryRejected(fn () => DB::table($table)->where('id', $record->id)->update([$column => 'UNSUPPORTED']), [1265]);
    }

    public function test_history_keeps_its_process_reference_and_cannot_be_removed_by_deleting_the_process(): void
    {
        $scenario = $this->scenario();
        $process = $this->process($scenario);
        $event = $this->event($scenario, ['process_id' => $process->id]);

        $this->assertQueryRejected(fn () => $process->delete(), [1451]);
        $this->assertTrue($event->fresh()->process->is($process));
        $this->assertDatabaseCount('simulation_events', 1);
        $this->assertDatabaseCount('processes', 1);
    }

    public function test_deleting_a_user_keeps_scenarios_and_events_without_the_deleted_identity(): void
    {
        $user = User::factory()->create();
        $scenario = $this->scenario(['created_by' => $user->id]);
        $event = $this->event($scenario, ['user_id' => $user->id]);

        $user->delete();

        $this->assertNull($scenario->fresh()->created_by);
        $this->assertNull($scenario->fresh()->creator);
        $this->assertNull($event->fresh()->user_id);
        $this->assertNull($event->fresh()->user);
        $this->assertDatabaseCount('scenarios', 1);
        $this->assertDatabaseCount('simulation_events', 1);
    }

    public function test_scenario_events_can_exist_without_a_process_and_without_optional_metadata(): void
    {
        $scenario = $this->scenario();
        $event = $this->event($scenario, ['type' => SimulationEventType::ScenarioCreated]);

        $this->assertNull($event->fresh()->process_id);
        $this->assertNull($event->fresh()->process);
        $this->assertNull($event->fresh()->metadata);
        $this->assertSame(SimulationEventType::ScenarioCreated, $event->fresh()->type);
    }

    public function test_event_metadata_must_be_valid_json_even_when_written_without_a_model(): void
    {
        $event = $this->event($this->scenario());

        $this->assertQueryRejected(fn () => DB::table('simulation_events')->where('id', $event->id)->update([
            'metadata' => '{invalid-json}',
        ]), DB::connection()->isMaria() ? [4025] : [3140]);
        $this->assertNull($event->fresh()->metadata);
    }

    public function test_a_scenario_with_history_cannot_be_deleted_implicitly(): void
    {
        $scenario = $this->scenario();
        $this->event($scenario);

        $this->assertQueryRejected(fn () => $scenario->delete(), [1451]);
        $this->assertDatabaseCount('scenarios', 1);
        $this->assertDatabaseCount('simulation_events', 1);
    }

    private function scenario(array $attributes = []): Scenario
    {
        return Scenario::create(array_replace(['name' => 'Escenario de prueba'], $attributes));
    }

    private function configuration(Scenario $scenario, array $attributes = []): MemoryConfiguration
    {
        return MemoryConfiguration::create(array_replace([
            'scenario_id' => $scenario->id,
            'ram_size_bytes' => 4096,
            'page_size_bytes' => 1024,
            'secondary_storage_bytes' => 8192,
        ], $attributes));
    }

    private function process(Scenario $scenario, array $attributes = []): Process
    {
        return Process::create(array_replace([
            'scenario_id' => $scenario->id,
            'name' => 'Proceso de prueba',
            'size_bytes' => 2048,
        ], $attributes));
    }

    private function frame(Scenario $scenario, array $attributes = []): MemoryFrame
    {
        return MemoryFrame::create(array_replace([
            'scenario_id' => $scenario->id,
            'frame_number' => 0,
        ], $attributes));
    }

    private function page(Scenario $scenario, Process $process, array $attributes = []): Page
    {
        return Page::create(array_replace([
            'scenario_id' => $scenario->id,
            'process_id' => $process->id,
            'page_number' => 0,
            'frame_id' => null,
        ], $attributes));
    }

    private function segment(Scenario $scenario, Process $process, array $attributes = []): Segment
    {
        return Segment::create(array_replace([
            'scenario_id' => $scenario->id,
            'process_id' => $process->id,
            'segment_number' => 0,
            'name' => 'Código',
            'base' => 0,
            'size_bytes' => 1024,
        ], $attributes));
    }

    private function event(Scenario $scenario, array $attributes = []): SimulationEvent
    {
        return SimulationEvent::create(array_replace([
            'scenario_id' => $scenario->id,
            'type' => SimulationEventType::PageFault,
            'description' => 'Fallo de página de prueba.',
            'occurred_at' => '2026-10-07 12:00:00.000000',
        ], $attributes));
    }

    private function assertQueryRejected(callable $operation, array $expectedCodes): void
    {
        if (DB::connection()->isMaria()) {
            $expectedCodes = array_map(fn (int $code) => $code === 3819 ? 4025 : $code, $expectedCodes);
        }

        try {
            $operation();
        } catch (QueryException $exception) {
            $this->assertContains((int) $exception->errorInfo[1], $expectedCodes);

            return;
        }

        $this->fail('La base de datos debe rechazar la operación que rompe la integridad del escenario.');
    }
}
