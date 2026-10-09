<section class="memorylab-dashboard-stats" @if ($summary !== null) wire:poll.5s.visible @endif aria-labelledby="memory-stats-title">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h2 id="memory-stats-title" class="h5 mb-0">Estado de la memoria</h2>
        <span class="badge text-wrap text-break bg-label-{{ $summary === null ? 'secondary' : 'primary' }}">{{ $summary === null ? 'Sin escenario cargado' : $scenario->name }}</span>
    </div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <p class="small text-body-secondary mb-0">{{ $summary === null ? 'Los indicadores aparecerán cuando se cargue un escenario.' : 'Memoria simulada del escenario seleccionado. 1 KB equivale a 1024 bytes.' }}</p>
        @can ('memory.view')
            <a href="{{ route('memory.configuration') }}" class="btn btn-sm btn-primary">{{ $summary === null ? 'Seleccionar escenario' : 'Ver configuración' }}</a>
        @endcan
    </div>
    <div class="row g-4">
        @foreach ($metrics as $metric)
            <div class="col-6 col-md-3" wire:key="dashboard-stat-{{ $metric['key'] }}">
                <article class="card h-100 memorylab-stat memorylab-stat-{{ $metric['color'] }}" aria-labelledby="stat-label-{{ $metric['key'] }}" data-stat-key="{{ $metric['key'] }}">
                    <div class="card-body">
                        <div class="avatar avatar-sm mb-3" aria-hidden="true">
                            <span class="avatar-initial rounded memorylab-dashboard-icon memorylab-dashboard-icon-{{ $metric['color'] }}"><i class="icon-base bx {{ $metric['icon'] }}"></i></span>
                        </div>
                        <h3 id="stat-label-{{ $metric['key'] }}" class="h6 text-body-secondary memorylab-stat-label mb-2">
                            {{ $metric['label'] }}
                            @if ($summary !== null && $summary[$metric['key']] === null)
                                <small class="d-block">Solo paginación</small>
                            @endif
                        </h3>
                        <p class="h4 mb-0 memorylab-stat-value">
                            @if ($summary === null)
                                <span aria-hidden="true">—</span><span class="visually-hidden">Sin datos</span>
                            @elseif ($summary[$metric['key']] === null)
                                <span class="fs-6 text-body-secondary" data-not-applicable="true">No aplica</span>
                            @else
                                <span data-value="{{ $summary[$metric['key']] }}">{{ number_format($summary[$metric['key']], $summary[$metric['key']] == (int) $summary[$metric['key']] ? 0 : 1, ',', '.') }}</span>
                            @endif
                            @if ($metric['unit'])<small class="fs-6 text-body-secondary">{{ $metric['unit'] }}</small>@endif
                        </p>
                    </div>
                </article>
            </div>
        @endforeach
    </div>
    <div class="card mt-4 memorylab-dashboard-utilization">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h3 class="h6 mb-0">Utilización de RAM</h3>
                <span class="small text-body-secondary">{{ $summary === null ? 'Sin datos' : number_format($summary['utilization'], 1, ',', '.').' %' }}</span>
            </div>
            <div class="progress memorylab-memory-progress" role="progressbar" aria-label="Utilización de RAM" aria-valuemin="0" aria-valuemax="100"
                @if ($summary !== null) aria-valuenow="{{ $summary['utilization'] }}" aria-valuetext="{{ number_format($summary['utilization'], 1, ',', '.') }} % utilizada" @else aria-valuetext="Sin escenario cargado" @endif>
                <div class="progress-bar" style="width: {{ $summary['utilization'] ?? 0 }}%"></div>
            </div>
            <p class="small text-body-secondary mt-3 mb-0">{{ $summary === null ? 'Configura un escenario para consultar la memoria utilizada y disponible.' : 'Los indicadores se actualizan cada cinco segundos. Esta configuración no reserva RAM real del equipo.' }}</p>
        </div>
    </div>
</section>
