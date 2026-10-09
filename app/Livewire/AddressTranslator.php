<?php

namespace App\Livewire;

use App\Enums\PermissionName;
use App\Enums\SimulationMode;
use App\Models\Scenario;
use App\Models\User;
use App\Services\ActiveScenarioService;
use App\Services\AddressTranslationService;
use App\Services\PagingService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AddressTranslator extends Component
{
    public $scenarioId = null;

    public $processId = null;

    public $logicalAddress = 0;

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
        $this->reset('processId', 'logicalAddress', 'result');
        $this->resetValidation();
        $this->dispatch('scenario-selected');
    }

    public function updatedProcessId(): void
    {
        $this->reset('result');
        $this->resetValidation();
    }

    public function updatedLogicalAddress(): void
    {
        $this->reset('result');
        $this->resetValidation();
    }

    public function translate(): void
    {
        $actor = $this->reader();
        $this->reset('result');
        $this->validate([
            'scenarioId' => ['required', 'integer', 'min:1'],
            'processId' => ['required', 'integer', 'min:1'],
            'logicalAddress' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ]);
        try {
            $this->result = app(AddressTranslationService::class)->translate($actor, (int) $this->scenarioId, (int) $this->processId, (int) $this->logicalAddress);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages($this->formErrors($exception));
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages(['scenarioId' => 'El escenario seleccionado ya no está disponible.']);
        }
    }

    public function render(): View
    {
        $actor = $this->reader();
        $snapshot = null;
        $scenarioId = filter_var($this->scenarioId, FILTER_VALIDATE_INT);
        $processId = $this->processId === null || $this->processId === '' ? null : filter_var($this->processId, FILTER_VALIDATE_INT);
        if ($scenarioId !== false && $scenarioId > 0) {
            try {
                if ($processId === false || ($processId !== null && $processId <= 0)) {
                    throw ValidationException::withMessages(['process_id' => 'Selecciona un proceso del escenario.']);
                }
                $snapshot = app(PagingService::class)->snapshot($actor, $scenarioId, $processId);
            } catch (ValidationException $exception) {
                foreach ($this->formErrors($exception) as $field => $messages) {
                    $this->addError($field, $messages[0]);
                }
            } catch (ModelNotFoundException) {
                $this->addError('scenarioId', 'El escenario seleccionado ya no está disponible.');
            }
        }

        return view('livewire.address-translator', [
            'snapshot' => $snapshot,
            'scenarios' => Scenario::where('mode', SimulationMode::Paging)->orderBy('name')->orderBy('id')->get(['id', 'name']),
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
        $fields = ['scenario_id' => 'scenarioId', 'process_id' => 'processId', 'logical_address' => 'logicalAddress'];
        $errors = [];
        foreach ($exception->errors() as $field => $messages) {
            $errors[$fields[$field] ?? $field] = $messages;
        }

        return $errors;
    }
}
