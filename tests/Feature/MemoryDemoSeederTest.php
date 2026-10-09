<?php

namespace Tests\Feature;

use App\Enums\ProcessStatus;
use App\Enums\RoleName;
use App\Enums\ScenarioStatus;
use App\Enums\SegmentStatus;
use App\Enums\SimulationEventType;
use App\Enums\SimulationMode;
use App\Models\Scenario;
use App\Models\SimulationEvent;
use App\Models\User;
use App\Services\MemoryConfigurationService;
use App\Services\PagingService;
use App\Services\ProcessManagerService;
use App\Services\SegmentationService;
use App\Services\SimulationService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\MemoryDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class MemoryDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_database_seeder_does_not_prepare_a_demo_even_when_an_administrator_exists(): void
    {
        $this->user(RoleName::Administrator);
        $before = $this->domainSignature();
        $accounts = $this->accountsSignature();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($before, $this->domainSignature());
        $this->assertSame($accounts, $this->accountsSignature());
        $this->assertSame(0, Scenario::count());
    }

    public function test_explicit_seeder_builds_canonical_paging_and_segmentation_using_the_lowest_existing_admin(): void
    {
        $this->user(RoleName::Observer);
        $firstAdmin = $this->user(RoleName::Administrator);
        $this->user(RoleName::Administrator);
        $accounts = $this->accountsSignature();

        $this->seed(MemoryDemoSeeder::class);

        [$paging, $segmentation] = $this->seededPair();
        $this->assertSame(2, Scenario::count());
        $this->assertSame(SimulationMode::Paging, $paging->mode);
        $this->assertSame(SimulationMode::Segmentation, $segmentation->mode);
        $this->assertSame($firstAdmin->id, $paging->created_by);
        $this->assertSame($firstAdmin->id, $segmentation->created_by);
        $this->assertTrue(SimulationEvent::get()->every(fn ($event) => $event->user_id === $firstAdmin->id));
        $this->assertSame($accounts, $this->accountsSignature());
        $this->assertSame(16384, $paging->configuration->ram_size_bytes);
        $this->assertSame(1024, $paging->configuration->page_size_bytes);
        $this->assertSame(65536, $paging->configuration->secondary_storage_bytes);
        $this->assertSame(16, $paging->frames()->count());
        $this->assertSame(range(0, 15), $paging->frames()->orderBy('frame_number')->pluck('frame_number')->all());
        $this->assertSame(15, $paging->pages()->count());
        $this->assertSame(5, $paging->pages()->whereNotNull('frame_id')->count());
        $this->assertSame(10, $paging->pages()->whereNull('frame_id')->count());
        $processes = $paging->processes()->orderBy('id')->get();
        $this->assertSame(['Chrome', 'Spotify', 'VS Code', 'MemoryLab'], $processes->pluck('name')->all());
        $this->assertSame([4096, 3072, 6144, 2048], $processes->pluck('size_bytes')->all());
        $loaded = $paging->pages()->whereNotNull('frame_id')->with(['process', 'frame'])->orderBy('frame_id')->get();
        $this->assertSame([
            ['Chrome', 0, 0], ['Chrome', 1, 1], ['Spotify', 0, 2], ['VS Code', 0, 3], ['VS Code', 1, 4],
        ], $loaded->map(fn ($page) => [$page->process->name, $page->page_number, $page->frame->frame_number])->all());
        $chrome = $processes->firstWhere('name', 'Chrome');
        $this->assertNull($chrome->pages()->where('page_number', 3)->firstOrFail()->frame_id);
        foreach ([SimulationEventType::PageRequest, SimulationEventType::PageFault, SimulationEventType::PageLoaded] as $type) {
            $this->assertSame(5, $paging->events()->where('type', $type->value)->count());
        }
        $pagingSnapshot = app(PagingService::class)->snapshot($firstAdmin, $paging->id);
        $this->assertSame(5120, $pagingSnapshot['ram']['used_bytes']);
        $this->assertSame(10240, $pagingSnapshot['secondary']['used_bytes']);
        $this->assertSame(16384, $segmentation->configuration->ram_size_bytes);
        $this->assertSame(0, $segmentation->configuration->secondary_storage_bytes);
        $this->assertSame(0, $segmentation->frames()->count());
        $this->assertSame(0, $segmentation->pages()->count());
        $editor = $segmentation->processes()->sole();
        $this->assertSame('Editor', $editor->name);
        $this->assertSame(4096, $editor->size_bytes);
        $segments = $editor->segments()->orderBy('segment_number')->get();
        $this->assertSame([0, 1, 2, 3], $segments->pluck('segment_number')->all());
        $this->assertSame([1000, 4000, 7000, 9000], $segments->pluck('base')->all());
        $this->assertSame([1200, 800, 600, 1000], $segments->pluck('size_bytes')->all());
        $this->assertTrue($segments->every(fn ($segment) => $segment->status === SegmentStatus::Active));
        $this->assertSame(3600, app(SegmentationService::class)->snapshot($firstAdmin, $segmentation->id)['ram']['used_bytes']);
    }

    public function test_running_the_explicit_seeder_twice_preserves_every_domain_table_account_and_password_hash(): void
    {
        $this->user(RoleName::Administrator);
        $this->user(RoleName::Operator);
        $this->seed(MemoryDemoSeeder::class);
        $before = $this->domainSignature();
        $accounts = $this->accountsSignature();

        $this->seed(MemoryDemoSeeder::class);

        $this->assertSame($before, $this->domainSignature());
        $this->assertSame($accounts, $this->accountsSignature());
        $this->assertSame(2, Scenario::count());
    }

    public function test_manual_scenarios_with_the_reserved_names_are_ignored_and_preserved(): void
    {
        $admin = $this->user(RoleName::Administrator);
        $this->user(RoleName::Observer);
        $paging = app(MemoryConfigurationService::class)->configure($admin, null, [
            'name' => MemoryDemoSeeder::PAGING_NAME, 'ram_kb' => 4, 'page_kb' => 1, 'secondary_kb' => 8,
        ]);
        $process = app(ProcessManagerService::class)->create($admin, $paging->id, ['name' => 'Proceso manual', 'size_kb' => 2]);
        app(PagingService::class)->requestPage($admin, $paging->id, $process->id, 0);
        $segmentation = app(SegmentationService::class)->configure($admin, ['name' => MemoryDemoSeeder::SEGMENTATION_NAME, 'ram_kb' => 8]);
        $pagingBefore = $this->scenarioSignature($paging);
        $segmentationBefore = $this->scenarioSignature($segmentation);
        $accounts = $this->accountsSignature();

        $this->seed(MemoryDemoSeeder::class);

        $this->assertSame(4, Scenario::count());
        $this->assertCount(2, $this->seededPair());
        $this->assertFalse($paging->fresh()->is_demo);
        $this->assertFalse($segmentation->fresh()->is_demo);
        $this->assertSame($pagingBefore, $this->scenarioSignature($paging));
        $this->assertSame($segmentationBefore, $this->scenarioSignature($segmentation));
        $this->assertSame($accounts, $this->accountsSignature());
    }

    public function test_unrelated_demonstrations_are_preserved_when_the_base_pair_is_created(): void
    {
        $admin = $this->user(RoleName::Administrator);
        $other = app(SimulationService::class)->startDemo($admin);
        $paging = Scenario::findOrFail($other['paging_scenario_id']);
        $segmentation = Scenario::findOrFail($other['segmentation_scenario_id']);
        $pagingBefore = $this->scenarioSignature($paging);
        $segmentationBefore = $this->scenarioSignature($segmentation);

        $this->seed(MemoryDemoSeeder::class);

        $this->assertSame(4, Scenario::count());
        $this->assertSame($pagingBefore, $this->scenarioSignature($paging));
        $this->assertSame($segmentationBefore, $this->scenarioSignature($segmentation));
    }

    public static function missingAdministrators(): array
    {
        return ['no accounts' => [false], 'only nonadministrative accounts' => [true]];
    }

    #[DataProvider('missingAdministrators')]
    public function test_missing_administrator_is_rejected_without_creating_accounts_roles_or_memory(bool $withAccounts): void
    {
        if ($withAccounts) {
            $this->user(RoleName::Observer);
            $this->user(RoleName::Operator);
        }
        $before = $this->domainSignature();
        $accounts = $this->accountsSignature();
        $roleCount = DB::table('roles')->count();

        $this->assertSeederRejected();

        $this->assertSame($before, $this->domainSignature());
        $this->assertSame($accounts, $this->accountsSignature());
        $this->assertSame($roleCount, DB::table('roles')->count());
        $this->assertSame(0, Scenario::count());
    }

    public static function pairSides(): array
    {
        return ['paging' => [true], 'segmentation' => [false]];
    }

    #[DataProvider('pairSides')]
    public function test_partial_base_pair_is_rejected_without_completing_or_resetting_it(bool $paging): void
    {
        $admin = $this->user(RoleName::Administrator);
        $scenario = $paging
            ? app(MemoryConfigurationService::class)->configure($admin, null, [
                'name' => MemoryDemoSeeder::PAGING_NAME, 'ram_kb' => 4, 'page_kb' => 1, 'secondary_kb' => 8,
            ])
            : app(SegmentationService::class)->configure($admin, ['name' => MemoryDemoSeeder::SEGMENTATION_NAME, 'ram_kb' => 8]);
        $scenario->update(['is_demo' => true]);
        $before = $this->domainSignature();
        $accounts = $this->accountsSignature();

        $this->assertSeederRejected();

        $this->assertSame($before, $this->domainSignature());
        $this->assertSame($accounts, $this->accountsSignature());
        $this->assertSame(1, Scenario::count());
    }

    #[DataProvider('pairSides')]
    public function test_ambiguous_base_pair_is_rejected_without_changing_any_existing_record(bool $paging): void
    {
        $admin = $this->user(RoleName::Administrator);
        $this->seed(MemoryDemoSeeder::class);
        [$pagingScenario, $segmentationScenario] = $this->seededPair();
        $target = $paging ? $pagingScenario : $segmentationScenario;
        Scenario::create([
            'name' => $target->name, 'mode' => $target->mode, 'status' => ScenarioStatus::Draft,
            'is_demo' => true, 'created_by' => $admin->id,
        ]);
        $before = $this->domainSignature();
        $accounts = $this->accountsSignature();

        $this->assertSeederRejected();

        $this->assertSame($before, $this->domainSignature());
        $this->assertSame($accounts, $this->accountsSignature());
        $this->assertSame(3, Scenario::count());
    }

    #[DataProvider('pairSides')]
    public function test_wrong_mode_in_the_base_pair_is_rejected_without_replacing_it(bool $paging): void
    {
        $this->user(RoleName::Administrator);
        $this->seed(MemoryDemoSeeder::class);
        [$pagingScenario, $segmentationScenario] = $this->seededPair();
        ($paging ? $pagingScenario : $segmentationScenario)->update(['mode' => SimulationMode::Contiguous]);
        $before = $this->domainSignature();

        $this->assertSeederRejected();

        $this->assertSame($before, $this->domainSignature());
    }

    public function test_reseeding_a_released_base_pair_does_not_resurrect_pages_segments_or_processes(): void
    {
        $admin = $this->user(RoleName::Administrator);
        $this->seed(MemoryDemoSeeder::class);
        [$paging, $segmentation] = $this->seededPair();
        app(SimulationService::class)->reset($admin, $paging->id);
        app(SimulationService::class)->reset($admin, $segmentation->id);
        $before = $this->domainSignature();

        $this->seed(MemoryDemoSeeder::class);

        $this->assertSame($before, $this->domainSignature());
        $this->assertSame(0, $paging->pages()->count());
        $this->assertTrue($paging->processes()->get()->every(fn ($process) => $process->status === ProcessStatus::Terminated));
        $this->assertTrue($segmentation->processes()->get()->every(fn ($process) => $process->status === ProcessStatus::Terminated));
        $this->assertTrue($segmentation->segments()->get()->every(fn ($segment) => $segment->status === SegmentStatus::Released));
        $this->assertSame(2, SimulationEvent::where('type', SimulationEventType::MemoryReset->value)->count());
    }

    public function test_reseeding_a_completed_base_pair_preserves_its_status_and_history(): void
    {
        $this->user(RoleName::Administrator);
        $this->seed(MemoryDemoSeeder::class);
        [$paging, $segmentation] = $this->seededPair();
        $paging->update(['status' => ScenarioStatus::Completed]);
        $segmentation->update(['status' => ScenarioStatus::Completed]);
        $before = $this->domainSignature();

        $this->seed(MemoryDemoSeeder::class);

        $this->assertSame($before, $this->domainSignature());
        $this->assertSame(ScenarioStatus::Completed, $paging->fresh()->status);
        $this->assertSame(ScenarioStatus::Completed, $segmentation->fresh()->status);
    }

    public function test_reseeding_preserves_accesses_performed_after_the_initial_preparation(): void
    {
        $admin = $this->user(RoleName::Administrator);
        $this->seed(MemoryDemoSeeder::class);
        [$paging, $segmentation] = $this->seededPair();
        $chrome = $paging->processes()->where('name', 'Chrome')->sole();
        app(PagingService::class)->requestPage($admin, $paging->id, $chrome->id, 3);
        $editor = $segmentation->processes()->sole();
        app(SegmentationService::class)->access($admin, $segmentation->id, $editor->id, 0, 1200);
        $before = $this->domainSignature();

        $this->seed(MemoryDemoSeeder::class);

        $this->assertSame($before, $this->domainSignature());
        $this->assertSame(6, $paging->pages()->whereNotNull('frame_id')->count());
        $this->assertSame(1, $segmentation->events()->where('type', SimulationEventType::SegmentationFault->value)->count());
    }

    #[DataProvider('pairSides')]
    public function test_inconsistent_existing_memory_is_rejected_without_repair_or_reset(bool $paging): void
    {
        $this->user(RoleName::Administrator);
        $this->seed(MemoryDemoSeeder::class);
        [$pagingScenario, $segmentationScenario] = $this->seededPair();
        if ($paging) {
            $pagingScenario->configuration()->update(['ram_size_bytes' => 8192]);
        } else {
            $segmentationScenario->segments()->where('segment_number', 1)->update(['base' => 1100]);
        }
        $before = $this->domainSignature();

        try {
            app(MemoryDemoSeeder::class)->run();
            $this->fail('Un snapshot inconsistente debe rechazarse sin reparar la memoria.');
        } catch (ValidationException) {
            $this->assertSame($before, $this->domainSignature());
        }
    }

    public function test_failure_while_preparing_the_second_scenario_rolls_back_the_entire_seed(): void
    {
        $this->user(RoleName::Administrator);
        $before = $this->domainSignature();
        $accounts = $this->accountsSignature();
        $dispatcher = SimulationEvent::getEventDispatcher();
        SimulationEvent::setEventDispatcher(clone $dispatcher);
        SimulationEvent::creating(function (SimulationEvent $event): void {
            if ($event->type === SimulationEventType::MemoryConfigured
                && ($event->metadata['mode'] ?? null) === SimulationMode::Segmentation->value) {
                throw new RuntimeException('Fallo de preparación de segmentación de prueba.');
            }
        });

        try {
            try {
                app(MemoryDemoSeeder::class)->run();
                $this->fail('Un fallo del segundo escenario debe revertir todo el par.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Fallo de preparación de segmentación de prueba.', $exception->getMessage());
            }
        } finally {
            SimulationEvent::setEventDispatcher($dispatcher);
        }

        $this->assertSame($before, $this->domainSignature());
        $this->assertSame($accounts, $this->accountsSignature());
        $this->assertSame(0, Scenario::count());
    }

    private function user(RoleName $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    /** @return array{Scenario,Scenario} */
    private function seededPair(): array
    {
        return [
            Scenario::where('is_demo', true)->where('name', MemoryDemoSeeder::PAGING_NAME)->sole(),
            Scenario::where('is_demo', true)->where('name', MemoryDemoSeeder::SEGMENTATION_NAME)->sole(),
        ];
    }

    /** @return array<string,string> */
    private function domainSignature(): array
    {
        $signatures = [];
        foreach (['scenarios', 'memory_configurations', 'memory_frames', 'processes', 'pages', 'segments', 'simulation_events'] as $table) {
            $signatures[$table] = hash('sha256', json_encode(DB::table($table)->orderBy('id')->get()->all(), JSON_THROW_ON_ERROR));
        }

        return $signatures;
    }

    /** @return array<string,string> */
    private function scenarioSignature(Scenario $scenario): array
    {
        $signatures = ['scenario' => hash('sha256', json_encode($scenario->fresh()->getAttributes(), JSON_THROW_ON_ERROR))];
        foreach (['memory_configurations', 'memory_frames', 'processes', 'pages', 'segments', 'simulation_events'] as $table) {
            $rows = DB::table($table)->where('scenario_id', $scenario->id)->orderBy('id')->get()->all();
            $signatures[$table] = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
        }

        return $signatures;
    }

    /** @return array<string,string> */
    private function accountsSignature(): array
    {
        return [
            'users' => hash('sha256', json_encode(DB::table('users')->orderBy('id')->get()->all(), JSON_THROW_ON_ERROR)),
            'roles' => hash('sha256', json_encode(DB::table('model_has_roles')->orderBy('model_type')->orderBy('model_id')->orderBy('role_id')->get()->all(), JSON_THROW_ON_ERROR)),
        ];
    }

    private function assertSeederRejected(): void
    {
        try {
            app(MemoryDemoSeeder::class)->run();
        } catch (RuntimeException $exception) {
            $this->assertNotSame('', $exception->getMessage());

            return;
        }

        $this->fail('El seeder debe rechazar el estado existente sin crear o reemplazar datos.');
    }
}
