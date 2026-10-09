<?php

namespace App\Services;

use App\Enums\PermissionName;
use App\Enums\ProcessStatus;
use App\Enums\ScenarioStatus;
use App\Enums\SimulationEventType;
use App\Models\Scenario;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MemoryStressService
{
    public const MAX_PROCESSES_PER_RUN = 8;

    public const MAX_SIZE_KB = 64;

    public const MAX_REQUESTED_PAGES = 64;

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function run(User $actor, int $scenarioId, array $input): array
    {
        $runId = (string) Str::uuid();
        $runLabel = substr(str_replace('-', '', $runId), 0, 8);

        return DB::transaction(function () use ($actor, $scenarioId, $input, $runId, $runLabel): array {
            Scenario::query()->lockForUpdate()->findOrFail($scenarioId);
            $currentActor = User::findOrFail($actor->getKey());
            foreach ([
                PermissionName::CreateProcesses,
                PermissionName::RequestPages,
                PermissionName::ExecuteSimulations,
                PermissionName::ViewMemory,
                PermissionName::ViewTables,
                PermissionName::ViewSimulations,
            ] as $permission) {
                Gate::forUser($currentActor)->authorize($permission->value);
            }
            $validated = Validator::make($input, [
                'process_count' => ['required', 'integer', 'min:1', 'max:'.self::MAX_PROCESSES_PER_RUN],
                'size_kb' => ['required', 'integer', 'min:1', 'max:'.self::MAX_SIZE_KB],
            ], [
                'required' => 'El campo :attribute es obligatorio.',
                'integer' => 'El campo :attribute debe ser un número entero.',
                'min' => 'El campo :attribute debe ser al menos :min.',
                'max' => 'El campo :attribute no puede superar :max.',
            ], ['process_count' => 'cantidad de procesos', 'size_kb' => 'tamaño de cada proceso en KB'])->validate();

            $paging = app(PagingService::class);
            $beforeSnapshot = $paging->snapshot($currentActor, $scenarioId);
            $scenario = $beforeSnapshot['scenario'];
            if (! in_array($scenario->status, [ScenarioStatus::Ready, ScenarioStatus::Running], true)) {
                throw ValidationException::withMessages([
                    'scenario_id' => 'La simulación de alta demanda requiere un escenario de paginación listo o en ejecución.',
                ]);
            }

            $processCount = (int) $validated['process_count'];
            $sizeKb = (int) $validated['size_kb'];
            $sizeBytes = $sizeKb * 1024;
            $pageBytes = $beforeSnapshot['configuration']->page_size_bytes;
            $pagesPerProcess = intdiv($sizeBytes + $pageBytes - 1, $pageBytes);
            if ($processCount * $pagesPerProcess > self::MAX_REQUESTED_PAGES) {
                throw ValidationException::withMessages([
                    'size_kb' => 'El lote no puede superar 64 solicitudes de página. Reduce la cantidad o el tamaño de los procesos.',
                ]);
            }

            $before = $this->summary($beforeSnapshot);
            $manager = app(ProcessManagerService::class);
            $createdProcesses = [];
            // Reserve the complete batch in simulated secondary storage before accessing RAM.
            for ($number = 1; $number <= $processCount; $number++) {
                $process = $manager->create($currentActor, $scenarioId, [
                    'name' => "Carga {$runLabel} #{$number}",
                    'size_kb' => $sizeKb,
                ]);
                $createdProcesses[] = ['id' => $process->getKey(), 'name' => $process->name, 'size_kb' => $sizeKb];
            }

            $requestedPages = 0;
            $evictions = 0;
            $progress = [];
            foreach ($createdProcesses as $process) {
                for ($page = 0; $page < $pagesPerProcess; $page++) {
                    $result = $paging->requestPage($currentActor, $scenarioId, $process['id'], $page);
                    $requestedPages++;
                    if ($result['evicted'] !== null) {
                        $evictions++;
                    }
                    $framesUsed = $scenario->pages()->whereNotNull('frame_id')->count();
                    $progress[] = [
                        'request_index' => $requestedPages, 'process_name' => $process['name'],
                        'page_number' => $page, 'outcome' => $result['outcome'],
                        'frames_used' => $framesUsed, 'ram_used_bytes' => $framesUsed * $pageBytes,
                    ];
                }
            }

            return [
                'scenario_id' => $scenarioId,
                'run_id' => $runId,
                'before' => $before,
                'after' => $this->summary($paging->snapshot($currentActor, $scenarioId)),
                'created_processes' => $createdProcesses,
                'requested_pages' => $requestedPages,
                'evictions' => $evictions,
                'progress' => $progress,
            ];
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function summary(array $snapshot): array
    {
        return [
            'ram' => $snapshot['ram'],
            'secondary' => $snapshot['secondary'],
            'active_process_count' => $snapshot['processes']->filter(fn ($process) => $process->status !== ProcessStatus::Terminated)->count(),
            'page_fault_count' => $snapshot['scenario']->events()->where('type', SimulationEventType::PageFault->value)->count(),
        ];
    }
}
