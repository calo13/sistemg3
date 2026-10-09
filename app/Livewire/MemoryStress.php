<?php

namespace App\Livewire;

use App\Enums\PermissionName;
use App\Enums\SimulationEventType;
use App\Enums\SimulationMode;
use App\Models\Scenario;
use App\Models\User;
use App\Services\ActiveScenarioService;
use App\Services\MemoryStressService;
use App\Services\PagingService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class MemoryStress extends Component
{
    public $scenarioId = null;

    public $processCount = 3;

    public $sizeKb = 2;

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
        $this->reset('result');
        $this->resetValidation();
    }

    public function run(): void
    {
        $actor = $this->reader();
        foreach ([PermissionName::CreateProcesses, PermissionName::RequestPages, PermissionName::ExecuteSimulations] as $permission) {
            Gate::forUser($actor)->authorize($permission->value);
        }
        $this->reset('result');
        $this->validate([
            'scenarioId' => ['required', 'integer', 'min:1'],
            'processCount' => ['required', 'integer', 'min:1', 'max:8'],
            'sizeKb' => ['required', 'integer', 'min:1', 'max:64'],
        ]);
        try {
            $this->result = app(MemoryStressService::class)->run($actor, (int) $this->scenarioId, [
                'process_count' => $this->processCount, 'size_kb' => $this->sizeKb,
            ]);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages($this->formErrors($exception));
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages(['scenarioId' => 'El escenario seleccionado ya no está disponible.']);
        }
        $this->dispatch('memory-updated');
    }

    public function render(): View
    {
        $actor = $this->reader();
        $snapshot = null;
        $id = filter_var($this->scenarioId, FILTER_VALIDATE_INT);
        if ($id !== false && $id > 0) {
            try {
                $snapshot = app(PagingService::class)->snapshot($actor, $id);
            } catch (ValidationException $exception) {
                foreach ($this->formErrors($exception) as $field => $messages) {
                    $this->addError($field, $messages[0]);
                }
            } catch (ModelNotFoundException) {
                $this->addError('scenarioId', 'El escenario seleccionado ya no está disponible.');
            }
        }

        return view('livewire.memory-stress', [
            'snapshot' => $snapshot,
            'pageFaultCount' => $snapshot ? $snapshot['scenario']->events()->where('type', SimulationEventType::PageFault->value)->count() : 0,
            'scenarios' => Scenario::where('mode', SimulationMode::Paging)->orderBy('name')->orderBy('id')->get(['id', 'name']),
            'canRun' => collect([PermissionName::CreateProcesses, PermissionName::RequestPages, PermissionName::ExecuteSimulations])->every(fn ($permission) => Gate::forUser($actor)->allows($permission->value)),
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

    private function formErrors(ValidationException $exception): array
    {
        $fields = ['scenario_id' => 'scenarioId', 'process_count' => 'processCount', 'size_kb' => 'sizeKb'];
        $errors = [];
        foreach ($exception->errors() as $field => $messages) {
            $errors[$fields[$field] ?? $field] = $messages;
        }

        return $errors;
    }
}
