<?php

namespace App\Livewire\Admin;

use App\Enums\RoleName;
use App\Models\User;
use App\Services\UserRoleService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class UserRoles extends Component
{
    use AuthorizesRequests, WithPagination;

    protected $paginationTheme = 'bootstrap';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updateRole(int $userId, string $role): void
    {
        $this->authorize('viewAny', User::class);
        Validator::make(['role' => $role], ['role' => [Rule::enum(RoleName::class)]])->validate();

        app(UserRoleService::class)->changeRole(auth()->user(), User::findOrFail($userId), RoleName::from($role));
        session()->flash('role-status', 'El rol se actualizó correctamente.');
    }

    public function render(): View
    {
        $this->authorize('viewAny', User::class);

        return view('livewire.admin.user-roles', [
            'users' => User::with('roles')->orderBy('id')->paginate(15),
            'roles' => RoleName::cases(),
        ]);
    }
}
