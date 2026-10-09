<div @if($snapshot) wire:poll.5s.visible @endif>
    <section class="card mb-4">
        <div class="card-body">
            <label class="form-label" for="stress-scenario">Escenario de paginación</label>
            <select id="stress-scenario" class="form-select @error('scenarioId') is-invalid @enderror" wire:model.live="scenarioId">
                <option value="">Seleccionar escenario</option>
                @foreach ($scenarios as $scenario)<option value="{{ $scenario->id }}">{{ $scenario->name }} · #{{ $scenario->id }}</option>@endforeach
            </select>
            @error('scenarioId')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <p class="small text-body-secondary mb-0 mt-3">La ejecución agrega procesos al escenario seleccionado y solicita sus páginas en orden. Primero reserva todo el lote en almacenamiento secundario; si no cabe, conserva el estado anterior.</p>
        </div>
    </section>
    @if ($snapshot)
        <div class="row g-3 mb-4" data-stress-current>
            @foreach (['RAM utilizada' => $snapshot['ram']['used_bytes'].' B', 'RAM disponible' => $snapshot['ram']['available_bytes'].' B', 'Marcos ocupados' => $snapshot['ram']['frames_used'].' / '.$snapshot['ram']['frames_total'], 'Procesos activos' => $snapshot['processes']->where('status', '!=', \App\Enums\ProcessStatus::Terminated)->count(), 'Page Faults acumulados' => $pageFaultCount, 'Secundario utilizado' => $snapshot['secondary']['used_bytes'].' B'] as $label => $value)
                <div class="col-sm-6 col-xl-4"><div class="card h-100"><div class="card-body"><p class="small mb-1">{{ $label }}</p><strong>{{ $value }}</strong></div></div></div>
            @endforeach
        </div>
    @endif
    @if ($canRun)
        <section class="card mb-4">
            <div class="card-body">
                <form wire:submit="run" class="row g-3 align-items-end">
                    <div class="col-sm-6 col-lg-4">
                        <label class="form-label" for="stress-count">Procesos ficticios</label>
                        <input id="stress-count" class="form-control @error('processCount') is-invalid @enderror" type="number" min="1" max="8" step="1" wire:model="processCount">
                        @error('processCount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-sm-6 col-lg-4">
                        <label class="form-label" for="stress-size">Tamaño de cada proceso, KB</label>
                        <input id="stress-size" class="form-control @error('sizeKb') is-invalid @enderror" type="number" min="1" max="64" step="1" wire:model="sizeKb">
                        @error('sizeKb')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-lg-4"><button class="btn btn-primary" type="submit" wire:loading.attr="disabled" @disabled($snapshot === null)>Simular alta demanda</button></div>
                </form>
                <p class="form-text mb-0">Hasta 8 procesos y 64 accesos por ejecución. La RAM mostrada pertenece al modelo educativo.</p>
            </div>
        </section>
    @else
        <div class="alert alert-info">Tu rol permite consultar la memoria. Un operador o administrador puede ejecutar la demanda.</div>
    @endif
    @if ($result)
        <section class="card" data-stress-result>
            <div class="card-header"><h2 class="h5 mb-1">Resultado de la ejecución</h2><p class="small mb-0">{{ $result['requested_pages'] }} solicitudes · <span data-stress-evictions>{{ $result['evictions'] }}</span> reemplazos FIFO</p></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Indicador</th><th>Antes</th><th>Después</th></tr></thead>
                    <tbody>
                        <tr><th>RAM utilizada (bytes)</th><td>{{ $result['before']['ram']['used_bytes'] }}</td><td data-stress-used>{{ $result['after']['ram']['used_bytes'] }}</td></tr>
                        <tr><th>RAM disponible (bytes)</th><td>{{ $result['before']['ram']['available_bytes'] }}</td><td>{{ $result['after']['ram']['available_bytes'] }}</td></tr>
                        <tr><th>Marcos ocupados</th><td>{{ $result['before']['ram']['frames_used'] }}</td><td>{{ $result['after']['ram']['frames_used'] }}</td></tr>
                        <tr><th>Procesos activos</th><td>{{ $result['before']['active_process_count'] }}</td><td>{{ $result['after']['active_process_count'] }}</td></tr>
                        <tr><th>Page Faults acumulados</th><td>{{ $result['before']['page_fault_count'] }}</td><td data-stress-faults>{{ $result['after']['page_fault_count'] }}</td></tr>
                        <tr><th>Almacenamiento secundario usado (bytes)</th><td>{{ $result['before']['secondary']['used_bytes'] }}</td><td>{{ $result['after']['secondary']['used_bytes'] }}</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="card-body"><p class="mb-2 fw-medium">Procesos agregados</p><ul class="mb-0">@foreach ($result['created_processes'] as $process)<li>{{ $process['name'] }} · {{ $process['size_kb'] }} KB</li>@endforeach</ul></div>
            <div class="card-header"><h3 class="h6 mb-0">Evolución de la ocupación</h3></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Acceso</th><th>Proceso / página</th><th>Resultado</th><th>RAM usada</th></tr></thead>
                    <tbody>
                        @foreach ($result['progress'] as $step)
                            <tr><td>{{ $step['request_index'] }}</td><td class="text-break">{{ $step['process_name'] }} / P{{ $step['page_number'] }}</td><td>{{ $step['outcome'] }}</td><td>{{ $step['ram_used_bytes'] }} B</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
