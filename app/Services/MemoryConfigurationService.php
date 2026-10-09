<?php

namespace App\Services;

use App\Enums\PermissionName;
use App\Enums\ScenarioStatus;
use App\Enums\SimulationEventType;
use App\Enums\SimulationMode;
use App\Models\MemoryConfiguration;
use App\Models\MemoryFrame;
use App\Models\Scenario;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MemoryConfigurationService
{
    /**
     * Configure educational memory only; no physical RAM or storage is reserved.
     *
     * @param  array<string, mixed>  $input
     */
    public function configure(User $actor, ?int $scenarioId, array $input): Scenario
    {
        return DB::transaction(function () use ($actor, $scenarioId, $input): Scenario {
            // Allocation services must also lock this scenario before changing its memory.
            $scenario = $scenarioId === null
                ? null
                : Scenario::query()->lockForUpdate()->findOrFail($scenarioId);

            // A component rendered earlier cannot retain revoked permissions.
            $currentActor = User::findOrFail($actor->getKey());
            Gate::forUser($currentActor)->authorize(PermissionName::ConfigureMemory->value);
            if ($scenario === null) {
                Gate::forUser($currentActor)->authorize(PermissionName::CreateScenarios->value);
            }

            $validated = $this->validateInput($input, $scenario === null);
            $values = [
                'ram_size_bytes' => (int) $validated['ram_kb'] * 1024,
                'page_size_bytes' => (int) $validated['page_kb'] * 1024,
                'secondary_storage_bytes' => (int) $validated['secondary_kb'] * 1024,
            ];
            $frameCount = intdiv($values['ram_size_bytes'], $values['page_size_bytes']);

            if ($scenario !== null && $scenario->mode !== SimulationMode::Paging) {
                throw ValidationException::withMessages([
                    'scenario_id' => 'Esta configuración requiere un escenario de paginación.',
                ]);
            }

            $configuration = $scenario?->configuration()->first();
            if ($configuration !== null && $this->sameConfiguration($configuration, $values)) {
                return $scenario->load('configuration');
            }

            if ($scenario !== null && (! in_array($scenario->status, [ScenarioStatus::Draft, ScenarioStatus::Ready], true)
                || $scenario->processes()->exists()
                || $scenario->pages()->exists()
                || $scenario->segments()->exists())) {
                throw ValidationException::withMessages([
                    'scenario_id' => 'La memoria solo puede cambiarse en un escenario sin procesos ni asignaciones, en estado borrador o listo. Crea otro escenario para usar una configuración distinta.',
                ]);
            }

            $before = $configuration === null ? null : $this->snapshot($configuration);
            $now = now('UTC');

            if ($scenario === null) {
                $scenario = Scenario::create([
                    'name' => $validated['name'],
                    'mode' => SimulationMode::Paging,
                    'status' => ScenarioStatus::Draft,
                    'created_by' => $currentActor->getKey(),
                    'is_demo' => false,
                ]);
                $scenario->events()->create([
                    'user_id' => $currentActor->getKey(),
                    'type' => SimulationEventType::ScenarioCreated,
                    'description' => 'Se creó el escenario de paginación.',
                    'metadata' => [
                        'name' => $scenario->name,
                        'mode' => $scenario->mode->value,
                    ],
                    'occurred_at' => $now,
                ]);
            }

            // Existing events remain intact. Only unused frames may be replaced.
            $scenario->frames()->whereDoesntHave('page')->delete();
            $configuration = $scenario->configuration()->updateOrCreate([], $values);

            $frames = [];
            for ($number = 0; $number < $frameCount; $number++) {
                $frames[] = [
                    'scenario_id' => $scenario->getKey(),
                    'frame_number' => $number,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($frames, 256) as $batch) {
                MemoryFrame::insert($batch);
            }

            $scenario->update(['status' => ScenarioStatus::Ready]);
            $scenario->events()->create([
                'user_id' => $currentActor->getKey(),
                'type' => SimulationEventType::MemoryConfigured,
                'description' => "Se configuró la memoria simulada con {$frameCount} marcos.",
                'metadata' => [
                    'before' => $before,
                    'after' => $this->snapshot($configuration),
                ],
                'occurred_at' => $now,
            ]);

            return $scenario->refresh()->load('configuration');
        }, 3);
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function validateInput(array $input, bool $creating): array
    {
        $maxSize = (int) config('memorylab.memory.max_size_kb', 65536);
        $maxFrames = (int) config('memorylab.memory.max_frames', 1024);
        $rules = [
            'ram_kb' => ['required', 'integer', 'min:1', 'max:'.$maxSize],
            'page_kb' => ['required', 'integer', 'min:1', 'max:'.$maxSize],
            'secondary_kb' => ['required', 'integer', 'min:0', 'max:'.$maxSize],
        ];
        if ($creating) {
            $rules['name'] = ['required', 'string', 'max:100'];
            if (is_string($input['name'] ?? null)) {
                $input['name'] = trim($input['name']);
            }
        }

        $validated = Validator::make($input, $rules, [
            'required' => 'El campo :attribute es obligatorio.',
            'integer' => 'El campo :attribute debe ser un número entero de KB.',
            'min' => 'El campo :attribute debe ser al menos :min.',
            'max.numeric' => 'El campo :attribute no puede superar :max KB.',
            'max.string' => 'El campo :attribute no puede superar :max caracteres.',
            'string' => 'El campo :attribute debe ser texto.',
        ], [
            'name' => 'nombre del escenario',
            'ram_kb' => 'RAM total',
            'page_kb' => 'tamaño de página',
            'secondary_kb' => 'almacenamiento secundario',
        ])->validate();

        $ram = (int) $validated['ram_kb'];
        $page = (int) $validated['page_kb'];
        if ($page > $ram) {
            throw ValidationException::withMessages([
                'page_kb' => 'El tamaño de página no puede superar la RAM total.',
            ]);
        }
        if ($ram % $page !== 0) {
            throw ValidationException::withMessages([
                'ram_kb' => 'La RAM total debe ser un múltiplo exacto del tamaño de página.',
            ]);
        }
        if (intdiv($ram, $page) > $maxFrames) {
            throw ValidationException::withMessages([
                'ram_kb' => "La configuración no puede superar {$maxFrames} marcos. Aumenta el tamaño de página o reduce la RAM total.",
            ]);
        }

        return $validated;
    }

    /** @param array<string, int> $values */
    private function sameConfiguration(MemoryConfiguration $configuration, array $values): bool
    {
        return $configuration->ram_size_bytes === $values['ram_size_bytes']
            && $configuration->page_size_bytes === $values['page_size_bytes']
            && $configuration->secondary_storage_bytes === $values['secondary_storage_bytes'];
    }

    /** @return array<string, int|null> */
    private function snapshot(MemoryConfiguration $configuration): array
    {
        return [
            'ram_size_bytes' => $configuration->ram_size_bytes,
            'page_size_bytes' => $configuration->page_size_bytes,
            'secondary_storage_bytes' => $configuration->secondary_storage_bytes,
            'frame_count' => $configuration->frame_count,
        ];
    }
}
