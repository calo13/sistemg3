<?php

namespace App\Livewire;

use App\Enums\PermissionName;
use App\Enums\SimulationMode;
use App\Models\Scenario;
use App\Models\User;
use App\Services\ActiveScenarioService;
use App\Services\SegmentationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class SegmentationSimulator extends Component
{
    public $scenarioId = null;

    public $processId = null;

    public $scenarioName = 'Segmentación';

    public $ramKb = 16;

    public $processName = '';

    public $processSizeKb = 4;

    public $segmentName = 'Código';

    public $segmentBase = 0;

    public $segmentSize = 1024;

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
        $this->reset('processId');
        $this->resetValidation();
        $this->dispatch('scenario-selected');
    }

    public function updatedProcessId(): void
    {
        $this->resetValidation();
    }

    #[On('memory-updated')]
    public function refreshSnapshot(): void
    {
        $this->reader();
    }

    public function configure(): void
    {
        $actor = $this->reader();
        Gate::forUser($actor)->authorize(PermissionName::ConfigureMemory->value);
        Gate::forUser($actor)->authorize(PermissionName::CreateScenarios->value);
        $this->validate(['scenarioName' => ['required', 'string', 'max:100'], 'ramKb' => ['required', 'integer', 'min:1', 'max:65536']]);
        $this->perform(function () use ($actor): void {
            $scenario = app(SegmentationService::class)->configure($actor, ['name' => $this->scenarioName, 'ram_kb' => $this->ramKb]);
            $this->scenarioId = $scenario->id;
            $this->reset('processId');
            app(ActiveScenarioService::class)->select($actor, $scenario->id);
            $this->dispatch('scenario-selected');
        });
    }

    public function createProcess(): void
    {
        $actor = $this->reader();
        Gate::forUser($actor)->authorize(PermissionName::CreateProcesses->value);
        $this->validate([
            'scenarioId' => ['required', 'integer', 'min:1'],
            'processName' => ['required', 'string', 'max:100'],
            'processSizeKb' => ['required', 'integer', 'min:1', 'max:65536'],
        ]);
        $this->perform(function () use ($actor): void {
            $process = app(SegmentationService::class)->createProcess($actor, (int) $this->scenarioId, ['name' => $this->processName, 'size_kb' => $this->processSizeKb]);
            $this->processId = $process->id;
            $this->reset('processName');
            $this->dispatch('memory-updated');
        }, ['name' => 'processName', 'size_kb' => 'processSizeKb']);
    }

    public function createSegment(): void
    {
        $actor = $this->reader();
        Gate::forUser($actor)->authorize(PermissionName::ExecuteSegmentation->value);
        Gate::forUser($actor)->authorize(PermissionName::ExecuteSimulations->value);
        $this->validate([
            'scenarioId' => ['required', 'integer', 'min:1'], 'processId' => ['required', 'integer', 'min:1'],
            'segmentName' => ['required', 'string', 'max:100'],
            'segmentBase' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'segmentSize' => ['required', 'integer', 'min:1', 'max:67108864'],
        ]);
        $this->perform(function () use ($actor): void {
            app(SegmentationService::class)->createSegment($actor, (int) $this->scenarioId, (int) $this->processId, ['name' => $this->segmentName, 'base' => $this->segmentBase, 'size_bytes' => $this->segmentSize]);
            $this->dispatch('memory-updated');
        }, ['name' => 'segmentName', 'base' => 'segmentBase', 'size_bytes' => 'segmentSize']);
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
                $snapshot = app(SegmentationService::class)->snapshot($actor, $scenarioId, $processId);
            } catch (ValidationException $exception) {
                foreach ($this->formErrors($exception) as $field => $messages) {
                    $this->addError($field, $messages[0]);
                }
            } catch (ModelNotFoundException) {
                $this->addError('scenarioId', 'El escenario seleccionado ya no está disponible.');
            }
        }

        return view('livewire.segmentation-simulator', [
            'snapshot' => $snapshot,
            'scenarios' => Scenario::where('mode', SimulationMode::Segmentation)->orderBy('name')->orderBy('id')->get(['id', 'name']),
            'canConfigure' => Gate::forUser($actor)->allows(PermissionName::ConfigureMemory->value) && Gate::forUser($actor)->allows(PermissionName::CreateScenarios->value),
            'canCreateProcess' => Gate::forUser($actor)->allows(PermissionName::CreateProcesses->value),
            'canCreateSegment' => Gate::forUser($actor)->allows(PermissionName::ExecuteSegmentation->value) && Gate::forUser($actor)->allows(PermissionName::ExecuteSimulations->value),
        ]);
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

    private function perform(callable $operation, array $fields = []): void
    {
        $this->resetValidation();
        try {
            $operation();
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages($this->formErrors($exception, $fields));
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages(['scenarioId' => 'El escenario seleccionado ya no está disponible.']);
        }
    }

    private function formErrors(ValidationException $exception, array $additional = []): array
    {
        $fields = $additional + ['scenario_id' => 'scenarioId', 'process_id' => 'processId', 'name' => 'scenarioName', 'ram_kb' => 'ramKb'];
        $errors = [];
        foreach ($exception->errors() as $field => $messages) {
            $errors[$fields[$field] ?? $field] = $messages;
        }

        return $errors;
    }
}
