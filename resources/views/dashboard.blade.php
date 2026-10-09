<x-app-layout>
    <x-slot name="title">Panel de simulación</x-slot>
    <x-slot name="header">
        <h1 class="h3 mb-1">Panel de simulación</h1>
        <p class="text-body-secondary mb-0">Administración de memoria · {{ $academic['course'] }}</p>
    </x-slot>

    {{-- Academic adaptation of Sneat's dashboards-analytics welcome card. --}}
    <section class="card mb-4" aria-labelledby="academic-title">
        <div class="card-body d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-4">
            <div>
                <p class="text-primary fw-medium mb-1">{{ $academic['university'] }}</p>
                <p class="text-body-secondary mb-3">{{ $academic['degree'] }}</p>
                <h2 id="academic-title" class="h4 mb-2">{{ config('app.name') }}</h2>
                <p class="mb-0">Simulador interactivo de administración de memoria.</p>
            </div>
            <div class="d-flex flex-wrap flex-md-column align-items-start align-items-md-end gap-2 flex-shrink-0">
                <span class="badge bg-label-primary">{{ $academic['course'] }}</span>
                <span class="badge bg-label-secondary">{{ $academic['group'] }}</span>
                <a class="btn btn-sm btn-outline-primary" href="#equipo">Ver integrantes</a>
            </div>
        </div>
    </section>

    @livewire('dashboard-stats')

    <div class="row g-4 mt-0">
        <div class="col-lg-7">
            <section id="equipo" class="card h-100 memorylab-team" aria-labelledby="team-title">
                <div class="card-header d-flex align-items-center justify-content-between gap-3">
                    <div>
                        <h2 id="team-title" class="h5 mb-1">Integrantes del proyecto</h2>
                        <p class="mb-0 small text-body-secondary">{{ $academic['degree'] }} · {{ $academic['group'] }}</p>
                    </div>
                    <span class="avatar avatar-sm flex-shrink-0" aria-hidden="true">
                        <span class="avatar-initial rounded bg-label-primary"><i class="icon-base bx bx-group"></i></span>
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0 memorylab-team-table">
                        <caption class="visually-hidden">Integrantes de {{ $academic['group'] }} y sus carnés</caption>
                        <thead><tr><th scope="col">Nombre</th><th scope="col">Carné</th></tr></thead>
                        <tbody>
                            @foreach ($academic['members'] as $member)
                                <tr>
                                    <th scope="row" class="fw-medium">{{ $member['name'] }}</th>
                                    <td class="font-monospace text-nowrap">{{ $member['carnet'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <div class="col-lg-5">
            <section class="card h-100" aria-labelledby="learning-title">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <h2 id="learning-title" class="h5 mb-0">Contenido académico</h2>
                    <span class="badge bg-label-secondary">Módulos</span>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-start gap-3 mb-4">
                        <span class="avatar avatar-sm flex-shrink-0" aria-hidden="true"><span class="avatar-initial rounded bg-label-primary"><i class="icon-base bx bx-grid-alt"></i></span></span>
                        <div>
                            <h3 class="h6 mb-1">Paginación</h3><p class="small mb-0">Tablas de páginas, marcos y fallos de página.</p>
                            @can('memory.view')
                                @can('tables.view')
                                    @can('simulations.view')
                                        <a class="small d-inline-block mt-2" href="{{ route('paging.index') }}">Abrir paginación</a>
                                    @endcan
                                @endcan
                            @endcan
                        </div>
                    </div>
                    <div class="d-flex align-items-start gap-3 mb-4">
                        <span class="avatar avatar-sm flex-shrink-0" aria-hidden="true"><span class="avatar-initial rounded bg-label-info"><i class="icon-base bx bx-data"></i></span></span>
                        <div>
                            <h3 class="h6 mb-1">Segmentación</h3>
                            <p class="small mb-0">Tablas de segmentos, base y límite de cada segmento.</p>
                            @can('memory.view')
                                @can('tables.view')
                                    @can('simulations.view')
                                        <a class="small d-inline-block mt-2" href="{{ route('segmentation.index') }}">Abrir segmentación</a>
                                    @endcan
                                @endcan
                            @endcan
                        </div>
                    </div>
                    <div class="d-flex align-items-start gap-3">
                        <span class="avatar avatar-sm flex-shrink-0" aria-hidden="true"><span class="avatar-initial rounded bg-label-success"><i class="icon-base bx bx-transfer-alt"></i></span></span>
                        <div>
                            <h3 class="h6 mb-1">Comparación de asignación</h3><p class="small mb-0">Asignación contigua y no contigua, fragmentación y conclusiones técnicas.</p>
                            @can('memory.view')
                                @can('tables.view')
                                    @can('simulations.view')
                                        @can('results.view')
                                            <a class="small d-inline-block mt-2" href="{{ route('comparison.index') }}">Comparar asignación</a>
                                        @endcan
                                    @endcan
                                @endcan
                            @endcan
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
