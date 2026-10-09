<?php

namespace App\Services;

use App\Enums\PermissionName;
use App\Enums\ProcessStatus;
use App\Enums\ScenarioStatus;
use App\Enums\SegmentStatus;
use App\Enums\SimulationEventType;
use App\Enums\SimulationMode;
use App\Models\Scenario;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SimulationService
{
    /** @return array<string, int|string> */
    public function startDemo(User $actor): array
    {
        $runId = (string) Str::uuid();
        $label = substr(str_replace('-', '', $runId), 0, 8);

        return DB::transaction(function () use ($actor, $runId, $label): array {
            $currentActor = $this->authorize($actor, $this->startPermissions());
            $pagingScenario = app(MemoryConfigurationService::class)->configure($currentActor, null, [
                'name' => "Demostración de paginación {$label}",
                'ram_kb' => 16,
                'page_kb' => 1,
                'secondary_kb' => 64,
            ]);
            $pagingScenario->update(['is_demo' => true]);
            $manager = app(ProcessManagerService::class);
            $processes = [];
            foreach (['Chrome' => 4, 'Spotify' => 3, 'VS Code' => 6, 'MemoryLab' => 2] as $name => $sizeKb) {
                $processes[$name] = $manager->create($currentActor, $pagingScenario->getKey(), [
                    'name' => $name,
                    'size_kb' => $sizeKb,
                ]);
            }
            $paging = app(PagingService::class);
            foreach (['Chrome' => [0, 1], 'Spotify' => [0], 'VS Code' => [0, 1]] as $name => $pages) {
                foreach ($pages as $pageNumber) {
                    $paging->requestPage($currentActor, $pagingScenario->getKey(), $processes[$name]->getKey(), $pageNumber);
                }
            }

            $segmentation = app(SegmentationService::class);
            $segmentationScenario = $segmentation->configure($currentActor, [
                'name' => "Demostración de segmentación {$label}",
                'ram_kb' => 16,
            ]);
            $segmentationScenario->update(['is_demo' => true]);
            $editor = $segmentation->createProcess($currentActor, $segmentationScenario->getKey(), [
                'name' => 'Editor',
                'size_kb' => 4,
            ]);
            foreach ([
                ['name' => 'Código', 'base' => 1000, 'size_bytes' => 1200],
                ['name' => 'Datos', 'base' => 4000, 'size_bytes' => 800],
                ['name' => 'Stack', 'base' => 7000, 'size_bytes' => 600],
                ['name' => 'Heap', 'base' => 9000, 'size_bytes' => 1000],
            ] as $input) {
                $segmentation->createSegment($currentActor, $segmentationScenario->getKey(), $editor->getKey(), $input);
            }

            return [
                'paging_scenario_id' => $pagingScenario->getKey(),
                'segmentation_scenario_id' => $segmentationScenario->getKey(),
                'paging_process_id' => $processes['Chrome']->getKey(),
                'segmentation_process_id' => $editor->getKey(),
                'run_id' => $runId,
            ];
        }, 3);
    }

    /** @return array<string, int|string> */
    public function restartDemo(User $actor, int $pagingScenarioId, int $segmentationScenarioId): array
    {
        $permissions = array_merge($this->startPermissions(), [PermissionName::ResetMemory]);
        $this->authorize($actor, $permissions);
        if ($pagingScenarioId === $segmentationScenarioId) {
            throw ValidationException::withMessages([
                'segmentation_scenario_id' => 'Los escenarios de paginación y segmentación deben ser distintos.',
            ]);
        }

        return DB::transaction(function () use ($actor, $pagingScenarioId, $segmentationScenarioId, $permissions): array {
            $ids = [$pagingScenarioId, $segmentationScenarioId];
            sort($ids, SORT_NUMERIC);
            $scenarios = [];
            foreach ($ids as $id) {
                $scenarios[$id] = Scenario::query()->lockForUpdate()->findOrFail($id);
            }
            $currentActor = $this->authorize($actor, $permissions);
            $pagingScenario = $scenarios[$pagingScenarioId];
            $segmentationScenario = $scenarios[$segmentationScenarioId];
            if ($pagingScenario->mode !== SimulationMode::Paging || ! $pagingScenario->is_demo) {
                throw ValidationException::withMessages([
                    'paging_scenario_id' => 'Selecciona el escenario de paginación de una demostración.',
                ]);
            }
            if ($segmentationScenario->mode !== SimulationMode::Segmentation || ! $segmentationScenario->is_demo) {
                throw ValidationException::withMessages([
                    'segmentation_scenario_id' => 'Selecciona el escenario de segmentación de una demostración.',
                ]);
            }

            $this->reset($currentActor, $pagingScenarioId);
            $this->reset($currentActor, $segmentationScenarioId);
            $pagingScenario->update(['status' => ScenarioStatus::Completed]);
            $segmentationScenario->update(['status' => ScenarioStatus::Completed]);

            return $this->startDemo($currentActor);
        }, 3);
    }

    /** @return array<string, int> */
    public function reset(User $actor, int $scenarioId): array
    {
        return DB::transaction(function () use ($actor, $scenarioId): array {
            $scenario = Scenario::query()->lockForUpdate()->findOrFail($scenarioId);
            $currentActor = $this->authorize($actor, [
                PermissionName::ResetMemory,
                PermissionName::ExecuteSimulations,
                PermissionName::ViewMemory,
                PermissionName::ViewTables,
                PermissionName::ViewSimulations,
            ]);
            if (! in_array($scenario->status, [ScenarioStatus::Ready, ScenarioStatus::Running], true)) {
                throw ValidationException::withMessages([
                    'scenario_id' => 'Solo se puede reiniciar un escenario listo o en ejecución.',
                ]);
            }
            match ($scenario->mode) {
                SimulationMode::Paging => app(PagingService::class)->snapshot($currentActor, $scenarioId),
                SimulationMode::Segmentation => app(SegmentationService::class)->snapshot($currentActor, $scenarioId),
                default => throw ValidationException::withMessages([
                    'scenario_id' => 'El reinicio requiere un escenario de paginación o segmentación válido.',
                ]),
            };

            $counts = [
                'scenario_id' => $scenario->getKey(),
                'terminated_processes' => $scenario->processes()->where('status', '!=', ProcessStatus::Terminated->value)->count(),
                'released_pages' => $scenario->pages()->count(),
                'released_segments' => $scenario->segments()->where('status', SegmentStatus::Active->value)->count(),
            ];
            $scenario->pages()->delete();
            $scenario->segments()->where('status', SegmentStatus::Active->value)->update(['status' => SegmentStatus::Released->value]);
            $scenario->processes()->where('status', '!=', ProcessStatus::Terminated->value)->update(['status' => ProcessStatus::Terminated->value]);
            $scenario->update(['status' => ScenarioStatus::Ready]);
            $scenario->events()->create([
                'user_id' => $currentActor->getKey(),
                'type' => SimulationEventType::MemoryReset,
                'description' => 'Se reinició la memoria simulada, finalizando los procesos y liberando sus asignaciones.',
                'metadata' => ['mode' => $scenario->mode->value] + $counts,
                'occurred_at' => now('UTC'),
            ]);

            return $counts;
        }, 3);
    }

    /** @return list<PermissionName> */
    private function startPermissions(): array
    {
        return [
            PermissionName::ConfigureMemory,
            PermissionName::CreateScenarios,
            PermissionName::CreateProcesses,
            PermissionName::RequestPages,
            PermissionName::ExecuteSimulations,
            PermissionName::ExecuteSegmentation,
            PermissionName::ViewMemory,
            PermissionName::ViewTables,
            PermissionName::ViewSimulations,
        ];
    }

    /** @param list<PermissionName> $permissions */
    private function authorize(User $actor, array $permissions): User
    {
        $currentActor = User::findOrFail($actor->getKey());
        foreach ($permissions as $permission) {
            Gate::forUser($currentActor)->authorize($permission->value);
        }

        return $currentActor;
    }
}
