<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Enums\SimulationMode;
use App\Models\Scenario;
use App\Models\User;
use App\Services\PagingService;
use App\Services\SegmentationService;
use App\Services\SimulationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MemoryDemoSeeder extends Seeder
{
    public const PAGING_NAME = 'Demostración base: paginación';

    public const SEGMENTATION_NAME = 'Demostración base: segmentación';

    public function run(): void
    {
        DB::transaction(function (): void {
            // Existing administrators serialize explicit demo seeding without account changes.
            $actor = User::query()->whereHas('roles', fn ($query) => $query
                ->where('name', RoleName::Administrator->value)->where('guard_name', 'web'))
                ->orderBy('id')->lockForUpdate()->first();
            if ($actor === null) {
                throw new RuntimeException('La demostración base requiere una cuenta existente con el rol Administrador. Asigna ese rol antes de ejecutar MemoryDemoSeeder.');
            }

            $scenarios = Scenario::where('is_demo', true)
                ->whereIn('name', [self::PAGING_NAME, self::SEGMENTATION_NAME])
                ->orderBy('id')->lockForUpdate()->get();
            if ($scenarios->isEmpty()) {
                $demo = app(SimulationService::class)->startDemo($actor);
                Scenario::findOrFail($demo['paging_scenario_id'])->update(['name' => self::PAGING_NAME]);
                Scenario::findOrFail($demo['segmentation_scenario_id'])->update(['name' => self::SEGMENTATION_NAME]);

                return;
            }

            $paging = $scenarios->filter(fn (Scenario $scenario): bool => $scenario->name === self::PAGING_NAME);
            $segmentation = $scenarios->filter(fn (Scenario $scenario): bool => $scenario->name === self::SEGMENTATION_NAME);
            if ($scenarios->count() !== 2 || $paging->count() !== 1 || $segmentation->count() !== 1) {
                throw new RuntimeException('La demostración base está incompleta o tiene nombres duplicados. Revisa sus dos escenarios existentes; el seeder no los modificó.');
            }
            $pagingScenario = $paging->first();
            $segmentationScenario = $segmentation->first();
            if ($pagingScenario->mode !== SimulationMode::Paging
                || $segmentationScenario->mode !== SimulationMode::Segmentation) {
                throw new RuntimeException('Los modos de los escenarios de la demostración base no coinciden con sus nombres. El seeder no los modificó.');
            }

            // Valid existing demos may contain user work, a reset, or completed scenarios.
            app(PagingService::class)->snapshot($actor, $pagingScenario->getKey());
            app(SegmentationService::class)->snapshot($actor, $segmentationScenario->getKey());
        }, 3);
    }
}
