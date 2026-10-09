<?php

namespace App\Livewire;

use App\Enums\PermissionName;
use App\Models\User;
use App\Services\ActiveScenarioService;
use App\Services\MemoryStatisticsService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class DashboardStats extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
    }

    #[On('scenario-selected')]
    #[On('process-created')]
    #[On('memory-updated')]
    public function refreshSelectedScenario(): void
    {
        // The selection lives in the session; rendering reads it again.
    }

    public function render(): View
    {
        $actor = auth()->id() === null ? null : User::find(auth()->id());
        $canViewMemory = $actor !== null && Gate::forUser($actor)->allows(PermissionName::ViewMemory->value);
        $scenario = $canViewMemory ? app(ActiveScenarioService::class)->current() : null;
        $summary = $scenario === null ? null : app(MemoryStatisticsService::class)->forScenario($scenario);

        return view('livewire.dashboard-stats', [
            'scenario' => $scenario,
            'summary' => $summary,
            'metrics' => [
                ['key' => 'ram-total', 'label' => 'RAM total', 'unit' => 'KB', 'icon' => 'bx-chip', 'color' => 'primary'],
                ['key' => 'ram-used', 'label' => 'RAM utilizada', 'unit' => 'KB', 'icon' => 'bx-chip', 'color' => 'primary'],
                ['key' => 'ram-available', 'label' => 'RAM disponible', 'unit' => 'KB', 'icon' => 'bx-chip', 'color' => 'primary'],
                ['key' => 'frames-total', 'label' => 'Marcos totales', 'unit' => null, 'icon' => 'bx-grid-alt', 'color' => 'info'],
                ['key' => 'frames-used', 'label' => 'Marcos ocupados', 'unit' => null, 'icon' => 'bx-grid-alt', 'color' => 'info'],
                ['key' => 'frames-free', 'label' => 'Marcos libres', 'unit' => null, 'icon' => 'bx-grid-alt', 'color' => 'info'],
                ['key' => 'processes-active', 'label' => 'Procesos activos', 'unit' => null, 'icon' => 'bx-user', 'color' => 'success'],
                ['key' => 'page-faults', 'label' => 'Page Faults', 'unit' => null, 'icon' => 'bx-error-circle', 'color' => 'danger'],
            ],
        ]);
    }
}
