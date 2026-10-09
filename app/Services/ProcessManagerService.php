<?php

namespace App\Services;

use App\Enums\PermissionName;
use App\Enums\ProcessStatus;
use App\Enums\ScenarioStatus;
use App\Enums\SimulationEventType;
use App\Enums\SimulationMode;
use App\Models\MemoryConfiguration;
use App\Models\Page;
use App\Models\Process;
use App\Models\Scenario;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProcessManagerService
{
    /** @param array<string, mixed> $input */
    public function create(User $actor, int $scenarioId, array $input): Process
    {
        return DB::transaction(function () use ($actor, $scenarioId, $input): Process {
            // Configuration and allocation services serialize changes on this same row.
            $scenario = Scenario::query()->lockForUpdate()->findOrFail($scenarioId);
            $currentActor = User::findOrFail($actor->getKey());
            Gate::forUser($currentActor)->authorize(PermissionName::CreateProcesses->value);

            $validated = $this->validateInput($input);
            if ($scenario->mode !== SimulationMode::Paging
                || ! in_array($scenario->status, [ScenarioStatus::Ready, ScenarioStatus::Running], true)) {
                throw ValidationException::withMessages([
                    'scenario_id' => 'La creación de procesos requiere un escenario de paginación listo o en ejecución.',
                ]);
            }

            $configuration = $scenario->configuration()->first();
            $frameCount = $configuration?->frame_count;
            if ($configuration === null || $frameCount === null) {
                throw ValidationException::withMessages([
                    'scenario_id' => 'Configura una geometría de memoria válida antes de crear procesos.',
                ]);
            }

            $frames = $scenario->frames()
                ->selectRaw('COUNT(*) AS frame_total, MIN(frame_number) AS first_frame, MAX(frame_number) AS last_frame')
                ->toBase()->first();
            // The unique index makes count + endpoints sufficient to verify 0..N-1.
            if ((int) $frames->frame_total !== $frameCount
                || $frames->first_frame === null
                || (int) $frames->first_frame !== 0
                || (int) $frames->last_frame !== $frameCount - 1) {
                throw ValidationException::withMessages([
                    'scenario_id' => 'Los marcos del escenario no coinciden con la configuración de memoria.',
                ]);
            }

            $sizeBytes = (int) $validated['size_kb'] * 1024;
            $pageBytes = $configuration->page_size_bytes;
            $pageCount = intdiv($sizeBytes + $pageBytes - 1, $pageBytes);
            $maxPages = (int) config('memorylab.processes.max_pages_per_process', 1024);
            if ($pageCount > $maxPages) {
                throw ValidationException::withMessages([
                    'size_kb' => "Un proceso no puede superar {$maxPages} páginas. Reduce su tamaño o utiliza otro escenario con páginas más grandes.",
                ]);
            }

            $maxProcesses = (int) config('memorylab.processes.max_processes_per_scenario', 100);
            if ($scenario->processes()->count() >= $maxProcesses) {
                throw ValidationException::withMessages([
                    'scenario_id' => "El escenario ya alcanzó el límite de {$maxProcesses} procesos. Crea otro escenario para continuar.",
                ]);
            }

            $reservedBytes = $pageCount * $pageBytes;
            $capacity = $this->secondaryCapacity($scenario, $configuration);
            if ($capacity['secondary_total_bytes'] < $capacity['secondary_used_bytes'] + $reservedBytes) {
                throw ValidationException::withMessages([
                    'size_kb' => 'El almacenamiento secundario simulado no tiene espacio suficiente para las páginas del proceso.',
                ]);
            }

            $process = $scenario->processes()->create([
                'name' => $validated['name'],
                'size_bytes' => $sizeBytes,
                'status' => ProcessStatus::Ready,
            ]);
            $now = now('UTC');
            $pages = [];
            for ($number = 0; $number < $pageCount; $number++) {
                $pages[] = [
                    'scenario_id' => $scenario->getKey(),
                    'process_id' => $process->getKey(),
                    'frame_id' => null,
                    'page_number' => $number,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($pages, 256) as $batch) {
                Page::insert($batch);
            }

            $scenario->events()->create([
                'user_id' => $currentActor->getKey(),
                'process_id' => $process->getKey(),
                'type' => SimulationEventType::ProcessCreated,
                'description' => "Se creó el proceso con {$pageCount} páginas en almacenamiento secundario simulado.",
                'metadata' => [
                    'name' => $process->name,
                    'size_bytes' => $sizeBytes,
                    'page_size_bytes' => $pageBytes,
                    'page_count' => $pageCount,
                    'secondary_bytes_reserved' => $reservedBytes,
                ],
                'occurred_at' => $now,
            ]);

            return $process->loadCount('pages');
        }, 3);
    }

    /** @return array<string, int>|null */
    public function capacity(Scenario $scenario): ?array
    {
        return DB::transaction(function () use ($scenario): ?array {
            $currentScenario = Scenario::with('configuration')->find($scenario->getKey());
            if ($currentScenario === null || $currentScenario->mode !== SimulationMode::Paging) {
                return null;
            }

            $configuration = $currentScenario->configuration;
            if ($configuration === null || $configuration->frame_count === null) {
                return null;
            }

            return $this->secondaryCapacity($currentScenario, $configuration);
        }, 3);
    }

    /** @return array<string, int> */
    private function secondaryCapacity(Scenario $scenario, MemoryConfiguration $configuration): array
    {
        $pageBytes = $configuration->page_size_bytes;
        $totalBytes = $configuration->secondary_storage_bytes;
        $usedBytes = $scenario->pages()->whereNull('frame_id')->count() * $pageBytes;

        return [
            'page_size_bytes' => $pageBytes,
            'secondary_total_bytes' => $totalBytes,
            'secondary_used_bytes' => $usedBytes,
            'secondary_available_bytes' => max(0, $totalBytes - $usedBytes),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validateInput(array $input): array
    {
        if (is_string($input['name'] ?? null)) {
            $input['name'] = trim($input['name']);
        }
        $maxSize = (int) config('memorylab.processes.max_size_kb', 65536);

        return Validator::make($input, [
            'name' => ['required', 'string', 'max:100'],
            'size_kb' => ['required', 'integer', 'min:1', 'max:'.$maxSize],
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'integer' => 'El campo :attribute debe ser un número entero de KB.',
            'min' => 'El campo :attribute debe ser al menos :min.',
            'max.numeric' => 'El campo :attribute no puede superar :max KB.',
            'max.string' => 'El campo :attribute no puede superar :max caracteres.',
            'string' => 'El campo :attribute debe ser texto.',
        ], [
            'name' => 'nombre del proceso',
            'size_kb' => 'tamaño del proceso',
        ])->validate();
    }
}
