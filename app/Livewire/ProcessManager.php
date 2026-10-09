<?php

namespace App\Livewire;

use App\Enums\PermissionName;
use App\Enums\ScenarioStatus;
use App\Enums\SimulationMode;
use App\Models\Process;
use App\Models\Scenario;
use App\Models\User;
use App\Services\ActiveScenarioService;
use App\Services\ProcessManagerService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class ProcessManager extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $scenarioId = null;

    public $name = '';

    public $sizeKb = 4;

    public function mount(): void
    {
        $this->reader();
        $this->scenarioId = app(ActiveScenarioService::class)->current()?->id;
    }

    public function updatedScenarioId(): void
    {
        $actor = $this->reader();
        app(ActiveScenarioService::class)->select($actor, $this->validatedScenarioId());
        $this->resetPage();
        $this->reset('name', 'sizeKb');
        $this->resetValidation();
        session()->forget('process-status');
        $this->dispatch('scenario-selected');
    }

    #[Computed]
    public function pageCount(): ?int
    {
        $size = filter_var($this->sizeKb, FILTER_VALIDATE_INT);
        $scenario = $this->selectedScenario();
        $pageSize = $scenario?->configuration?->page_size_bytes;

        if ($size === false || $size <= 0 || $size > (int) config('memorylab.processes.max_size_kb')
            || $pageSize === null || $pageSize <= 0) {
            return null;
        }

        return intdiv($size * 1024 + $pageSize - 1, $pageSize);
    }

    public function create(): void
    {
        $actor = $this->reader();
        Gate::forUser($actor)->authorize(PermissionName::CreateProcesses->value);
        $id = $this->validatedScenarioId();
        if ($id === null) {
            throw ValidationException::withMessages(['scenarioId' => 'Selecciona un escenario con memoria configurada.']);
        }

        try {
            $process = app(ProcessManagerService::class)->create($actor, $id, [
                'name' => $this->name,
                'size_kb' => $this->sizeKb,
            ]);
        } catch (ValidationException $exception) {
            $fields = ['size_kb' => 'sizeKb', 'scenario_id' => 'scenarioId'];
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $errors[$fields[$field] ?? $field] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }

        app(ActiveScenarioService::class)->select($actor, $id);
        $this->reset('name', 'sizeKb');
        $this->resetValidation();
        $this->resetPage();
        session()->flash('process-status', "Proceso {$process->name} creado con {$process->pages_count} páginas en almacenamiento secundario simulado.");
        $this->dispatch('process-created');
    }

    public function render(): View
    {
        $actor = $this->reader();
        $scenario = $this->selectedScenario();
        $capacity = $scenario ? app(ProcessManagerService::class)->capacity($scenario) : null;

        return view('livewire.process-manager', [
            'scenarios' => Scenario::orderBy('name')->orderBy('id')->get(['id', 'name']),
            'scenario' => $scenario,
            'capacity' => $capacity,
            'processes' => Process::where('scenario_id', $scenario?->id ?? 0)
                ->withCount('pages')->orderByDesc('id')->paginate(15),
            'canCreate' => Gate::forUser($actor)->allows(PermissionName::CreateProcesses->value),
            'canConfigure' => Gate::forUser($actor)->allows(PermissionName::ConfigureMemory->value),
            'acceptsProcesses' => $scenario !== null && $capacity !== null
                && in_array($scenario->status, [ScenarioStatus::Ready, ScenarioStatus::Running], true)
                && $scenario->mode === SimulationMode::Paging,
            'maxSizeKb' => (int) config('memorylab.processes.max_size_kb'),
            'maxPages' => (int) config('memorylab.processes.max_pages_per_process'),
            'maxProcesses' => (int) config('memorylab.processes.max_processes_per_scenario'),
        ]);
    }

    private function reader(): User
    {
        abort_unless(auth()->check(), 403);
        $actor = User::findOrFail(auth()->id());
        Gate::forUser($actor)->authorize(PermissionName::ViewMemory->value);

        return $actor;
    }

    private function selectedScenario(): ?Scenario
    {
        $id = filter_var($this->scenarioId, FILTER_VALIDATE_INT);

        return $id !== false && $id > 0 ? Scenario::with('configuration')->find($id) : null;
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
}
