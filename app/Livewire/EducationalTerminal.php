<?php

namespace App\Livewire;

use App\Enums\PermissionName;
use App\Enums\SimulationMode;
use App\Models\Scenario;
use App\Models\User;
use App\Services\ActiveScenarioService;
use App\Services\EducationalTerminalService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class EducationalTerminal extends Component
{
    public $scenarioId = null;

    public $command = '';

    #[Locked]
    public array $lines = [];

    #[Locked]
    public ?array $result = null;

    public function mount(): void
    {
        $this->reader();
        $this->scenarioId = app(ActiveScenarioService::class)->current()?->id;
    }

    public function updatedScenarioId(): void
    {
        $actor = $this->reader();
        $this->validate(['scenarioId' => ['nullable', 'integer', 'min:1', 'exists:scenarios,id']]);
        app(ActiveScenarioService::class)->select($actor, $this->scenarioId === null || $this->scenarioId === '' ? null : (int) $this->scenarioId);
        $this->clear();
    }

    public function execute(): void
    {
        $actor = $this->reader();
        $this->validate([
            'scenarioId' => ['required', 'integer', 'min:1'],
            'command' => ['required', 'string', 'max:160'],
        ]);
        try {
            $result = app(EducationalTerminalService::class)->execute($actor, (int) $this->scenarioId, $this->command);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['command' => array_merge(...array_values($exception->errors()))]);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages(['scenarioId' => 'El escenario seleccionado ya no está disponible.']);
        }
        $this->result = $result;
        $this->lines = array_slice(array_merge($this->lines, ['> '.$result['command']], $result['lines']), -100);
        $this->reset('command');
        $this->resetValidation();
        if ($result['mutated']) {
            $this->dispatch('memory-updated');
        }
    }

    public function clear(): void
    {
        $this->reader();
        $this->reset('command', 'lines', 'result');
        $this->resetValidation();
    }

    public function render(): View
    {
        $actor = $this->reader();

        return view('livewire.educational-terminal', [
            'scenarios' => Scenario::whereIn('mode', [SimulationMode::Paging->value, SimulationMode::Segmentation->value])->orderBy('name')->orderBy('id')->get(['id', 'name']),
            'canRequest' => Gate::forUser($actor)->allows(PermissionName::RequestPages->value) && Gate::forUser($actor)->allows(PermissionName::ExecuteSimulations->value),
            'canReset' => Gate::forUser($actor)->allows(PermissionName::ResetMemory->value) && Gate::forUser($actor)->allows(PermissionName::ExecuteSimulations->value),
        ]);
    }

    private function reader(): User
    {
        abort_unless(auth()->check(), 403);
        $actor = User::findOrFail(auth()->id());
        foreach ([PermissionName::ViewMemory, PermissionName::ViewTables, PermissionName::ViewSimulations, PermissionName::ViewResults] as $permission) {
            Gate::forUser($actor)->authorize($permission->value);
        }

        return $actor;
    }
}
