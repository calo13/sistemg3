<?php

namespace Tests\Feature;

use App\Livewire\MemoryComparison;
use App\Models\User;
use App\Services\MemoryComparisonService;
use App\Services\MemoryConfigurationService;
use App\Services\ProcessManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MemoryComparisonTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_request_exposes_external_fragmentation_and_successful_paging_and_segmentation(): void
    {
        $comparison = app(MemoryComparisonService::class)->compare(7168);

        $this->assertSame(16384, $comparison['ram_size_bytes']);
        $this->assertSame(1024, $comparison['page_size_bytes']);
        $this->assertSame(7168, $comparison['requested_bytes']);
        $this->assertSame(9216, $comparison['initial_free_bytes']);
        $this->assertSame(6144, $comparison['initial_largest_hole_bytes']);
        $this->assertSame(['contiguous', 'paging', 'segmentation'], array_keys($comparison['modes']));
        $contiguous = $comparison['modes']['contiguous'];
        $this->assertFalse($contiguous['accepted']);
        $this->assertSame(0, $contiguous['reserved_bytes']);
        $this->assertSame(0, $contiguous['internal_waste_bytes']);
        $this->assertSame(9216, $contiguous['remaining_free_bytes']);
        $this->assertSame(6144, $contiguous['largest_hole_bytes']);
        $this->assertSame([], $contiguous['allocations']);
        $paging = $comparison['modes']['paging'];
        $this->assertTrue($paging['accepted']);
        $this->assertSame(7168, $paging['reserved_bytes']);
        $this->assertSame(0, $paging['internal_waste_bytes']);
        $this->assertCount(7, $paging['allocations']);
        $this->assertSame([4096, 5120, 6144, 10240, 11264, 12288, 13312], array_column($paging['allocations'], 'base'));
        $this->assertSame(array_fill(0, 7, 1024), array_column($paging['allocations'], 'size_bytes'));
        $segmentation = $comparison['modes']['segmentation'];
        $this->assertTrue($segmentation['accepted']);
        $this->assertSame(7168, $segmentation['reserved_bytes']);
        $this->assertSame(0, $segmentation['internal_waste_bytes']);
        $allocations = $segmentation['allocations'];
        usort($allocations, fn ($left, $right) => $left['base'] <=> $right['base']);
        $this->assertSame([4096, 10240], array_column($allocations, 'base'));
        $this->assertSame([3072, 4096], array_column($allocations, 'size_bytes'));
        foreach ([$paging, $segmentation] as $mode) {
            $this->assertSame(2048, $mode['remaining_free_bytes']);
            $this->assertSame(2048, $mode['largest_hole_bytes']);
        }
    }

    public function test_non_page_aligned_request_reports_only_paging_internal_fragmentation(): void
    {
        $comparison = app(MemoryComparisonService::class)->compare(7500);
        $paging = $comparison['modes']['paging'];
        $segmentation = $comparison['modes']['segmentation'];

        $this->assertFalse($comparison['modes']['contiguous']['accepted']);
        $this->assertTrue($paging['accepted']);
        $this->assertSame(8192, $paging['reserved_bytes']);
        $this->assertSame(692, $paging['internal_waste_bytes']);
        $this->assertSame(1024, $paging['remaining_free_bytes']);
        $this->assertSame(1024, $paging['largest_hole_bytes']);
        $this->assertCount(8, $paging['allocations']);
        $this->assertTrue($segmentation['accepted']);
        $this->assertSame(7500, $segmentation['reserved_bytes']);
        $this->assertSame(0, $segmentation['internal_waste_bytes']);
        $this->assertSame(1716, $segmentation['remaining_free_bytes']);
        $this->assertSame(1716, $segmentation['largest_hole_bytes']);
        $allocations = $segmentation['allocations'];
        usort($allocations, fn ($left, $right) => $left['base'] <=> $right['base']);
        $this->assertSame([4096, 10240], array_column($allocations, 'base'));
        $this->assertSame([3072, 4428], array_column($allocations, 'size_bytes'));
    }

    public function test_request_equal_to_the_largest_hole_fits_contiguously_and_leaves_the_other_hole(): void
    {
        $mode = app(MemoryComparisonService::class)->compare(6144)['modes']['contiguous'];

        $this->assertTrue($mode['accepted']);
        $this->assertSame(6144, $mode['reserved_bytes']);
        $this->assertSame(0, $mode['internal_waste_bytes']);
        $this->assertCount(1, $mode['allocations']);
        $this->assertSame(10240, $mode['allocations'][0]['base']);
        $this->assertSame(6144, $mode['allocations'][0]['size_bytes']);
        $this->assertSame(3072, $mode['remaining_free_bytes']);
        $this->assertSame(3072, $mode['largest_hole_bytes']);
    }

    public function test_exact_total_free_space_can_be_filled_by_paging_and_segmentation(): void
    {
        $comparison = app(MemoryComparisonService::class)->compare(9216);

        $this->assertFalse($comparison['modes']['contiguous']['accepted']);
        foreach (['paging', 'segmentation'] as $key) {
            $mode = $comparison['modes'][$key];
            $this->assertTrue($mode['accepted']);
            $this->assertSame(9216, $mode['reserved_bytes']);
            $this->assertSame(0, $mode['remaining_free_bytes']);
            $this->assertSame(0, $mode['largest_hole_bytes']);
        }
    }

    public function test_single_byte_request_is_accepted_and_paging_reserves_a_complete_frame(): void
    {
        $comparison = app(MemoryComparisonService::class)->compare(1);

        foreach (['contiguous', 'segmentation'] as $key) {
            $mode = $comparison['modes'][$key];
            $this->assertTrue($mode['accepted']);
            $this->assertSame(1, $mode['reserved_bytes']);
            $this->assertSame(0, $mode['internal_waste_bytes']);
            $this->assertSame(9215, $mode['remaining_free_bytes']);
            $this->assertCount(1, $mode['allocations']);
            $this->assertSame(4096, $mode['allocations'][0]['base']);
            $this->assertSame(1, $mode['allocations'][0]['size_bytes']);
        }
        $paging = $comparison['modes']['paging'];
        $this->assertTrue($paging['accepted']);
        $this->assertSame(1024, $paging['reserved_bytes']);
        $this->assertSame(1023, $paging['internal_waste_bytes']);
        $this->assertSame(8192, $paging['remaining_free_bytes']);
    }

    public static function rejectedRequests(): array
    {
        return ['one byte beyond free space' => [9217], 'all RAM requested' => [16384]];
    }

    #[DataProvider('rejectedRequests')]
    public function test_insufficient_total_free_space_leaves_every_mode_in_its_initial_state(int $bytes): void
    {
        $comparison = app(MemoryComparisonService::class)->compare($bytes);

        foreach ($comparison['modes'] as $mode) {
            $this->assertFalse($mode['accepted']);
            $this->assertSame(0, $mode['reserved_bytes']);
            $this->assertSame(0, $mode['internal_waste_bytes']);
            $this->assertSame([], $mode['allocations']);
            $this->assertSame(9216, $mode['remaining_free_bytes']);
            $this->assertSame(6144, $mode['largest_hole_bytes']);
            $this->assertSame([0, 4096, 7168, 10240], array_column($mode['layout'], 'base'));
            $this->assertSame([4096, 3072, 3072, 6144], array_column($mode['layout'], 'size_bytes'));
            $this->assertSame(['EXISTING', 'FREE', 'EXISTING', 'FREE'], array_column($mode['layout'], 'state'));
        }
    }

    public static function layoutRequests(): array
    {
        return array_map(fn ($bytes) => [$bytes], [1, 1023, 1024, 3072, 3073, 6144, 7168, 7500, 9216, 9217, 16384]);
    }

    #[DataProvider('layoutRequests')]
    public function test_each_layout_covers_ram_once_and_reserves_only_the_bytes_reported(int $bytes): void
    {
        $comparison = app(MemoryComparisonService::class)->compare($bytes);

        foreach ($comparison['modes'] as $mode) {
            $cursor = 0;
            $sizes = ['EXISTING' => 0, 'FREE' => 0, 'ALLOCATED' => 0];
            $largestHole = 0;
            foreach ($mode['layout'] as $block) {
                $this->assertSame($cursor, $block['base']);
                $this->assertGreaterThan(0, $block['size_bytes']);
                $this->assertArrayHasKey($block['state'], $sizes);
                $this->assertIsString($block['label']);
                $this->assertNotSame('', $block['label']);
                $sizes[$block['state']] += $block['size_bytes'];
                $cursor += $block['size_bytes'];
                if ($block['state'] === 'FREE') {
                    $largestHole = max($largestHole, $block['size_bytes']);
                }
            }
            $this->assertSame(16384, $cursor);
            $this->assertSame(7168, $sizes['EXISTING']);
            $this->assertSame($mode['reserved_bytes'], $sizes['ALLOCATED']);
            $this->assertSame($mode['remaining_free_bytes'], $sizes['FREE']);
            $this->assertSame($mode['largest_hole_bytes'], $largestHole);
            $this->assertSame($mode['reserved_bytes'], array_sum(array_column($mode['allocations'], 'size_bytes')));
            $this->assertNotSame('', trim($mode['note']));
            if ($mode['accepted']) {
                $this->assertSame($bytes + $mode['internal_waste_bytes'], $mode['reserved_bytes']);
            } else {
                $this->assertSame([], $mode['allocations']);
                $this->assertSame(0, $sizes['ALLOCATED']);
            }
        }
    }

    public static function invalidServiceRequests(): array
    {
        return ['zero' => [0], 'negative' => [-1], 'beyond RAM' => [16385]];
    }

    #[DataProvider('invalidServiceRequests')]
    public function test_invalid_request_sizes_are_rejected_without_database_changes(int $bytes): void
    {
        $before = $this->domainState();

        try {
            app(MemoryComparisonService::class)->compare($bytes);
            $this->fail('La comparacion requiere entre uno y 16384 bytes.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('requested_bytes', $exception->errors());
        }

        $this->assertSame($before, $this->domainState());
    }

    public function test_repeated_comparisons_do_not_change_a_saved_scenario_or_its_process_pages_and_events(): void
    {
        $admin = $this->user('administrador');
        $this->savedScenario($admin);
        $before = $this->domainState();
        $service = app(MemoryComparisonService::class);
        $expected = $service->compare(7168);
        $service->compare(7500);
        $service->compare(9217);

        $this->assertSame($expected, $service->compare(7168));
        $this->assertSame($before, $this->domainState());
    }

    public static function readerRoles(): array
    {
        return ['administrator' => ['administrador'], 'operator' => ['operador'], 'observer' => ['observador']];
    }

    #[DataProvider('readerRoles')]
    public function test_every_reader_role_can_compare_from_the_form_without_persisting_a_simulation(string $role): void
    {
        $this->savedScenario($this->user('administrador'));
        $before = $this->domainState();
        $actor = $this->user($role);

        $this->actingAs($actor)->get(route('comparison.index'))->assertOk();
        $component = Livewire::actingAs($actor)->test(MemoryComparison::class)
            ->assertSet('requestedBytes', 7168)->assertSet('result', null)
            ->assertViewHas('comparison', fn ($comparison) => $comparison['requested_bytes'] === 7168)
            ->assertSeeHtml('wire:submit="compare"')
            ->set('requestedBytes', 7500)->call('compare')->assertHasNoErrors()
            ->assertSet('result.requested_bytes', 7500)
            ->assertViewHas('comparison', fn ($comparison) => $comparison['modes']['paging']['internal_waste_bytes'] === 692);
        $component->call('$refresh');

        $this->assertSame($before, $this->domainState());
    }

    public function test_comparison_route_requires_authentication_and_the_read_permissions(): void
    {
        $this->get(route('comparison.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('comparison.index'))->assertForbidden();
    }

    public static function missingReadPermissions(): array
    {
        return array_map(fn ($permission) => [$permission], ['memory.view', 'tables.view', 'simulations.view', 'results.view']);
    }

    #[DataProvider('missingReadPermissions')]
    public function test_route_and_component_require_each_read_permission(string $missing): void
    {
        $actor = User::factory()->create();
        $actor->givePermissionTo(array_values(array_diff(['memory.view', 'tables.view', 'simulations.view', 'results.view'], [$missing])));
        $before = $this->domainState();

        $this->actingAs($actor)->get(route('comparison.index'))->assertForbidden();
        Livewire::actingAs($actor)->test(MemoryComparison::class)->assertForbidden();

        $this->assertSame($before, $this->domainState());
    }

    public static function invalidFormRequests(): array
    {
        return ['zero' => [0], 'negative' => [-1], 'beyond RAM' => [16385], 'fractional' => [1.5], 'non numeric' => ['abc'], 'array' => [[7168]]];
    }

    #[DataProvider('invalidFormRequests')]
    public function test_form_rejects_invalid_sizes_without_writes($bytes): void
    {
        $before = $this->domainState();

        Livewire::actingAs($this->user('observador'))->test(MemoryComparison::class)
            ->set('requestedBytes', $bytes)->call('compare')->assertHasErrors(['requestedBytes'])->assertSet('result', null);

        $this->assertSame($before, $this->domainState());
    }

    public function test_result_is_locked_and_a_mounted_calculator_rechecks_revoked_permissions(): void
    {
        $observer = $this->user('observador');
        $component = Livewire::actingAs($observer)->test(MemoryComparison::class);
        $before = $this->domainState();

        try {
            $component->set('result', ['requested_bytes' => 1, 'modes' => []]);
            $this->fail('El cliente no puede fabricar un resultado de comparacion.');
        } catch (CannotUpdateLockedPropertyException) {
            $this->assertSame($before, $this->domainState());
        }
        $component = Livewire::test(MemoryComparison::class);
        $observer->fresh()->syncRoles([]);
        $component->call('compare')->assertForbidden();
        $this->assertSame($before, $this->domainState());
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function savedScenario(User $actor): void
    {
        $scenario = app(MemoryConfigurationService::class)->configure($actor, null, [
            'name' => 'Escenario guardado', 'ram_kb' => 16, 'page_kb' => 1, 'secondary_kb' => 64,
        ]);
        app(ProcessManagerService::class)->create($actor, $scenario->id, ['name' => 'Proceso guardado', 'size_kb' => 4]);
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
