<?php

namespace App\Livewire;

use App\Enums\PermissionName;
use App\Models\Scenario;
use App\Models\User;
use App\Services\ActiveScenarioService;
use App\Services\MemoryConfigurationService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class MemoryConfiguration extends Component
{
    public $scenarioId = null;

    public $name = 'Escenario de paginación';

    public $ramKb = 16;

    public $pageKb = 1;

    public $secondaryKb = 64;

    public function mount(): void
    {
        $this->reader();
        $scenario = app(ActiveScenarioService::class)->current();
        $this->scenarioId = $scenario?->id;
        $this->loadConfiguration($scenario);
    }

    public function updatedScenarioId(): void
    {
        $actor = $this->reader();
        $id = $this->validatedScenarioId();
        app(ActiveScenarioService::class)->select($actor, $id);
        $this->resetValidation();
        session()->forget('memory-status');
        $this->loadConfiguration($id ? Scenario::with('configuration')->findOrFail($id) : null);
        $this->dispatch('scenario-selected');
    }

    #[Computed]
    public function frameCount(): ?int
    {
        $values = [$this->ramKb, $this->pageKb];
        foreach ($values as $value) {
            if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                return null;
            }
        }

        $ram = (int) $this->ramKb;
        $page = (int) $this->pageKb;
        $max = (int) config('memorylab.memory.max_size_kb');

        if ($ram < 1 || $page < 1 || $ram > $max || $page > $ram || $ram % $page !== 0) {
            return null;
        }

        return intdiv($ram, $page);
    }

    public function save(): void
    {
        $actor = $this->reader();
        Gate::forUser($actor)->authorize(PermissionName::ConfigureMemory->value);
        $id = $this->validatedScenarioId();

        try {
            $scenario = app(MemoryConfigurationService::class)->configure($actor, $id, [
                'name' => $this->name,
                'ram_kb' => $this->ramKb,
                'page_kb' => $this->pageKb,
                'secondary_kb' => $this->secondaryKb,
            ]);
        } catch (ValidationException $exception) {
            $fields = ['ram_kb' => 'ramKb', 'page_kb' => 'pageKb', 'secondary_kb' => 'secondaryKb', 'scenario_id' => 'scenarioId'];
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $errors[$fields[$field] ?? $field] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }

        $this->scenarioId = $scenario->id;
        app(ActiveScenarioService::class)->select($actor, $scenario->id);
        $this->loadConfiguration($scenario->load('configuration'));
        $this->resetValidation();
        session()->flash('memory-status', 'Configuración guardada. Este escenario está seleccionado para el dashboard.');
        $this->dispatch('scenario-selected');
    }

    public function render(): View
    {
        $actor = $this->reader();
        $id = filter_var($this->scenarioId, FILTER_VALIDATE_INT);
        $selected = $id !== false && $id > 0 ? Scenario::with('configuration')->find($id) : null;

        return view('livewire.memory-configuration', [
            'scenarios' => Scenario::orderBy('name')->orderBy('id')->get(['id', 'name']),
            'selectedScenario' => $selected,
            'canConfigure' => Gate::forUser($actor)->allows(PermissionName::ConfigureMemory->value),
            'canCreate' => Gate::forUser($actor)->allows(PermissionName::CreateScenarios->value),
            'maxSizeKb' => (int) config('memorylab.memory.max_size_kb'),
            'maxFrames' => (int) config('memorylab.memory.max_frames'),
        ]);
    }

    private function reader(): User
    {
        abort_unless(auth()->check(), 403);
        $actor = User::findOrFail(auth()->id());
        Gate::forUser($actor)->authorize(PermissionName::ViewMemory->value);

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

    private function loadConfiguration(?Scenario $scenario): void
    {
        $this->name = $scenario?->name ?? 'Escenario de paginación';
        $this->ramKb = $scenario?->configuration?->ram_size_bytes / 1024 ?: 16;
        $this->pageKb = $scenario?->configuration?->page_size_bytes / 1024 ?: 1;
        $this->secondaryKb = $scenario?->configuration ? $scenario->configuration->secondary_storage_bytes / 1024 : 64;
    }
}
