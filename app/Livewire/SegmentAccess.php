<?php

namespace App\Livewire;

use App\Enums\PermissionName;
use App\Enums\ProcessStatus;
use App\Enums\SegmentStatus;
use App\Models\User;
use App\Services\SegmentationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class SegmentAccess extends Component
{
    #[Reactive]
    public $scenarioId = null;

    #[Reactive]
    public $processId = null;

    public $segmentNumber = 0;

    public $offset = 0;

    #[Locked]
    public ?array $result = null;

    public function mount(): void
    {
        $actor = $this->reader();
        $scenarioId = filter_var($this->scenarioId, FILTER_VALIDATE_INT);
        $processId = filter_var($this->processId, FILTER_VALIDATE_INT);
        if ($scenarioId !== false && $scenarioId > 0 && $processId !== false && $processId > 0) {
            try {
                $snapshot = app(SegmentationService::class)->snapshot($actor, $scenarioId, $processId);
                $this->segmentNumber = $snapshot['segments']->first(fn ($segment) => $segment->status === SegmentStatus::Active)?->segment_number;
            } catch (ValidationException|ModelNotFoundException) {
                $this->segmentNumber = null;
            }
        }
    }

    public function updatedSegmentNumber(): void
    {
        $this->reset('result');
        $this->resetValidation();
    }

    public function updatedOffset(): void
    {
        $this->reset('result');
        $this->resetValidation();
    }

    public function access(): void
    {
        $actor = $this->reader();
        Gate::forUser($actor)->authorize(PermissionName::ExecuteSegmentation->value);
        Gate::forUser($actor)->authorize(PermissionName::ExecuteSimulations->value);
        $this->reset('result');
        $this->validate([
            'scenarioId' => ['required', 'integer', 'min:1'], 'processId' => ['required', 'integer', 'min:1'],
            'segmentNumber' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'offset' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ]);
        try {
            $this->result = app(SegmentationService::class)->access($actor, (int) $this->scenarioId, (int) $this->processId, (int) $this->segmentNumber, (int) $this->offset);
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
        $scenarioId = filter_var($this->scenarioId, FILTER_VALIDATE_INT);
        $processId = filter_var($this->processId, FILTER_VALIDATE_INT);
        if ($scenarioId !== false && $scenarioId > 0 && $processId !== false && $processId > 0) {
            try {
                $snapshot = app(SegmentationService::class)->snapshot($actor, $scenarioId, $processId);
            } catch (ValidationException $exception) {
                foreach ($this->formErrors($exception) as $field => $messages) {
                    $this->addError($field, $messages[0]);
                }
            } catch (ModelNotFoundException) {
                $this->addError('scenarioId', 'El escenario seleccionado ya no está disponible.');
            }
        }

        if (($snapshot['selected_process']->status ?? null) === ProcessStatus::Terminated) {
            $this->reset('result');
        }

        return view('livewire.segment-access', [
            'snapshot' => $snapshot,
            'displayResult' => $this->result ?? ($snapshot['last_access'] ?? null),
            'sharedResult' => $this->result === null && ($snapshot['last_access'] ?? null) !== null,
            'canAccess' => Gate::forUser($actor)->allows(PermissionName::ExecuteSegmentation->value) && Gate::forUser($actor)->allows(PermissionName::ExecuteSimulations->value),
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
        $fields = ['scenario_id' => 'scenarioId', 'process_id' => 'processId', 'segment_number' => 'segmentNumber'];
        $errors = [];
        foreach ($exception->errors() as $field => $messages) {
            $errors[$fields[$field] ?? $field] = $messages;
        }

        return $errors;
    }
}
