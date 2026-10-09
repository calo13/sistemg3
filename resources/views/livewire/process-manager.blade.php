<div>
    @if (session('process-status'))
        <div class="alert alert-success text-break" role="status">{{ session('process-status') }}</div>
    @endif
    <section class="card mb-4" aria-labelledby="process-scenario-title">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 id="process-scenario-title" class="h5 mb-0">Escenario seleccionado</h2>
                <a class="btn btn-sm btn-outline-primary" href="{{ route('memory.configuration') }}">{{ $canConfigure ? 'Configurar memoria' : 'Consultar memoria' }}</a>
            </div>
            <label class="form-label" for="process-scenario">Escenario</label>
            <select id="process-scenario" class="form-select @error('scenarioId') is-invalid @enderror" wire:model.live="scenarioId" wire:loading.attr="disabled" aria-describedby="process-scenario-help @error('scenarioId') process-scenario-error @enderror">
                <option value="">Seleccionar un escenario</option>
                @foreach ($scenarios as $option)
                    <option value="{{ $option->id }}">{{ $option->name }} · #{{ $option->id }}</option>
                @endforeach
            </select>
            @error('scenarioId')<div id="process-scenario-error" class="invalid-feedback">{{ $message }}</div>@enderror
            <p id="process-scenario-help" class="form-text mb-0">La selección también se utiliza en tu dashboard. 1 KB equivale a 1024 bytes.</p>
        </div>
    </section>

    @if (! $scenario)
        <div class="alert alert-info" role="status">Selecciona un escenario para consultar sus procesos. Si todavía no hay escenarios, un administrador debe configurar la memoria.</div>
    @elseif ($capacity === null)
        <div class="alert alert-warning" role="status">Este módulo requiere un escenario de paginación con memoria configurada. Consulta su configuración antes de crear procesos.</div>
    @else
        <div class="row g-4 mb-4" aria-label="Capacidades del escenario">
            <div class="col-md-4"><div class="card h-100"><div class="card-body"><p class="text-body-secondary mb-1">Tamaño de página</p><p class="h4 mb-0" data-process-capacity="page-size">{{ number_format($capacity['page_size_bytes'] / 1024, 0, ',', '.') }} <small class="fs-6">KB</small></p></div></div></div>
            <div class="col-md-4"><div class="card h-100"><div class="card-body"><p class="text-body-secondary mb-1">Secundaria utilizada</p><p class="h4 mb-0" data-process-capacity="secondary-used">{{ number_format($capacity['secondary_used_bytes'] / 1024, 0, ',', '.') }} <small class="fs-6">KB</small></p></div></div></div>
            <div class="col-md-4"><div class="card h-100"><div class="card-body"><p class="text-body-secondary mb-1">Secundaria disponible</p><p class="h4 mb-0" data-process-capacity="secondary-available">{{ number_format($capacity['secondary_available_bytes'] / 1024, 0, ',', '.') }} <small class="fs-6">KB</small></p></div></div></div>
        </div>
    @endif

    @if ($scenario)
        <div class="row g-4">
            @if ($canCreate)
                <div class="col-xl-4">
                    <section class="card" aria-labelledby="process-create-title">
                        <div class="card-header"><h2 id="process-create-title" class="h5 mb-0">Crear proceso</h2></div>
                        <div class="card-body">
                            @if (! $acceptsProcesses)
                                <div class="alert alert-warning" role="status">Solo puedes crear procesos en un escenario de paginación configurado, listo o en ejecución.</div>
                            @endif
                            <form id="process-create-form" wire:submit="create" novalidate>
                                <fieldset @disabled(! $acceptsProcesses)>
                                    <legend class="visually-hidden">Datos del proceso</legend>
                                    <div class="mb-4">
                                        <label class="form-label" for="process-name">Nombre</label>
                                        <input id="process-name" type="text" class="form-control @error('name') is-invalid @enderror" wire:model="name" maxlength="100" placeholder="Ejemplo: Chrome" @error('name') aria-describedby="process-name-error" aria-invalid="true" @enderror>
                                        @error('name')<div id="process-name-error" class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label" for="process-size">Tamaño (KB)</label>
                                        <input id="process-size" type="number" class="form-control @error('sizeKb') is-invalid @enderror" wire:model.live="sizeKb" min="1" max="{{ $maxSizeKb }}" step="1" aria-describedby="process-size-help @error('sizeKb') process-size-error @enderror">
                                        @error('sizeKb')<div id="process-size-error" class="invalid-feedback">{{ $message }}</div>@enderror
                                        <p id="process-size-help" class="form-text mb-0">Hasta {{ number_format($maxSizeKb, 0, ',', '.') }} KB y {{ $maxPages }} páginas por proceso.</p>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label" for="process-pages">Páginas requeridas</label>
                                        <input id="process-pages" type="text" class="form-control" value="{{ $this->pageCount ?? '—' }}" readonly aria-describedby="process-pages-help">
                                        <p id="process-pages-help" class="form-text mb-0">Tamaño ÷ página, redondeado hacia arriba.</p>
                                    </div>
                                    <p class="small text-body-secondary">El proceso inicia en READY. Sus páginas se preparan en almacenamiento secundario simulado y todavía no ocupan RAM.</p>
                                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Crear proceso</button>
                                    <span id="process-creating-status" class="small text-body-secondary mt-2" wire:loading.block wire:target="create">Creando…</span>
                                </fieldset>
                            </form>
                        </div>
                    </section>
                </div>
            @endif
            <div class="{{ $canCreate ? 'col-xl-8' : 'col-12' }}">
                <section class="card" aria-labelledby="process-list-title" @if ($capacity !== null) wire:poll.5s.visible @endif>
                    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div><h2 id="process-list-title" class="h5 mb-1">Procesos del escenario</h2><p class="small text-body-secondary mb-0">{{ $processes->total() }} de {{ $maxProcesses }} procesos totales permitidos.</p></div>
                        <span class="badge bg-label-primary">{{ $scenario->status->value }}</span>
                    </div>
                    @if (! $canCreate)
                        <p class="small text-body-secondary px-4 mb-3">Puedes consultar los procesos. Administrador y Operador pueden crearlos.</p>
                    @endif
                    <div class="table-responsive">
                        <table class="table mb-0 memorylab-process-table">
                            <caption class="visually-hidden">Nombre, tamaño, páginas, estado y creación de los procesos del escenario seleccionado</caption>
                            <thead><tr><th scope="col">ID</th><th scope="col">Nombre</th><th scope="col">Tamaño</th><th scope="col">Páginas</th><th scope="col">Estado</th><th scope="col">Creado (UTC)</th></tr></thead>
                            <tbody>
                                @forelse ($processes as $process)
                                    <tr wire:key="process-{{ $process->id }}" data-process-id="{{ $process->id }}">
                                        <td class="font-monospace">{{ $process->id }}</td>
                                        <th scope="row" class="fw-medium text-break">{{ $process->name }}</th>
                                        <td class="text-nowrap">{{ number_format($process->size_bytes / 1024, $process->size_bytes % 1024 === 0 ? 0 : 1, ',', '.') }} KB</td>
                                        <td>{{ $process->pages_count }}</td>
                                        <td><span class="badge bg-label-{{ $process->status->color() }}" title="{{ $process->status->label() }}">{{ $process->status->value }}</span></td>
                                        <td class="small text-nowrap">{{ $process->created_at->format('d/m/Y H:i:s') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-body-secondary py-5">Todavía no hay procesos en este escenario.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="card-body">
                        @if ($processes->hasPages()){{ $processes->links() }}@endif
                        <p class="small text-body-secondary mb-0">READY: listo · RUNNING: en ejecución · WAITING: en espera · TERMINATED: finalizado. Los estados cambian mediante las operaciones del simulador.</p>
                    </div>
                </section>
            </div>
        </div>
    @endif
</div>
