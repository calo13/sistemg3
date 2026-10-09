<?php

namespace App\Livewire;

use App\Enums\PermissionName;
use App\Enums\SimulationEventType;
use App\Models\Process;
use App\Models\Scenario;
use App\Models\User;
use App\Services\ActiveScenarioService;
use App\Services\SimulationHistoryService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class SimulationHistory extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $scenarioId = null;

    public $processId = null;

    public $userId = null;

    public $eventType = null;

    public $fromDate = null;

    public $toDate = null;

    public function mount(): void
    {
        $this->reader();
        $this->scenarioId = app(ActiveScenarioService::class)->current()?->id;
    }

    public function updatedScenarioId(): void
    {
        $this->reset('processId');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['scenarioId', 'processId', 'userId', 'eventType', 'fromDate', 'toDate'], true)) {
            $this->resetPage('historyPage');
            $this->resetValidation();
        }
    }

    public function clearFilters(): void
    {
        $this->reader();
        $this->reset('scenarioId', 'processId', 'userId', 'eventType', 'fromDate', 'toDate');
        $this->resetPage('historyPage');
        $this->resetValidation();
    }

    public function render(): View
    {
        $actor = $this->reader();
        if (! is_array($this->paginators)) {
            $this->paginators = [];
        }
        $page = filter_var($this->getPage('historyPage'), FILTER_VALIDATE_INT);
        try {
            $events = app(SimulationHistoryService::class)->search($actor, [
                'scenario_id' => $this->scenarioId, 'process_id' => $this->processId, 'user_id' => $this->userId,
                'type' => $this->eventType, 'from_date' => $this->fromDate, 'to_date' => $this->toDate,
            ], $page === false ? 1 : max(1, $page));
        } catch (ValidationException $exception) {
            $fields = ['scenario_id' => 'scenarioId', 'process_id' => 'processId', 'user_id' => 'userId', 'type' => 'eventType', 'from_date' => 'fromDate', 'to_date' => 'toDate'];
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($fields[$field] ?? $field, $messages[0]);
            }
            $events = new LengthAwarePaginator([], 0, 20, 1, ['pageName' => 'historyPage', 'path' => route('history.index')]);
        }
        $this->paginators['historyPage'] = $events->currentPage();
        $scenarioId = filter_var($this->scenarioId, FILTER_VALIDATE_INT);

        return view('livewire.simulation-history', [
            'events' => $events,
            'scenarios' => Scenario::orderBy('name')->orderBy('id')->get(['id', 'name']),
            'processes' => $scenarioId !== false && $scenarioId > 0 ? Process::where('scenario_id', $scenarioId)->orderBy('name')->orderBy('id')->get(['id', 'name']) : collect(),
            'users' => User::orderBy('name')->orderBy('id')->get(['id', 'name']),
            'eventTypes' => SimulationEventType::cases(),
        ]);
    }

    private function reader(): User
    {
        abort_unless(auth()->check(), 403);
        $actor = User::findOrFail(auth()->id());
        Gate::forUser($actor)->authorize(PermissionName::ViewHistory->value);

        return $actor;
    }
}
