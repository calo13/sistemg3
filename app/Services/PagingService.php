<?php

namespace App\Services;

use App\Enums\PermissionName;
use App\Enums\ProcessStatus;
use App\Enums\ScenarioStatus;
use App\Enums\SimulationEventType;
use App\Enums\SimulationMode;
use App\Models\Page;
use App\Models\Scenario;
use App\Models\SimulationEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PagingService
{
    /** @return array<string, int|bool|null> */
    public function beginRequest(User $actor, int $scenarioId, int $processId, int $pageNumber): array
    {
        return DB::transaction(function () use ($actor, $scenarioId, $processId, $pageNumber): array {
            // All memory operations serialize against configuration and process creation.
            Scenario::query()->lockForUpdate()->findOrFail($scenarioId);
            $currentActor = User::findOrFail($actor->getKey());
            foreach ([PermissionName::RequestPages, PermissionName::ExecuteSimulations] as $permission) {
                Gate::forUser($currentActor)->authorize($permission->value);
            }

            [$snapshot, $page] = $this->requestContext($currentActor, $scenarioId, $processId, $pageNumber);
            $scenario = $snapshot['scenario'];
            $process = $snapshot['selected_process'];
            $pageBytes = $snapshot['configuration']->page_size_bytes;

            $frameNumber = $page->frame?->frame_number;
            $event = $scenario->events()->create([
                'user_id' => $currentActor->getKey(),
                'process_id' => $process->getKey(),
                'type' => SimulationEventType::PageRequest,
                'description' => "La CPU solicitó la página {$pageNumber} del proceso {$process->name}.",
                'metadata' => [
                    'page_number' => $pageNumber,
                    'page_id' => $page->getKey(),
                    'page_size_bytes' => $pageBytes,
                    'process_name' => $process->name,
                    'frame_number' => $frameNumber,
                ],
                'occurred_at' => now('UTC'),
            ]);

            return [
                'request_event_id' => $event->getKey(),
                'scenario_id' => $scenario->getKey(),
                'process_id' => $process->getKey(),
                'page_number' => $pageNumber,
                'frame_number' => $frameNumber,
                'present' => $page->present,
                'page_size_bytes' => $pageBytes,
            ];
        }, 3);
    }

    /** @return array<string, mixed> */
    public function requestPage(User $actor, int $scenarioId, int $processId, int $pageNumber): array
    {
        return DB::transaction(function () use ($actor, $scenarioId, $processId, $pageNumber): array {
            $request = $this->beginRequest($actor, $scenarioId, $processId, $pageNumber);

            return $this->resolveRequest($actor, $request['request_event_id']);
        }, 3);
    }

    /** @return array<string, mixed> */
    public function resolveRequest(User $actor, int $requestEventId): array
    {
        // Start the consistent read after obtaining the scenario lock, including on retries.
        $currentActor = $this->requestActor($actor);
        $initialRequest = SimulationEvent::findOrFail($requestEventId);
        $this->requestMetadata($initialRequest, $currentActor);

        return DB::transaction(function () use ($actor, $initialRequest, $requestEventId): array {
            Scenario::query()->lockForUpdate()->findOrFail($initialRequest->scenario_id);
            $currentActor = $this->requestActor($actor);
            $request = SimulationEvent::where('scenario_id', $initialRequest->scenario_id)->findOrFail($requestEventId);
            $requestMetadata = $this->requestMetadata($request, $currentActor);

            $completed = SimulationEvent::where('scenario_id', $request->scenario_id)
                ->where('process_id', $request->process_id)
                ->whereIn('type', [SimulationEventType::PageHit->value, SimulationEventType::PageLoaded->value])
                ->where('metadata->request_event_id', $requestEventId)
                ->orderBy('id')->first();
            if ($completed !== null) {
                $metadata = $completed->metadata;
                $result = is_array($metadata) ? ($metadata['result'] ?? null) : null;
                if (! is_array($result)
                    || ($metadata['page_id'] ?? null) !== $requestMetadata['page_id']
                    || ($result['request_event_id'] ?? null) !== $requestEventId
                    || ($result['scenario_id'] ?? null) !== $request->scenario_id
                    || ($result['process_id'] ?? null) !== $request->process_id
                    || ($result['page_number'] ?? null) !== $requestMetadata['page_number']
                    || ($result['page_size_bytes'] ?? null) !== $requestMetadata['page_size_bytes']) {
                    throw ValidationException::withMessages([
                        'request_event_id' => 'El resultado guardado de la solicitud no es válido.',
                    ]);
                }

                return $result;
            }

            if (SimulationEvent::where('scenario_id', $request->scenario_id)
                ->where('type', SimulationEventType::MemoryReset->value)
                ->where('id', '>', $requestEventId)->exists()) {
                throw ValidationException::withMessages([
                    'request_event_id' => 'La memoria se reinició después de esta solicitud. Inicia un nuevo acceso.',
                ]);
            }

            try {
                [$snapshot, $page] = $this->requestContext(
                    $currentActor,
                    $request->scenario_id,
                    $request->process_id,
                    $requestMetadata['page_number'],
                );
            } catch (ValidationException $exception) {
                if (array_key_exists('page_number', $exception->errors())) {
                    throw ValidationException::withMessages([
                        'request_event_id' => 'La página registrada en la solicitud ya no pertenece al proceso seleccionado.',
                    ]);
                }

                throw $exception;
            }
            $scenario = $snapshot['scenario'];
            $process = $snapshot['selected_process'];
            $pageBytes = $snapshot['configuration']->page_size_bytes;
            if ($page->getKey() !== $requestMetadata['page_id'] || $pageBytes !== $requestMetadata['page_size_bytes']) {
                throw ValidationException::withMessages([
                    'request_event_id' => 'La página o configuración actual no coincide con la solicitud registrada.',
                ]);
            }

            $now = now('UTC');
            $evicted = null;
            $wasPresent = $page->present;
            $frame = $page->frame;
            if (! $wasPresent) {
                $frame = $scenario->frames()->whereDoesntHave('page')->orderBy('frame_number')->lockForUpdate()->first();
                $victim = null;
                if ($frame === null) {
                    // Hits never touch loaded_at, so this remains FIFO rather than LRU.
                    $victim = $scenario->pages()->whereNotNull('frame_id')->with(['frame', 'process'])
                        ->orderBy('loaded_at')->orderBy('id')->lockForUpdate()->first();
                    if ($victim === null || $victim->frame === null) {
                        throw ValidationException::withMessages([
                            'scenario_id' => 'No se pudo determinar un marco válido para cargar la página.',
                        ]);
                    }
                    $frame = $victim->frame;
                    $evicted = [
                        'page_id' => $victim->getKey(),
                        'process_id' => $victim->process_id,
                        'process_name' => $victim->process->name,
                        'page_number' => $victim->page_number,
                        'frame_number' => $frame->frame_number,
                    ];
                }

                // The target leaves secondary storage; an evicted page uses that same space.
                $secondaryAfter = $snapshot['secondary']['used_bytes'] - $pageBytes + ($victim === null ? 0 : $pageBytes);
                if ($secondaryAfter < 0 || $secondaryAfter > $snapshot['secondary']['total_bytes']) {
                    throw ValidationException::withMessages([
                        'scenario_id' => 'El intercambio excedería la capacidad del almacenamiento secundario simulado.',
                    ]);
                }

                $scenario->events()->create([
                    'user_id' => $currentActor->getKey(),
                    'process_id' => $process->getKey(),
                    'type' => SimulationEventType::PageFault,
                    'description' => "PAGE FAULT: la página {$page->page_number} no estaba presente en RAM.",
                    'metadata' => [
                        'request_event_id' => $requestEventId,
                        'page_id' => $page->getKey(),
                        'page_number' => $page->page_number,
                        'frame_number' => null,
                        'page_size_bytes' => $pageBytes,
                    ],
                    'occurred_at' => $now,
                ]);

                if ($victim !== null) {
                    $victim->update(['frame_id' => null, 'loaded_at' => null]);
                }
                $page->update(['frame_id' => $frame->getKey(), 'loaded_at' => $now]);
            }

            $result = [
                'request_event_id' => $requestEventId,
                'scenario_id' => $scenario->getKey(),
                'process_id' => $process->getKey(),
                'page_number' => $page->page_number,
                'frame_number' => $frame->frame_number,
                'present' => true,
                'page_size_bytes' => $pageBytes,
                'outcome' => $wasPresent ? SimulationEventType::PageHit->value : SimulationEventType::PageFault->value,
                'completed' => true,
                'physical_address' => $frame->frame_number * $pageBytes,
                'evicted' => $evicted,
            ];
            $metadata = [
                'request_event_id' => $requestEventId,
                'page_id' => $page->getKey(),
                'page_number' => $page->page_number,
                'frame_number' => $frame->frame_number,
                'page_size_bytes' => $pageBytes,
                'result' => $result,
            ];
            if (! $wasPresent) {
                $metadata['evicted'] = $evicted;
            }
            $scenario->events()->create([
                'user_id' => $currentActor->getKey(),
                'process_id' => $process->getKey(),
                'type' => $wasPresent ? SimulationEventType::PageHit : SimulationEventType::PageLoaded,
                'description' => $wasPresent
                    ? "PAGE HIT: la página {$page->page_number} se localizó en el marco {$frame->frame_number}."
                    : "Se cargó la página {$page->page_number} en el marco {$frame->frame_number} desde almacenamiento secundario simulado.",
                'metadata' => $metadata,
                'occurred_at' => $now,
            ]);

            if ($scenario->status !== ScenarioStatus::Running) {
                $scenario->update(['status' => ScenarioStatus::Running]);
            }
            if ($process->status !== ProcessStatus::Running) {
                $process->update(['status' => ProcessStatus::Running]);
            }

            return $result;
        }, 3);
    }

    /** @return array<string, mixed> */
    public function snapshot(User $actor, int $scenarioId, ?int $processId = null): array
    {
        $currentActor = User::findOrFail($actor->getKey());
        foreach ([PermissionName::ViewMemory, PermissionName::ViewTables, PermissionName::ViewSimulations] as $permission) {
            Gate::forUser($currentActor)->authorize($permission->value);
        }

        return DB::transaction(function () use ($scenarioId, $processId): array {
            $scenario = Scenario::with('configuration')->findOrFail($scenarioId);
            if ($scenario->mode !== SimulationMode::Paging) {
                throw ValidationException::withMessages([
                    'scenario_id' => 'Selecciona un escenario de paginación para consultar sus páginas.',
                ]);
            }

            $configuration = $scenario->configuration;
            $frameCount = $configuration?->frame_count;
            if ($configuration === null || $frameCount === null) {
                throw ValidationException::withMessages([
                    'scenario_id' => 'El escenario necesita una configuración de memoria válida.',
                ]);
            }

            $maxFrames = (int) config('memorylab.memory.max_frames', 1024);
            if ($frameCount > $maxFrames) {
                throw ValidationException::withMessages([
                    'scenario_id' => 'El escenario supera el límite de marcos permitido para su consulta.',
                ]);
            }

            $frames = $scenario->frames()
                ->selectRaw('COUNT(*) AS frame_total, MIN(frame_number) AS first_frame, MAX(frame_number) AS last_frame')
                ->toBase()->first();
            // With unique frame numbers, these aggregate values prove the range 0..N-1.
            if ((int) $frames->frame_total !== $frameCount
                || $frames->first_frame === null
                || (int) $frames->first_frame !== 0
                || (int) $frames->last_frame !== $frameCount - 1) {
                throw ValidationException::withMessages([
                    'scenario_id' => 'Los marcos del escenario no coinciden con la configuración de memoria.',
                ]);
            }

            $maxProcesses = (int) config('memorylab.processes.max_processes_per_scenario', 100);
            if ($scenario->processes()->count() > $maxProcesses) {
                throw ValidationException::withMessages([
                    'scenario_id' => 'El escenario supera el límite de procesos permitido para su consulta.',
                ]);
            }

            $processes = $scenario->processes()->withCount([
                'pages',
                'pages as resident_pages_count' => fn ($query) => $query->whereNotNull('frame_id'),
            ])->orderBy('name')->orderBy('id')->get();
            $selectedProcess = $processId === null ? null : $processes->firstWhere('id', $processId);
            if ($processId !== null && $selectedProcess === null) {
                throw ValidationException::withMessages([
                    'process_id' => 'Selecciona un proceso que pertenezca al escenario activo.',
                ]);
            }

            $maxPages = (int) config('memorylab.processes.max_pages_per_process', 1024);
            if ($selectedProcess !== null && $selectedProcess->pages_count > $maxPages) {
                throw ValidationException::withMessages([
                    'scenario_id' => 'El proceso seleccionado supera el límite de páginas permitido para su consulta.',
                ]);
            }

            $pages = $selectedProcess === null
                ? new Collection
                : $selectedProcess->pages()->with('frame')->orderBy('page_number')->get();

            $pageBytes = $configuration->page_size_bytes;
            $framesUsed = $scenario->frames()->whereHas('page')->count();
            $ramUsedBytes = $framesUsed * $pageBytes;
            $secondaryUsedBytes = $scenario->pages()->whereNull('frame_id')->count() * $pageBytes;
            if ($ramUsedBytes > $configuration->ram_size_bytes
                || $secondaryUsedBytes > $configuration->secondary_storage_bytes) {
                throw ValidationException::withMessages([
                    'scenario_id' => 'Las páginas del escenario exceden la capacidad de memoria configurada.',
                ]);
            }

            $selectedFramesUsed = $pages->whereNotNull('frame_id')->count();
            $selectedSecondaryPages = $pages->whereNull('frame_id')->count();
            $frameRows = $scenario->frames()->with('page.process')->orderBy('frame_number')->get();

            $request = $selectedProcess === null ? null : $scenario->events()
                ->where('process_id', $selectedProcess->getKey())
                ->where('type', SimulationEventType::PageRequest->value)
                ->orderByDesc('occurred_at')->orderByDesc('id')->first();
            $lastRequest = null;
            if ($request !== null) {
                $metadata = $request->metadata;
                $pageNumber = is_array($metadata) ? ($metadata['page_number'] ?? null) : null;
                $lastRequest = [
                    'event_id' => $request->getKey(),
                    'page_number' => is_int($pageNumber) && $pageNumber >= 0 ? $pageNumber : null,
                    'description' => $request->description,
                    'occurred_at' => $request->occurred_at,
                    'type' => SimulationEventType::PageRequest->value,
                ];
            }

            return [
                'scenario' => $scenario,
                'configuration' => $configuration,
                'processes' => $processes,
                'selected_process' => $selectedProcess,
                'pages' => $pages,
                'frames' => $frameRows,
                'ram' => [
                    'total_bytes' => $configuration->ram_size_bytes,
                    'used_bytes' => $ramUsedBytes,
                    'available_bytes' => $configuration->ram_size_bytes - $ramUsedBytes,
                    'frames_total' => $frameCount,
                    'frames_used' => $framesUsed,
                    'frames_free' => $frameCount - $framesUsed,
                    'selected_frames_used' => $selectedFramesUsed,
                ],
                'secondary' => [
                    'total_bytes' => $configuration->secondary_storage_bytes,
                    'used_bytes' => $secondaryUsedBytes,
                    'available_bytes' => $configuration->secondary_storage_bytes - $secondaryUsedBytes,
                    'selected_pages' => $selectedSecondaryPages,
                    'selected_bytes' => $selectedSecondaryPages * $pageBytes,
                ],
                'last_request' => $lastRequest,
                'last_result' => $this->lastResult($request, $pages, $processes, $pageBytes, $frameCount),
                'page_fault_count' => $scenario->events()->where('type', SimulationEventType::PageFault->value)->count(),
            ];
        }, 3);
    }

    /** @return array<string, mixed>|null */
    private function lastResult(?SimulationEvent $request, Collection $pages, Collection $processes, int $pageBytes, int $frameCount): ?array
    {
        if ($request === null || $request->process_id === null
            || SimulationEvent::where('scenario_id', $request->scenario_id)
                ->where('type', SimulationEventType::MemoryReset->value)
                ->where('id', '>', $request->getKey())->exists()) {
            return null;
        }

        $requestMetadata = $request->metadata;
        if (! is_array($requestMetadata)
            || ! is_int($requestMetadata['page_id'] ?? null)
            || $requestMetadata['page_id'] <= 0
            || ! is_int($requestMetadata['page_number'] ?? null)
            || $requestMetadata['page_number'] < 0
            || ($requestMetadata['page_size_bytes'] ?? null) !== $pageBytes) {
            return null;
        }
        $page = $pages->firstWhere('id', $requestMetadata['page_id']);
        if ($page === null || $page->scenario_id !== $request->scenario_id
            || $page->process_id !== $request->process_id
            || $page->page_number !== $requestMetadata['page_number']) {
            return null;
        }

        $terminal = SimulationEvent::where('scenario_id', $request->scenario_id)
            ->where('process_id', $request->process_id)
            ->whereIn('type', [SimulationEventType::PageHit->value, SimulationEventType::PageLoaded->value])
            ->where('metadata->request_event_id', $request->getKey())
            ->orderBy('id')->first();
        if ($terminal === null || $terminal->getKey() <= $request->getKey()) {
            return null;
        }

        $metadata = $terminal->metadata;
        $result = is_array($metadata) ? ($metadata['result'] ?? null) : null;
        $expectedOutcome = $terminal->type === SimulationEventType::PageHit
            ? SimulationEventType::PageHit->value : SimulationEventType::PageFault->value;
        if (! is_array($result)
            || ($metadata['request_event_id'] ?? null) !== $request->getKey()
            || ($metadata['page_id'] ?? null) !== $page->getKey()
            || ($metadata['page_number'] ?? null) !== $page->page_number
            || ($metadata['page_size_bytes'] ?? null) !== $pageBytes
            || ($result['request_event_id'] ?? null) !== $request->getKey()
            || ($result['scenario_id'] ?? null) !== $request->scenario_id
            || ($result['process_id'] ?? null) !== $request->process_id
            || ($result['page_number'] ?? null) !== $page->page_number
            || ($result['page_size_bytes'] ?? null) !== $pageBytes
            || ($result['present'] ?? null) !== true
            || ($result['completed'] ?? null) !== true
            || ($result['outcome'] ?? null) !== $expectedOutcome
            || ! is_int($result['frame_number'] ?? null)
            || $result['frame_number'] < 0 || $result['frame_number'] >= $frameCount
            || ($metadata['frame_number'] ?? null) !== $result['frame_number']
            || ($result['physical_address'] ?? null) !== $result['frame_number'] * $pageBytes
            || ! array_key_exists('evicted', $result)) {
            return null;
        }

        $evicted = $result['evicted'];
        if ($terminal->type === SimulationEventType::PageHit) {
            if ($evicted !== null || ($metadata['evicted'] ?? null) !== null) {
                return null;
            }
        } elseif (! array_key_exists('evicted', $metadata)
            || $metadata['evicted'] !== $evicted
            || ! $this->validStoredEviction($evicted, $request, $page, $processes, $result['frame_number'])) {
            return null;
        }

        return [
            'request_event_id' => $result['request_event_id'],
            'scenario_id' => $result['scenario_id'],
            'process_id' => $result['process_id'],
            'page_number' => $result['page_number'],
            'frame_number' => $result['frame_number'],
            'present' => true,
            'page_size_bytes' => $result['page_size_bytes'],
            'outcome' => $result['outcome'],
            'completed' => true,
            'physical_address' => $result['physical_address'],
            'evicted' => $evicted,
            'occurred_at' => $terminal->occurred_at,
        ];
    }

    private function validStoredEviction(mixed $evicted, SimulationEvent $request, Page $target, Collection $processes, int $frameNumber): bool
    {
        if ($evicted === null) {
            return true;
        }
        if (! is_array($evicted) || count($evicted) !== 5
            || ! is_int($evicted['page_id'] ?? null) || $evicted['page_id'] <= 0
            || $evicted['page_id'] === $target->getKey()
            || ! is_int($evicted['process_id'] ?? null) || $evicted['process_id'] <= 0
            || $processes->firstWhere('id', $evicted['process_id']) === null
            || ! is_string($evicted['process_name'] ?? null)
            || trim($evicted['process_name']) === '' || mb_strlen($evicted['process_name']) > 100
            || ! is_int($evicted['page_number'] ?? null) || $evicted['page_number'] < 0
            || $evicted['page_number'] >= (int) config('memorylab.processes.max_pages_per_process', 1024)
            || ($evicted['frame_number'] ?? null) !== $frameNumber) {
            return false;
        }

        return Page::where('scenario_id', $request->scenario_id)
            ->where('process_id', $evicted['process_id'])
            ->where('page_number', $evicted['page_number'])
            ->where('id', $evicted['page_id'])->exists();
    }

    /** @return array{0:array<string, mixed>,1:Page} */
    private function requestContext(User $actor, int $scenarioId, int $processId, int $pageNumber): array
    {
        $snapshot = $this->snapshot($actor, $scenarioId, $processId);
        $scenario = $snapshot['scenario'];
        $process = $snapshot['selected_process'];
        if (! in_array($scenario->status, [ScenarioStatus::Ready, ScenarioStatus::Running], true)) {
            throw ValidationException::withMessages([
                'scenario_id' => 'La CPU requiere un escenario listo o en ejecución.',
            ]);
        }
        if ($process->status === ProcessStatus::Terminated) {
            throw ValidationException::withMessages([
                'process_id' => 'No se pueden solicitar páginas de un proceso finalizado.',
            ]);
        }

        $maxPages = (int) config('memorylab.processes.max_pages_per_process', 1024);
        if ($pageNumber < 0 || $pageNumber >= $maxPages) {
            throw ValidationException::withMessages([
                'page_number' => 'Selecciona un número de página dentro del rango permitido.',
            ]);
        }

        $pages = $snapshot['pages'];
        $pageBytes = $snapshot['configuration']->page_size_bytes;
        $expectedPages = intdiv($process->size_bytes + $pageBytes - 1, $pageBytes);
        if ($pages->count() !== $expectedPages
            || $pages->min('page_number') !== 0
            || $pages->max('page_number') !== $expectedPages - 1) {
            throw ValidationException::withMessages([
                'process_id' => 'Las páginas del proceso no coinciden con su tamaño y la configuración de memoria.',
            ]);
        }

        $page = $pages->firstWhere('page_number', $pageNumber);
        if ($page === null) {
            throw ValidationException::withMessages([
                'page_number' => 'La página solicitada no pertenece al proceso seleccionado.',
            ]);
        }

        return [$snapshot, $page];
    }

    private function requestActor(User $actor): User
    {
        $currentActor = User::findOrFail($actor->getKey());
        foreach ([
            PermissionName::RequestPages,
            PermissionName::ExecuteSimulations,
            PermissionName::ViewMemory,
            PermissionName::ViewTables,
            PermissionName::ViewSimulations,
        ] as $permission) {
            Gate::forUser($currentActor)->authorize($permission->value);
        }

        return $currentActor;
    }

    /** @return array{page_id:int,page_number:int,page_size_bytes:int} */
    private function requestMetadata(SimulationEvent $request, User $actor): array
    {
        if ($request->user_id !== $actor->getKey()) {
            throw new AuthorizationException('Solo puedes completar las solicitudes de CPU que registraste.');
        }

        $metadata = $request->metadata;
        if ($request->type !== SimulationEventType::PageRequest
            || $request->process_id === null
            || ! is_array($metadata)
            || ! is_int($metadata['page_id'] ?? null)
            || $metadata['page_id'] <= 0
            || ! is_int($metadata['page_number'] ?? null)
            || $metadata['page_number'] < 0
            || ! is_int($metadata['page_size_bytes'] ?? null)
            || $metadata['page_size_bytes'] <= 0) {
            throw ValidationException::withMessages([
                'request_event_id' => 'La solicitud registrada no contiene una referencia válida a su página.',
            ]);
        }

        return [
            'page_id' => $metadata['page_id'],
            'page_number' => $metadata['page_number'],
            'page_size_bytes' => $metadata['page_size_bytes'],
        ];
    }
}
