<?php

namespace App\Services;

use App\Enums\PermissionName;
use App\Enums\SimulationEventType;
use App\Models\Process;
use App\Models\SimulationEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SimulationHistoryService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, SimulationEvent>
     */
    public function search(User $actor, array $filters, int $page = 1): LengthAwarePaginator
    {
        return DB::transaction(function () use ($actor, $filters, $page): LengthAwarePaginator {
            $currentActor = User::findOrFail($actor->getKey());
            Gate::forUser($currentActor)->authorize(PermissionName::ViewHistory->value);
            $allowed = ['scenario_id', 'process_id', 'user_id', 'type', 'from_date', 'to_date'];
            $input = array_intersect_key($filters, array_fill_keys($allowed, true));
            foreach ($input as $key => $value) {
                if ($value === '') {
                    $input[$key] = null;
                } elseif (is_string($value) && trim($value) === '') {
                    throw ValidationException::withMessages([$key => 'Selecciona un valor válido o deja el filtro vacío.']);
                }
            }
            $validated = Validator::make($input, [
                'scenario_id' => ['nullable', 'integer', 'min:1', 'exists:scenarios,id'],
                'process_id' => ['nullable', 'integer', 'min:1', 'exists:processes,id'],
                'user_id' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
                'type' => ['nullable', 'string', Rule::in(array_map(fn (SimulationEventType $type) => $type->value, SimulationEventType::cases()))],
                'from_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-30'],
                'to_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-30'],
            ], [
                'integer' => 'El campo :attribute debe ser un identificador entero.',
                'min' => 'El campo :attribute debe ser mayor que cero.',
                'exists' => 'El campo :attribute debe corresponder a un registro existente.',
                'string' => 'El campo :attribute debe ser texto.',
                'in' => 'Selecciona un tipo de evento válido.',
                'date_format' => 'El campo :attribute debe tener el formato AAAA-MM-DD.',
                'after_or_equal' => 'Selecciona una fecha desde 1000-01-01.',
                'before_or_equal' => 'Selecciona una fecha hasta 9999-12-30.',
            ], [
                'scenario_id' => 'escenario',
                'process_id' => 'proceso',
                'user_id' => 'usuario',
                'type' => 'tipo de evento',
                'from_date' => 'fecha inicial',
                'to_date' => 'fecha final',
            ])->validate();

            $scenarioId = isset($validated['scenario_id']) ? (int) $validated['scenario_id'] : null;
            $processId = isset($validated['process_id']) ? (int) $validated['process_id'] : null;
            if ($processId !== null && ($scenarioId === null
                || ! Process::where('scenario_id', $scenarioId)->whereKey($processId)->exists())) {
                throw ValidationException::withMessages([
                    'process_id' => 'Selecciona primero un escenario y un proceso que pertenezca a él.',
                ]);
            }
            if (isset($validated['from_date'], $validated['to_date'])
                && $validated['from_date'] > $validated['to_date']) {
                throw ValidationException::withMessages([
                    'to_date' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
                ]);
            }

            $query = SimulationEvent::query();
            foreach (['scenario_id' => $scenarioId, 'process_id' => $processId, 'user_id' => $validated['user_id'] ?? null, 'type' => $validated['type'] ?? null] as $column => $value) {
                if ($value !== null) {
                    $query->where($column, $value);
                }
            }
            $timezone = config('memorylab.display_timezone', 'America/Guatemala');
            if (isset($validated['from_date'])) {
                $from = CarbonImmutable::createFromFormat('!Y-m-d', $validated['from_date'], $timezone)->setTimezone('UTC');
                $query->where('occurred_at', '>=', $from->format('Y-m-d H:i:s.u'));
            }
            if (isset($validated['to_date'])) {
                $until = CarbonImmutable::createFromFormat('!Y-m-d', $validated['to_date'], $timezone)->addDay()->setTimezone('UTC');
                $query->where('occurred_at', '<', $until->format('Y-m-d H:i:s.u'));
            }

            $total = (clone $query)->count();
            $lastPage = max(1, intdiv($total + 19, 20));
            $currentPage = max(1, min($lastPage, $page));
            $events = $query->with(['scenario:id,name', 'process:id,name', 'user:id,name'])
                ->orderByDesc('occurred_at')->orderByDesc('id')->forPage($currentPage, 20)->get();

            return new LengthAwarePaginator($events, $total, 20, $currentPage, [
                'pageName' => 'historyPage',
                'path' => route('history.index'),
            ]);
        }, 3);
    }
}
