<?php

namespace App\View\Components;

use App\Enums\RoleName;
use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.app', [
            'academic' => config('memorylab.academic'),
            'accountRoleLabels' => auth()->user()?->getRoleNames()
                ->map(fn (string $role) => RoleName::tryFrom($role)?->label() ?? $role)->all() ?? [],
        ]);
    }
}
