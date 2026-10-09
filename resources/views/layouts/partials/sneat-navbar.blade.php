{{-- Structure adapted from Sneat's navbar and navbar-partial Blade files. --}}
<nav id="layout-navbar" class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme" aria-label="Cuenta">
    <button type="button" class="layout-menu-toggle btn btn-icon me-3 d-xl-none" aria-label="Abrir menú" aria-controls="layout-menu" aria-expanded="false">
        <i class="icon-base bx bx-menu" aria-hidden="true"></i>
    </button>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-label-primary">UMG</span>
        <span class="fw-medium d-none d-sm-inline">{{ $academic['course'] }}</span>
    </div>
    <div class="navbar-nav ms-auto">
        <div class="nav-item dropdown">
            <button type="button" class="btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Opciones de cuenta">
                <span class="d-inline-block text-truncate align-middle memorylab-profile-name">{{ auth()->user()->name }}</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                @if ($accountRoleLabels)
                    <li class="dropdown-header small">{{ implode(' · ', $accountRoleLabels) }}</li>
                    <li><hr class="dropdown-divider"></li>
                @endif
                <li><a class="dropdown-item" href="{{ route('profile.show') }}">Mi perfil</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item">Cerrar sesión</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</nav>
