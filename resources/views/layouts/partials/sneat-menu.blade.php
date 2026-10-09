{{-- Structure adapted from Sneat's layouts/sections/menu/verticalMenu.blade.php. --}}
<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme" aria-label="Menú principal">
    <div class="app-brand memorylab-brand">
        <a href="{{ route('dashboard') }}" class="app-brand-link">
            <img class="memorylab-menu-crest" src="{{ asset('images/umg-logo.png') }}" width="36" height="36" alt="" aria-hidden="true">
            <span class="d-flex flex-column">
                <span class="app-brand-text menu-text fw-bold">{{ config('app.name') }}</span>
                <span class="small text-body-secondary">UMG · {{ $academic['group'] }}</span>
            </span>
        </a>
        <button type="button" class="layout-menu-toggle btn btn-icon ms-auto d-xl-none" aria-label="Cerrar menú" aria-controls="layout-menu" aria-expanded="false">
            <i class="icon-base bx bx-chevron-left" aria-hidden="true"></i>
        </button>
    </div>
    <div class="menu-divider mt-0"></div>
    <ul class="menu-inner py-1">
        <li class="menu-header small"><span class="menu-header-text">Proyecto</span></li>
        <li @class(['menu-item', 'active' => request()->routeIs('dashboard')])>
            <a href="{{ route('dashboard') }}" class="menu-link" @if(request()->routeIs('dashboard')) aria-current="page" @endif>
                <i class="menu-icon icon-base bx bx-home-circle" aria-hidden="true"></i>
                <div>Inicio</div>
            </a>
        </li>
        <li class="menu-item">
            <a href="{{ route('dashboard') }}#equipo" class="menu-link">
                <i class="menu-icon icon-base bx bx-group" aria-hidden="true"></i>
                <div>Integrantes</div>
            </a>
        </li>
        @can('memory.view')
            <li class="menu-header small"><span class="menu-header-text">Simulador</span></li>
            <li @class(['menu-item', 'active' => request()->routeIs('memory.configuration')])>
                <a href="{{ route('memory.configuration') }}" class="menu-link" @if(request()->routeIs('memory.configuration')) aria-current="page" @endif>
                    <i class="menu-icon icon-base bx bx-chip" aria-hidden="true"></i>
                    <div>Configuración de memoria</div>
                </a>
            </li>
            <li @class(['menu-item', 'active' => request()->routeIs('processes.index')])>
                <a href="{{ route('processes.index') }}" class="menu-link" @if(request()->routeIs('processes.index')) aria-current="page" @endif>
                    <i class="menu-icon icon-base bx bx-group" aria-hidden="true"></i>
                    <div>Procesos</div>
                </a>
            </li>
            @can('tables.view')
                @can('simulations.view')
                    <li @class(['menu-item', 'active' => request()->routeIs('paging.index')])>
                        <a href="{{ route('paging.index') }}" class="menu-link" @if(request()->routeIs('paging.index')) aria-current="page" @endif>
                            <i class="menu-icon icon-base bx bx-grid-alt" aria-hidden="true"></i>
                            <div>Paginación</div>
                        </a>
                    </li>
                    <li @class(['menu-item', 'active' => request()->routeIs('demo.index')])>
                        <a href="{{ route('demo.index') }}" class="menu-link" @if(request()->routeIs('demo.index')) aria-current="page" @endif>
                            <i class="menu-icon icon-base bx bx-grid-alt" aria-hidden="true"></i>
                            <div>Demostración</div>
                        </a>
                    </li>
                    @can('results.view')
                        <li @class(['menu-item', 'active' => request()->routeIs('translation.index')])>
                            <a href="{{ route('translation.index') }}" class="menu-link" @if(request()->routeIs('translation.index')) aria-current="page" @endif>
                                <i class="menu-icon icon-base bx bx-transfer-alt" aria-hidden="true"></i>
                                <div>Traducción de direcciones</div>
                            </a>
                        </li>
                        <li @class(['menu-item', 'active' => request()->routeIs('comparison.index')])>
                            <a href="{{ route('comparison.index') }}" class="menu-link" @if(request()->routeIs('comparison.index')) aria-current="page" @endif>
                                <i class="menu-icon icon-base bx bx-transfer-alt" aria-hidden="true"></i>
                                <div>Comparación de asignación</div>
                            </a>
                        </li>
                        <li @class(['menu-item', 'active' => request()->routeIs('stress.index')])>
                            <a href="{{ route('stress.index') }}" class="menu-link" @if(request()->routeIs('stress.index')) aria-current="page" @endif>
                                <i class="menu-icon icon-base bx bx-chip" aria-hidden="true"></i>
                                <div>Demanda de memoria</div>
                            </a>
                        </li>
                        <li @class(['menu-item', 'active' => request()->routeIs('terminal.index')])>
                            <a href="{{ route('terminal.index') }}" class="menu-link" @if(request()->routeIs('terminal.index')) aria-current="page" @endif>
                                <i class="menu-icon icon-base bx bx-data" aria-hidden="true"></i>
                                <div>Terminal educativa</div>
                            </a>
                        </li>
                        <li @class(['menu-item', 'active' => request()->routeIs('presentation.index')])>
                            <a href="{{ route('presentation.index') }}" class="menu-link" @if(request()->routeIs('presentation.index')) aria-current="page" @endif>
                                <i class="menu-icon icon-base bx bx-grid-alt" aria-hidden="true"></i>
                                <div>Modo presentación</div>
                            </a>
                        </li>
                        <li @class(['menu-item', 'active' => request()->routeIs('deliverables.*')])>
                            <a href="{{ route('deliverables.index') }}" class="menu-link" @if(request()->routeIs('deliverables.*')) aria-current="page" @endif>
                                <i class="menu-icon icon-base bx bx-data" aria-hidden="true"></i>
                                <div>Entregables académicos</div>
                            </a>
                        </li>
                    @endcan
                    <li @class(['menu-item', 'active' => request()->routeIs('segmentation.index')])>
                        <a href="{{ route('segmentation.index') }}" class="menu-link" @if(request()->routeIs('segmentation.index')) aria-current="page" @endif>
                            <i class="menu-icon icon-base bx bx-data" aria-hidden="true"></i>
                            <div>Segmentación</div>
                        </a>
                    </li>
                @endcan
            @endcan
        @endcan
        @can('history.view')
            <li @class(['menu-item', 'active' => request()->routeIs('history.index')])>
                <a href="{{ route('history.index') }}" class="menu-link" @if(request()->routeIs('history.index')) aria-current="page" @endif>
                    <i class="menu-icon icon-base bx bx-data" aria-hidden="true"></i>
                    <div>Historial</div>
                </a>
            </li>
        @endcan
        @can('users.manage')
            <li class="menu-header small"><span class="menu-header-text">Administración</span></li>
            <li @class(['menu-item', 'active' => request()->routeIs('admin.users')])>
                <a href="{{ route('admin.users') }}" class="menu-link" @if(request()->routeIs('admin.users')) aria-current="page" @endif>
                    <i class="menu-icon icon-base bx bx-user" aria-hidden="true"></i>
                    <div>Usuarios y roles</div>
                </a>
            </li>
        @endcan
        <li class="menu-header small"><span class="menu-header-text">Cuenta</span></li>
        <li @class(['menu-item', 'active' => request()->routeIs('profile.show')])>
            <a href="{{ route('profile.show') }}" class="menu-link" @if(request()->routeIs('profile.show')) aria-current="page" @endif>
                <i class="menu-icon icon-base bx bx-user" aria-hidden="true"></i>
                <div>Mi perfil</div>
            </a>
        </li>
    </ul>
</aside>
