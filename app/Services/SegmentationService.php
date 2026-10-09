<?php

namespace App\Services;

use App\Enums\PermissionName;
use App\Enums\ProcessStatus;
use App\Enums\ScenarioStatus;
use App\Enums\SegmentStatus;
use App\Enums\SimulationEventType;
use App\Enums\SimulationMode;
use App\Models\MemoryConfiguration;
use App\Models\Process;
use App\Models\Scenario;
use App\Models\Segment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SegmentationService
{
    public const MAX_SEGMENTS_PER_PROCESS = 16;

    public const MAX_SEGMENTS_PER_SCENARIO = 1024;

    /** @param array<string, mixed> $input */
    public function configure(User $actor, array $input): Scenario
    {
        return DB::transaction(function () use ($actor, $input): Scenario {
            $currentActor = $this->authorize($actor, [PermissionName::ConfigureMemory, PermissionName::CreateScenarios]);
            $maxRam = (int) config('memorylab.memory.max_size_kb', 65536);
            $validated = $this->validateInput($input, [
                'name' => ['required', 'string', 'max:100'],
                'ram_kb' => ['required', 'integer', 'min:1', 'max:'.$maxRam],
            ], ['name' => 'nombre del escenario', 'ram_kb' => 'RAM total']);

            $scenario = Scenario::create([
                'name' => $validated['name'],
                'mode' => SimulationMode::Segmentation,
                'status' => ScenarioStatus::Ready,
                'created_by' => $currentActor->getKey(),
                'is_demo' => false,
            ]);
            $configuration = $scenario->configuration()->create([
                'ram_size_bytes' => (int) $validated['ram_kb'] * 1024,
                'page_size_bytes' => 1024,
                'secondary_storage_bytes' => 0,
            ]);
            $now = now('UTC');
            $scenario->events()->create([
                'user_id' => $currentActor->getKey(),
                'type' => SimulationEventType::ScenarioCreated,
                'description' => 'Se creó el escenario de segmentación.',
                'metadata' => ['name' => $scenario->name, 'mode' => SimulationMode::Segmentation->value],
                'occurred_at' => $now,
            ]);
            $scenario->events()->create([
                'user_id' => $currentActor->getKey(),
                'type' => SimulationEventType::MemoryConfigured,
                'description' => 'Se configuró la RAM simulada para segmentación, sin marcos ni páginas.',
                'metadata' => [
                    'mode' => SimulationMode::Segmentation->value,
                    'before' => null,
                    'after' => [
                        'ram_size_bytes' => $configuration->ram_size_bytes,
                        'page_size_bytes' => $configuration->page_size_bytes,
                        'secondary_storage_bytes' => $configuration->secondary_storage_bytes,
                    ],
                ],
                'occurred_at' => $now,
            ]);

            return $scenario->load('configuration');
        }, 3);
    }

    /** @param array<string, mixed> $input */
    public function createProcess(User $actor, int $scenarioId, array $input): Process
    {
        return DB::transaction(function () use ($actor, $scenarioId, $input): Process {
            $scenario = Scenario::query()->lockForUpdate()->findOrFail($scenarioId);
            $currentActor = $this->authorize($actor, [PermissionName::CreateProcesses]);
            $configuration = $this->configuration($scenario);
            $this->assertWritable($scenario);
            $state = $this->state($scenario, $configuration);
            $maxProcesses = (int) config('memorylab.processes.max_processes_per_scenario', 100);
            if ($state['processes']->count() >= $maxProcesses) {
                throw ValidationException::withMessages([
                    'scenario_id' => "El escenario ya alcanzó el límite de {$maxProcesses} procesos.",
                ]);
            }

            $maxSize = (int) config('memorylab.processes.max_size_kb', 65536);
            $validated = $this->validateInput($input, [
                'name' => ['required', 'string', 'max:100'],
                'size_kb' => ['required', 'integer', 'min:1', 'max:'.$maxSize],
            ], ['name' => 'nombre del proceso', 'size_kb' => 'tamaño del proceso en KB']);
            $process = $scenario->processes()->create([
                'name' => $validated['name'],
                'size_bytes' => (int) $validated['size_kb'] * 1024,
                'status' => ProcessStatus::Ready,
            ]);
            $scenario->events()->create([
                'user_id' => $currentActor->getKey(),
                'process_id' => $process->getKey(),
                'type' => SimulationEventType::ProcessCreated,
                'description' => 'Se creó un proceso para asignar sus segmentos en RAM simulada.',
                'metadata' => [
                    'mode' => SimulationMode::Segmentation->value,
                    'name' => $process->name,
                    'size_bytes' => $process->size_bytes,
                ],
                'occurred_at' => now('UTC'),
            ]);

            return $process->loadCount('segments');
        }, 3);
    }

    /** @param array<string, mixed> $input */
    public function createSegment(User $actor, int $scenarioId, int $processId, array $input): Segment
    {
        return DB::transaction(function () use ($actor, $scenarioId, $processId, $input): Segment {
            $scenario = Scenario::query()->lockForUpdate()->findOrFail($scenarioId);
            $this->authorize($actor, [PermissionName::ExecuteSegmentation, PermissionName::ExecuteSimulations]);
            $configuration = $this->configuration($scenario);
            $this->assertWritable($scenario);
            $state = $this->state($scenario, $configuration, $processId);
            $process = $state['selected_process'];
            if ($process->status === ProcessStatus::Terminated) {
                throw ValidationException::withMessages([
                    'process_id' => 'No se pueden asignar segmentos a un proceso finalizado.',
                ]);
            }
            if ($process->segments_count >= self::MAX_SEGMENTS_PER_PROCESS) {
                throw ValidationException::withMessages([
                    'process_id' => 'El proceso ya alcanzó el límite de 16 segmentos, incluidos los liberados.',
                ]);
            }
            if ($scenario->segments()->count() >= self::MAX_SEGMENTS_PER_SCENARIO) {
                throw ValidationException::withMessages([
                    'scenario_id' => 'El escenario ya alcanzó el límite de 1024 segmentos, incluidos los liberados.',
                ]);
            }

            $ramBytes = $configuration->ram_size_bytes;
            $validated = $this->validateInput($input, [
                'name' => ['required', 'string', 'max:100'],
                'base' => ['required', 'integer', 'min:0', 'max:'.$ramBytes],
                'size_bytes' => ['required', 'integer', 'min:1', 'max:'.$ramBytes],
            ], ['name' => 'nombre del segmento', 'base' => 'base en bytes', 'size_bytes' => 'tamaño del segmento en bytes']);
            $base = (int) $validated['base'];
            $sizeBytes = (int) $validated['size_bytes'];
            $end = $base + $sizeBytes;
            if ($end > $ramBytes) {
                throw ValidationException::withMessages([
                    'base' => 'La base más el tamaño del segmento no puede superar la RAM total.',
                ]);
            }
            $processUsedBytes = $state['segments']->where('status', SegmentStatus::Active)->sum('size_bytes');
            if ($processUsedBytes + $sizeBytes > $process->size_bytes) {
                throw ValidationException::withMessages([
                    'size_bytes' => 'La suma de los segmentos activos no puede superar el tamaño del proceso.',
                ]);
            }
            foreach ($state['all_segments'] as $existing) {
                if ($base < $existing->base + $existing->size_bytes && $existing->base < $end) {
                    throw ValidationException::withMessages([
                        'base' => 'El segmento se superpone con otro segmento activo. Los intervalos adyacentes sí son válidos.',
                    ]);
                }
            }

            $lastNumber = $process->segments()->max('segment_number');
            $number = $lastNumber === null ? 0 : (int) $lastNumber + 1;
            if ($number > 4294967295) {
                throw ValidationException::withMessages([
                    'process_id' => 'El proceso ya no admite otro número de segmento.',
                ]);
            }

            return $scenario->segments()->create([
                'process_id' => $process->getKey(),
                'segment_number' => $number,
                'name' => $validated['name'],
                'base' => $base,
                'size_bytes' => $sizeBytes,
                'status' => SegmentStatus::Active,
            ])->load('process');
        }, 3);
    }

    /** @return array<string, int|string|bool|null> */
    public function access(User $actor, int $scenarioId, int $processId, int $segmentNumber, int $offset): array
    {
        return DB::transaction(function () use ($actor, $scenarioId, $processId, $segmentNumber, $offset): array {
            Scenario::query()->lockForUpdate()->findOrFail($scenarioId);
            $currentActor = $this->authorize($actor, [PermissionName::ExecuteSegmentation, PermissionName::ExecuteSimulations]);
            $snapshot = $this->snapshot($currentActor, $scenarioId, $processId);
            $scenario = $snapshot['scenario'];
            $process = $snapshot['selected_process'];
            $this->assertWritable($scenario);
            if ($process->status === ProcessStatus::Terminated) {
                throw ValidationException::withMessages([
                    'process_id' => 'No se pueden consultar segmentos de un proceso finalizado mediante la CPU.',
                ]);
            }
            if ($segmentNumber < 0 || $segmentNumber > 4294967295) {
                throw ValidationException::withMessages([
                    'segment_number' => 'Selecciona un número de segmento entero entre 0 y 4294967295.',
                ]);
            }
            if ($offset < 0 || $offset > 4294967295) {
                throw ValidationException::withMessages([
                    'offset' => 'El offset debe ser un número entero entre 0 y 4294967295.',
                ]);
            }

            $segment = $snapshot['segments']->firstWhere('segment_number', $segmentNumber);
            if ($segment === null || $segment->status !== SegmentStatus::Active) {
                throw ValidationException::withMessages([
                    'segment_number' => 'Selecciona un segmento activo del proceso elegido.',
                ]);
            }

            $valid = $offset < $segment->size_bytes;
            $physicalAddress = $valid ? $segment->base + $offset : null;
            $outcome = $valid ? SimulationEventType::SegmentAccess : SimulationEventType::SegmentationFault;
            $event = $scenario->events()->create([
                'user_id' => $currentActor->getKey(),
                'process_id' => $process->getKey(),
                'type' => $outcome,
                'description' => $valid
                    ? "Acceso al segmento {$segmentNumber}: base {$segment->base} + offset {$offset} = dirección física {$physicalAddress}."
                    : "SEGMENTATION FAULT: el offset {$offset} debe ser menor que el tamaño {$segment->size_bytes} del segmento {$segmentNumber}.",
                'metadata' => [
                    'mode' => SimulationMode::Segmentation->value,
                    'process_name' => $process->name,
                    'segment_id' => $segment->getKey(),
                    'segment_number' => $segment->segment_number,
                    'segment_name' => $segment->name,
                    'base' => $segment->base,
                    'size_bytes' => $segment->size_bytes,
                    'offset' => $offset,
                    'last_valid_offset' => $segment->size_bytes - 1,
                    'physical_address' => $physicalAddress,
                    'outcome' => $outcome->value,
                ],
                'occurred_at' => now('UTC'),
            ]);

            if ($valid) {
                if ($scenario->status !== ScenarioStatus::Running) {
                    $scenario->update(['status' => ScenarioStatus::Running]);
                }
                if ($process->status !== ProcessStatus::Running) {
                    $process->update(['status' => ProcessStatus::Running]);
                }
            }

            return [
                'event_id' => $event->getKey(),
                'scenario_id' => $scenario->getKey(),
                'process_id' => $process->getKey(),
                'segment_id' => $segment->getKey(),
                'segment_number' => $segment->segment_number,
                'segment_name' => $segment->name,
                'base' => $segment->base,
                'size_bytes' => $segment->size_bytes,
                'offset' => $offset,
                'valid' => $valid,
                'physical_address' => $physicalAddress,
                'outcome' => $outcome->value,
            ];
        }, 3);
    }

    /** @return array<string, mixed> */
    public function snapshot(User $actor, int $scenarioId, ?int $processId = null): array
    {
        return DB::transaction(function () use ($actor, $scenarioId, $processId): array {
            $this->authorize($actor, [PermissionName::ViewMemory, PermissionName::ViewTables, PermissionName::ViewSimulations]);
            $scenario = Scenario::with('configuration')->findOrFail($scenarioId);
            $configuration = $this->configuration($scenario);

            return $this->state($scenario, $configuration, $processId);
        }, 3);
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

    private function configuration(Scenario $scenario): MemoryConfiguration
    {
        $scenario->loadMissing('configuration');
        $configuration = $scenario->configuration;
        $maxRamBytes = (int) config('memorylab.memory.max_size_kb', 65536) * 1024;
        if ($scenario->mode !== SimulationMode::Segmentation || $configuration === null
            || $configuration->frame_count === null
            || $configuration->ram_size_bytes > $maxRamBytes
            || $configuration->secondary_storage_bytes !== 0
            || $scenario->frames()->exists()
            || $scenario->pages()->exists()) {
            throw ValidationException::withMessages([
                'scenario_id' => 'Selecciona un escenario de segmentación con configuración válida, sin marcos, páginas ni almacenamiento secundario.',
            ]);
        }

        return $configuration;
    }

    private function assertWritable(Scenario $scenario): void
    {
        if (! in_array($scenario->status, [ScenarioStatus::Ready, ScenarioStatus::Running], true)) {
            throw ValidationException::withMessages([
                'scenario_id' => 'El escenario de segmentación debe estar listo o en ejecución para realizar cambios.',
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function state(Scenario $scenario, MemoryConfiguration $configuration, ?int $processId = null): array
    {
        $maxProcesses = (int) config('memorylab.processes.max_processes_per_scenario', 100);
        if ($scenario->processes()->count() > $maxProcesses
            || $scenario->segments()->count() > self::MAX_SEGMENTS_PER_SCENARIO) {
            throw ValidationException::withMessages([
                'scenario_id' => 'El escenario supera los límites permitidos de procesos o segmentos.',
            ]);
        }
        $processes = $scenario->processes()->withCount([
            'segments',
            'segments as active_segments_count' => fn ($query) => $query->where('status', SegmentStatus::Active->value),
        ])->orderBy('name')->orderBy('id')->get();
        $processMap = $processes->keyBy('id');
        foreach ($processes as $process) {
            if ($process->segments_count > self::MAX_SEGMENTS_PER_PROCESS) {
                throw ValidationException::withMessages([
                    'scenario_id' => 'Un proceso del escenario supera el límite de 16 segmentos.',
                ]);
            }
        }
        $selectedProcess = $processId === null ? null : $processMap->get($processId);
        if ($processId !== null && $selectedProcess === null) {
            throw ValidationException::withMessages([
                'process_id' => 'Selecciona un proceso que pertenezca al escenario de segmentación.',
            ]);
        }

        $allSegments = $scenario->segments()->where('status', SegmentStatus::Active->value)
            ->with('process')->orderBy('base')->orderBy('id')->get();
        $ramBytes = $configuration->ram_size_bytes;
        $blocks = [];
        $cursor = 0;
        $usedBytes = 0;
        $processBytes = [];
        foreach ($allSegments as $segment) {
            $end = $segment->base + $segment->size_bytes;
            $process = $processMap->get($segment->process_id);
            if ($segment->base < $cursor || $segment->size_bytes <= 0 || $end > $ramBytes || $process === null) {
                throw ValidationException::withMessages([
                    'scenario_id' => 'El mapa de segmentos contiene un solapamiento o un intervalo fuera de la RAM configurada.',
                ]);
            }
            $processBytes[$segment->process_id] = ($processBytes[$segment->process_id] ?? 0) + $segment->size_bytes;
            if ($processBytes[$segment->process_id] > $process->size_bytes) {
                throw ValidationException::withMessages([
                    'scenario_id' => 'Los segmentos activos de un proceso superan su tamaño.',
                ]);
            }
            if ($cursor < $segment->base) {
                $blocks[] = ['base' => $cursor, 'size_bytes' => $segment->base - $cursor, 'state' => 'FREE', 'segment' => null];
            }
            $blocks[] = ['base' => $segment->base, 'size_bytes' => $segment->size_bytes, 'state' => 'ACTIVE', 'segment' => $segment];
            $cursor = $end;
            $usedBytes += $segment->size_bytes;
        }
        if ($cursor < $ramBytes) {
            $blocks[] = ['base' => $cursor, 'size_bytes' => $ramBytes - $cursor, 'state' => 'FREE', 'segment' => null];
        }

        $segments = $selectedProcess === null
            ? new Collection
            : $selectedProcess->segments()->with('process')->orderBy('segment_number')->get();

        return [
            'scenario' => $scenario,
            'configuration' => $configuration,
            'processes' => $processes,
            'selected_process' => $selectedProcess,
            'segments' => $segments,
            'all_segments' => $allSegments,
            'ram' => ['total_bytes' => $ramBytes, 'used_bytes' => $usedBytes, 'available_bytes' => $ramBytes - $usedBytes],
            'blocks' => $blocks,
            'last_access' => $this->lastAccess($scenario, $selectedProcess, $segments, $ramBytes),
        ];
    }

    /** @return array<string, mixed>|null */
    private function lastAccess(Scenario $scenario, ?Process $process, Collection $segments, int $ramBytes): ?array
    {
        if ($process === null) {
            return null;
        }
        $event = $scenario->events()->where('process_id', $process->getKey())
            ->whereIn('type', [SimulationEventType::SegmentAccess->value, SimulationEventType::SegmentationFault->value])
            ->orderByDesc('occurred_at')->orderByDesc('id')->first();
        if ($event === null || $scenario->events()->where('type', SimulationEventType::MemoryReset->value)
            ->where('id', '>', $event->getKey())->exists()) {
            return null;
        }

        $metadata = $event->metadata;
        if (! is_array($metadata)
            || ($metadata['mode'] ?? null) !== SimulationMode::Segmentation->value
            || ($metadata['process_name'] ?? null) !== $process->name
            || ! is_int($metadata['segment_id'] ?? null) || $metadata['segment_id'] <= 0
            || ! is_int($metadata['segment_number'] ?? null)
            || $metadata['segment_number'] < 0 || $metadata['segment_number'] > 4294967295
            || ! is_string($metadata['segment_name'] ?? null)
            || trim($metadata['segment_name']) === '' || mb_strlen($metadata['segment_name']) > 100
            || ! is_int($metadata['base'] ?? null) || $metadata['base'] < 0
            || ! is_int($metadata['size_bytes'] ?? null) || $metadata['size_bytes'] <= 0
            || $ramBytes < $metadata['base'] + $metadata['size_bytes']
            || ! is_int($metadata['offset'] ?? null)
            || $metadata['offset'] < 0 || $metadata['offset'] > 4294967295
            || ($metadata['last_valid_offset'] ?? null) !== $metadata['size_bytes'] - 1
            || ($metadata['outcome'] ?? null) !== $event->type->value
            || ! array_key_exists('physical_address', $metadata)) {
            return null;
        }

        $segment = $segments->firstWhere('id', $metadata['segment_id']);
        if ($segment === null || $segment->scenario_id !== $scenario->getKey()
            || $segment->process_id !== $process->getKey()
            || $segment->segment_number !== $metadata['segment_number']
            || $segment->name !== $metadata['segment_name']
            || $segment->base !== $metadata['base']
            || $segment->size_bytes !== $metadata['size_bytes']) {
            return null;
        }
        $valid = $event->type === SimulationEventType::SegmentAccess;
        $physicalAddress = $valid ? $metadata['base'] + $metadata['offset'] : null;
        if ($valid !== ($metadata['offset'] < $metadata['size_bytes'])
            || $metadata['physical_address'] !== $physicalAddress
            || (array_key_exists('valid', $metadata) && $metadata['valid'] !== $valid)) {
            return null;
        }

        return [
            'event_id' => $event->getKey(),
            'scenario_id' => $scenario->getKey(),
            'process_id' => $process->getKey(),
            'segment_id' => $metadata['segment_id'],
            'segment_number' => $metadata['segment_number'],
            'segment_name' => $metadata['segment_name'],
            'base' => $metadata['base'],
            'size_bytes' => $metadata['size_bytes'],
            'offset' => $metadata['offset'],
            'valid' => $valid,
            'physical_address' => $physicalAddress,
            'outcome' => $metadata['outcome'],
            'occurred_at' => $event->occurred_at,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, list<string>>  $rules
     * @param  array<string, string>  $attributes
     * @return array<string, mixed>
     */
    private function validateInput(array $input, array $rules, array $attributes): array
    {
        if (is_string($input['name'] ?? null)) {
            $input['name'] = trim($input['name']);
        }

        return Validator::make($input, $rules, [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'min' => 'El campo :attribute debe ser al menos :min.',
            'max.numeric' => 'El campo :attribute no puede superar :max.',
            'max.string' => 'El campo :attribute no puede superar :max caracteres.',
        ], $attributes)->validate();
    }
}
