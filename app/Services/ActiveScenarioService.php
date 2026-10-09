<?php

namespace App\Services;

use App\Enums\PermissionName;
use App\Models\Scenario;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ActiveScenarioService
{
    public const SESSION_KEY = 'memorylab.active_scenario_id';

    public function current(): ?Scenario
    {
        $actor = auth()->user();
        $user = $actor ? User::find($actor->getKey()) : null;

        if (! $user || ! Gate::forUser($user)->allows(PermissionName::ViewMemory->value)) {
            return null;
        }

        $id = session(self::SESSION_KEY);
        if (! is_int($id) || $id <= 0) {
            session()->forget(self::SESSION_KEY);

            return null;
        }

        $scenario = Scenario::with('configuration')->find($id);
        if (! $scenario) {
            session()->forget(self::SESSION_KEY);
        }

        return $scenario;
    }

    public function select(User $actor, ?int $id): void
    {
        Gate::forUser(User::findOrFail($actor->getKey()))->authorize(PermissionName::ViewMemory->value);

        if ($id === null) {
            session()->forget(self::SESSION_KEY);

            return;
        }

        if ($id <= 0 || ! Scenario::whereKey($id)->exists()) {
            throw ValidationException::withMessages(['scenarioId' => 'Selecciona un escenario existente.']);
        }

        session()->put(self::SESSION_KEY, $id);
    }
}
