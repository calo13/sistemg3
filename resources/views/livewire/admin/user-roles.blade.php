<div>
    @session('role-status')
        <div class="alert alert-success" role="status">{{ $value }}</div>
    @endsession
    <x-validation-errors class="mb-4" />
    <div class="card">
        <div class="card-header">
            <h2 class="h5 mb-2">Acceso a MemoryLab</h2>
            <p class="mb-0">Los registros nuevos reciben el rol Observador. Tu propio rol se conserva desde esta pantalla.</p>
        </div>
        <div class="table-responsive">
            <table class="table memorylab-user-table mb-0">
                <thead><tr><th>Usuario</th><th>Rol actual</th><th>Asignar rol</th></tr></thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr wire:key="user-role-{{ $user->id }}">
                            <td><span class="fw-medium">{{ $user->name }}</span><div class="small text-break">{{ $user->email }}</div></td>
                            <td>
                                @forelse ($user->roles as $role)
                                    <span class="badge bg-label-primary">{{ \App\Enums\RoleName::tryFrom($role->name)?->label() ?? $role->name }}</span>
                                @empty
                                    <span class="text-body-secondary">Sin rol</span>
                                @endforelse
                            </td>
                            <td>
                                @can('assignRole', $user)
                                    <select class="form-select" aria-label="Rol de {{ $user->name }}"
                                        wire:change="updateRole({{ $user->id }}, $event.target.value)"
                                        wire:loading.attr="disabled" wire:target="updateRole">
                                        @if ($user->roles->count() !== 1)<option value="" selected disabled>Selecciona un rol</option>@endif
                                        @foreach ($roles as $option)
                                            <option value="{{ $option->value }}" @selected($user->hasRole($option->value))>{{ $option->label() }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <span class="small text-body-secondary">Tu cuenta</span>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center py-4">No hay usuarios registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $users->links() }}</div>
    </div>
</div>
