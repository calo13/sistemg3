<?php

namespace App\Livewire;

use App\Enums\PermissionName;
use App\Enums\SimulationMode;
use App\Models\Scenario;
use App\Models\User;
use App\Services\ActiveScenarioService;
use App\Services\PagingService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class PagingSimulator extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $scenarioId = null;

    public $processId = null;

    #[Locked]
    public bool $presentation = false;

    public function mount(): void
    {
        $this->reader();
        $active = app(ActiveScenarioService::class)->current();
        $this->scenarioId = $this->presentation && $active?->mode !== SimulationMode::Paging ? null : $active?->id;
    }

    public function updatedScenarioId(): void
    {
        $actor = $this->reader();
        app(ActiveScenarioService::class)->select($actor, $this->validatedScenarioId());
        $this->reset('processId');
        $this->resetPage('pagesPage');
        $this->resetPage('framesPage');
        $this->resetPage('secondaryPage');
        $this->resetValidation();
        $this->dispatch('scenario-selected');
    }

    public function updatedProcessId(): void
    {
        $actor = $this->reader();
        $scenarioId = $this->validatedScenarioId();
        $processId = $this->validatedProcessId();
        $this->resetPage('pagesPage');
        $this->resetPage('secondaryPage');
        $this->resetValidation();

        if ($scenarioId === null) {
            if ($processId !== null) {
                throw ValidationException::withMessages(['processId' => 'Selecciona primero un escenario configurado.']);
            }

            return;
        }

        try {
            app(PagingService::class)->snapshot($actor, $scenarioId, $processId);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages($this->formErrors($exception));
        }
        app(ActiveScenarioService::class)->select($actor, $scenarioId);
    }

    #[On('process-created')]
    #[On('memory-updated')]
    public function refreshSnapshot(): void
    {
        $this->reader();
    }

    public function render(): View
    {
        $actor = $this->reader();
        $snapshot = $this->readSnapshot($actor);
        $pages = $snapshot['pages'] ?? collect();
        $frames = $snapshot['frames'] ?? collect();
        if (! is_array($this->paginators)) {
            $this->paginators = [];
        }
        $requestedPage = filter_var($this->getPage('pagesPage'), FILTER_VALIDATE_INT);
        $lastPage = max(1, intdiv($pages->count() + 14, 15));
        $page = $requestedPage === false ? 1 : max(1, min($lastPage, $requestedPage));
        $this->paginators['pagesPage'] = $page;

        return view($this->presentation ? 'livewire.memory-presentation' : 'livewire.paging-simulator', [
            'scenarios' => Scenario::when($this->presentation, fn ($query) => $query->where('mode', SimulationMode::Paging))->orderBy('name')->orderBy('id')->get(['id', 'name']),
            'snapshot' => $snapshot,
            'pageRows' => new LengthAwarePaginator($pages->forPage($page, 15)->values(), $pages->count(), 15, $page, [
                'pageName' => 'pagesPage', 'path' => route('paging.index'),
            ]),
            'frameRows' => $this->paginateRows($frames, 64, 'framesPage'),
            'secondaryRows' => $this->paginateRows($pages->whereNull('frame_id')->values(), 15, 'secondaryPage'),
            'canCreateProcesses' => Gate::forUser($actor)->allows(PermissionName::CreateProcesses->value),
        ]);
    }

    private function paginateRows($rows, int $perPage, string $pageName): LengthAwarePaginator
    {
        $requested = filter_var($this->getPage($pageName), FILTER_VALIDATE_INT);
        $last = max(1, intdiv($rows->count() + $perPage - 1, $perPage));
        $page = $requested === false ? 1 : max(1, min($last, $requested));
        $this->paginators[$pageName] = $page;

        return new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page, [
            'pageName' => $pageName, 'path' => route('paging.index'),
        ]);
    }

    private function reader(): User
    {
        abort_unless(auth()->check(), 403);
        $actor = User::findOrFail(auth()->id());
        foreach ([PermissionName::ViewMemory, PermissionName::ViewTables, PermissionName::ViewSimulations] as $permission) {
            Gate::forUser($actor)->authorize($permission->value);
        }
        if ($this->presentation) {
            Gate::forUser($actor)->authorize(PermissionName::ViewResults->value);
        }

        return $actor;
    }

    private function validatedScenarioId(): ?int
    {
        if ($this->scenarioId === null || $this->scenarioId === '') {
            return null;
        }
        Validator::make(['scenarioId' => $this->scenarioId], [
            'scenarioId' => ['required', 'integer', 'min:1', 'exists:scenarios,id'],
        ])->validate();

        return (int) $this->scenarioId;
    }

    private function validatedProcessId(): ?int
    {
        if ($this->processId === null || $this->processId === '') {
            return null;
        }
        Validator::make(['processId' => $this->processId], [
            'processId' => ['required', 'integer', 'min:1'],
        ])->validate();

        return (int) $this->processId;
    }

    private function readSnapshot(User $actor): ?array
    {
        if ($this->scenarioId === null || $this->scenarioId === '') {
            return null;
        }
        $scenarioId = filter_var($this->scenarioId, FILTER_VALIDATE_INT);
        if ($scenarioId === false || $scenarioId <= 0) {
            $this->addError('scenarioId', 'Selecciona un escenario existente.');

            return null;
        }
        $processId = null;
        if ($this->processId !== null && $this->processId !== '') {
            $processId = filter_var($this->processId, FILTER_VALIDATE_INT);
            if ($processId === false || $processId <= 0) {
                $this->addError('processId', 'Selecciona un proceso del escenario.');

                return null;
            }
        }

        try {
            $snapshot = app(PagingService::class)->snapshot($actor, $scenarioId, $processId);
            $this->resetValidation(['scenarioId', 'processId']);

            return $snapshot;
        } catch (ValidationException $exception) {
            foreach ($this->formErrors($exception) as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }
        } catch (ModelNotFoundException) {
            $this->addError('scenarioId', 'El escenario seleccionado ya no está disponible.');
        }

        return null;
    }

    /** @return array<string, list<string>> */
    private function formErrors(ValidationException $exception): array
    {
        $fields = ['scenario_id' => 'scenarioId', 'process_id' => 'processId'];
        $errors = [];
        foreach ($exception->errors() as $field => $messages) {
            $errors[$fields[$field] ?? $field] = $messages;
        }

        return $errors;
    }
}
