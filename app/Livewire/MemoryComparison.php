<?php

namespace App\Livewire;

use App\Enums\PermissionName;
use App\Models\User;
use App\Services\MemoryComparisonService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class MemoryComparison extends Component
{
    public $requestedBytes = 7168;

    #[Locked]
    public ?array $result = null;

    public function mount(): void
    {
        $this->reader();
    }

    public function compare(): void
    {
        $this->reader();
        $this->reset('result');
        $this->validate(['requestedBytes' => ['required', 'integer', 'min:1', 'max:16384']]);
        try {
            $this->result = app(MemoryComparisonService::class)->compare((int) $this->requestedBytes);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['requestedBytes' => $exception->errors()['requested_bytes']]);
        }
    }

    public function render(): View
    {
        $this->reader();

        return view('livewire.memory-comparison', [
            'comparison' => $this->result ?? app(MemoryComparisonService::class)->compare(7168),
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
}
