<?php

namespace App\Livewire;

use App\Enums\PermissionName;
use App\Enums\ProcessStatus;
use App\Enums\ScenarioStatus;
use App\Enums\SimulationMode;
use App\Models\Scenario;
use App\Models\User;
use App\Services\ActiveScenarioService;
use App\Services\SimulationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class SimulationDemo extends Component
{
    public const SESSION_KEY = 'memorylab.demo';

    #[Locked]
    public ?array $demo = null;

    public $resetScenarioId = null;

    #[Locked]
    public ?array $resetResult = null;

    public function mount(): void
    {
        $this->reader();
        $saved = session(self::SESSION_KEY);
        if (is_array($saved)
            && is_int($saved['paging_scenario_id'] ?? null)
            && is_int($saved['segmentation_scenario_id'] ?? null)
            && Scenario::whereKey($saved['paging_scenario_id'])->where('is_demo', true)->where('mode', SimulationMode::Paging)->where('status', '!=', ScenarioStatus::Completed)->whereHas('processes', fn ($query) => $query->where('status', '!=', ProcessStatus::Terminated))->exists()
            && Scenario::whereKey($saved['segmentation_scenario_id'])->where('is_demo', true)->where('mode', SimulationMode::Segmentation)->where('status', '!=', ScenarioStatus::Completed)->whereHas('processes', fn ($query) => $query->where('status', '!=', ProcessStatus::Terminated))->exists()) {
            $this->demo = $saved;
        } else {
            session()->forget(self::SESSION_KEY);
        }
        $this->resetScenarioId = app(ActiveScenarioService::class)->current()?->id;
    }

    public function startDemo(): void
    {
        $actor = $this->reader();
        $this->resetValidation();
        $this->rememberDemo(app(SimulationService::class)->startDemo($actor), $actor);
    }

    public function restartDemo(): void
    {
        $actor = $this->reader();
        Gate::forUser($actor)->authorize(PermissionName::ResetMemory->value);
        if ($this->demo === null) {
            throw ValidationException::withMessages(['demo' => 'Inicia una demostración antes de reiniciarla.']);
        }
        try {
            $demo = app(SimulationService::class)->restartDemo($actor, $this->demo['paging_scenario_id'], $this->demo['segmentation_scenario_id']);
        } catch (ValidationException|ModelNotFoundException) {
            throw ValidationException::withMessages(['demo' => 'El par de demostración ya no está disponible para reiniciar.']);
        }
        $this->rememberDemo($demo, $actor);
    }

    public function updatedResetScenarioId(): void
    {
        $this->reset('resetResult');
        $this->resetValidation();
    }

    public function resetMemory(): void
    {
        $actor = $this->reader();
        Gate::forUser($actor)->authorize(PermissionName::ResetMemory->value);
        $this->validate(['resetScenarioId' => ['required', 'integer', 'min:1']]);
        $this->reset('resetResult');
        try {
            $this->resetResult = app(SimulationService::class)->reset($actor, (int) $this->resetScenarioId);
        } catch (ValidationException|ModelNotFoundException) {
            throw ValidationException::withMessages(['resetScenarioId' => 'Selecciona un escenario configurado y ejecutable para liberar la memoria.']);
        }
        if ($this->demo !== null && in_array((int) $this->resetScenarioId, [$this->demo['paging_scenario_id'], $this->demo['segmentation_scenario_id']], true)) {
            $this->reset('demo');
            session()->forget(self::SESSION_KEY);
        }
        $this->dispatch('memory-updated');
    }

    public function selectScenario(int $id): void
    {
        $actor = $this->reader();
        $scenario = Scenario::where('is_demo', true)->findOrFail($id);
        app(ActiveScenarioService::class)->select($actor, $id);
        $this->redirectRoute($scenario->mode === SimulationMode::Paging ? 'paging.index' : 'segmentation.index');
    }

    public function render(): View
    {
        $actor = $this->reader();
        $canStart = collect([
            PermissionName::ConfigureMemory, PermissionName::CreateScenarios, PermissionName::CreateProcesses,
            PermissionName::RequestPages, PermissionName::ExecuteSimulations, PermissionName::ExecuteSegmentation,
        ])->every(fn ($permission) => Gate::forUser($actor)->allows($permission->value));

        return view('livewire.simulation-demo', [
            'canStart' => $canStart,
            'canReset' => Gate::forUser($actor)->allows(PermissionName::ResetMemory->value) && Gate::forUser($actor)->allows(PermissionName::ExecuteSimulations->value),
            'scenarios' => Scenario::withCount(['processes as active_processes_count' => fn ($query) => $query->where('status', '!=', ProcessStatus::Terminated)])
                ->orderBy('name')->orderBy('id')->get(['id', 'name', 'is_demo', 'mode', 'status']),
        ]);
    }

    private function rememberDemo(array $demo, User $actor): void
    {
        $this->demo = $demo;
        session()->put(self::SESSION_KEY, $demo);
        app(ActiveScenarioService::class)->select($actor, $demo['paging_scenario_id']);
        $this->resetScenarioId = $demo['paging_scenario_id'];
        $this->reset('resetResult');
        $this->dispatch('memory-updated');
    }

    private function reader(): User
    {
        abort_unless(auth()->check(), 403);
        $actor = User::findOrFail(auth()->id());
        foreach ([PermissionName::ViewMemory, PermissionName::ViewTables, PermissionName::ViewSimulations] as $permission) {
            Gate::forUser($actor)->authorize($permission->value);
        }

        return $actor;
    }
}
