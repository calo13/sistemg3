<?php

namespace App\Livewire;

use App\Enums\PermissionName;
use App\Enums\ProcessStatus;
use App\Models\User;
use App\Services\PagingFlowService;
use App\Services\PagingService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class PageRequest extends Component
{
    #[Reactive]
    public $scenarioId = null;

    #[Reactive]
    public $processId = null;

    public $pageNumber = 0;

    public $mode = 'automatic';

    #[Locked]
    public ?array $pending = null;

    #[Locked]
    public int $step = 0;

    #[Locked]
    public bool $stateChanged = false;

    #[Locked]
    public ?array $result = null;

    public function updatedPageNumber(): void
    {
        $this->clearFlow();
        $this->resetValidation();
    }

    public function updatedMode(): void
    {
        $this->clearFlow();
        $this->resetValidation();
    }

    public function clearFlow(): void
    {
        $this->reader();
        $this->reset('result', 'pending', 'step', 'stateChanged');
    }

    public function requestPage(): void
    {
        $actor = $this->reader();
        Gate::forUser($actor)->authorize(PermissionName::RequestPages->value);
        Gate::forUser($actor)->authorize(PermissionName::ExecuteSimulations->value);
        $this->validate([
            'scenarioId' => ['required', 'integer', 'min:1'],
            'processId' => ['required', 'integer', 'min:1'],
            'pageNumber' => ['required', 'integer', 'min:0', 'max:1023'],
            'mode' => ['required', 'in:automatic,step'],
        ]);
        $this->clearFlow();
        try {
            if ($this->mode === 'step') {
                $this->pending = app(PagingService::class)->beginRequest($actor, (int) $this->scenarioId, (int) $this->processId, (int) $this->pageNumber);
                $this->step = 1;
            } else {
                $this->result = app(PagingService::class)->requestPage($actor, (int) $this->scenarioId, (int) $this->processId, (int) $this->pageNumber);
                $this->step = $this->result['outcome'] === 'PAGE_HIT' ? 4 : 8;
                $this->announceAccess();
            }
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages($this->formErrors($exception));
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages(['scenarioId' => 'El escenario seleccionado ya no está disponible.']);
        }
        $this->dispatch('memory-updated');
    }

    public function nextStep(): void
    {
        $actor = $this->reader();
        Gate::forUser($actor)->authorize(PermissionName::RequestPages->value);
        Gate::forUser($actor)->authorize(PermissionName::ExecuteSimulations->value);
        if ($this->pending === null || $this->step >= ($this->pending['present'] ? 4 : 8)) {
            return;
        }
        $this->step++;
        $commitStep = $this->pending['present'] ? 4 : 6;
        if ($this->result === null && $this->step === $commitStep) {
            try {
                $this->result = app(PagingService::class)->resolveRequest($actor, $this->pending['request_event_id']);
            } catch (ValidationException $exception) {
                $this->clearFlow();
                throw ValidationException::withMessages($this->formErrors($exception));
            } catch (ModelNotFoundException) {
                $this->clearFlow();
                throw ValidationException::withMessages(['pageNumber' => 'La solicitud o sus datos ya no están disponibles.']);
            }
            $hit = $this->result['outcome'] === 'PAGE_HIT';
            if ($hit !== $this->pending['present']) {
                $this->stateChanged = true;
                $this->pending['present'] = $hit;
                $this->step = $hit ? 4 : 8;
            }
            $this->announceAccess();
            $this->dispatch('memory-updated');
        }
    }

    private function announceAccess(): void
    {
        $this->dispatch('memory-accessed', requestEventId: $this->result['request_event_id'], scenarioId: $this->result['scenario_id'], processId: $this->result['process_id'], pageNumber: $this->result['page_number'], frameNumber: $this->result['frame_number'], hit: $this->result['outcome'] === 'PAGE_HIT', automatic: $this->mode === 'automatic');
    }

    public function render(): View
    {
        $actor = $this->reader();
        $snapshot = null;
        $scenarioId = filter_var($this->scenarioId, FILTER_VALIDATE_INT);
        $processId = filter_var($this->processId, FILTER_VALIDATE_INT);
        if ($scenarioId !== false && $scenarioId > 0 && $processId !== false && $processId > 0) {
            try {
                $snapshot = app(PagingService::class)->snapshot($actor, $scenarioId, $processId);
            } catch (ValidationException $exception) {
                foreach ($this->formErrors($exception) as $field => $messages) {
                    $this->addError($field, $messages[0]);
                }
            } catch (ModelNotFoundException) {
                $this->addError('scenarioId', 'El escenario seleccionado ya no está disponible.');
            }
        }

        if (($snapshot['selected_process']->status ?? null) === ProcessStatus::Terminated) {
            $this->clearFlow();
        }
        $displayResult = $this->result ?? ($this->pending === null ? ($snapshot['last_result'] ?? null) : null);
        $flowSteps = $this->pending !== null || $displayResult !== null
            ? app(PagingFlowService::class)->steps($this->pending['present'] ?? ($displayResult['outcome'] === 'PAGE_HIT')) : [];

        return view('livewire.page-request', [
            'snapshot' => $snapshot,
            'displayResult' => $displayResult,
            'flowSteps' => $flowSteps,
            'currentStep' => $this->pending !== null || $this->result !== null ? $this->step : count($flowSteps),
            'sharedResult' => $this->result === null && $displayResult !== null,
            'canRequest' => Gate::forUser($actor)->allows(PermissionName::RequestPages->value)
                && Gate::forUser($actor)->allows(PermissionName::ExecuteSimulations->value),
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

    private function formErrors(ValidationException $exception): array
    {
        $fields = ['scenario_id' => 'scenarioId', 'process_id' => 'processId', 'page_number' => 'pageNumber', 'request_event_id' => 'pageNumber'];
        $errors = [];
        foreach ($exception->errors() as $field => $messages) {
            $errors[$fields[$field] ?? $field] = $messages;
        }

        return $errors;
    }
}
