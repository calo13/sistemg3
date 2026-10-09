<div>
    @if (session('memory-status'))
        <div class="alert alert-success" role="status">{{ session('memory-status') }}</div>
    @endif
    <div class="row g-4">
        <div class="col-lg-8">
            <section class="card" aria-labelledby="configuration-title">
                <div class="card-header">
                    <h2 id="configuration-title" class="h5 mb-1">Escenario y capacidades</h2>
                    <p class="small text-body-secondary mb-0">Seleccionar un escenario actualiza el contexto de tu sesión.</p>
                </div>
                <div class="card-body">
                    <label class="form-label" for="memory-scenario">Escenario</label>
                    <select id="memory-scenario" class="form-select @error('scenarioId') is-invalid @enderror" wire:model.live="scenarioId" wire:loading.attr="disabled" aria-describedby="scenario-help @error('scenarioId') scenario-error @enderror">
                        <option value="">{{ $canConfigure && $canCreate ? 'Crear un escenario nuevo' : 'Seleccionar un escenario' }}</option>
                        @foreach ($scenarios as $scenario)
                            <option value="{{ $scenario->id }}">{{ $scenario->name }} · #{{ $scenario->id }}</option>
                        @endforeach
                    </select>
                    @error('scenarioId')<div id="scenario-error" class="invalid-feedback">{{ $message }}</div>@enderror
                    <p id="scenario-help" class="form-text mb-4">Los escenarios son compartidos; la selección del dashboard corresponde a esta sesión.</p>

                    @if (! $canConfigure)
                        <div class="alert alert-info" role="status">Consulta de memoria. Solo un administrador puede guardar configuraciones.</div>
                    @endif

                    @if (! $selectedScenario && ! ($canConfigure && $canCreate))
                        <p class="text-body-secondary mb-0">{{ $scenarios->isEmpty() ? 'Todavía no hay escenarios. Solicita al administrador que configure uno.' : 'Selecciona un escenario para consultar su configuración.' }}</p>
                    @elseif ($selectedScenario && $selectedScenario->mode !== \App\Enums\SimulationMode::Paging)
                        <div class="alert alert-warning mb-0" role="status">La configuración de este módulo corresponde a paginación. Selecciona un escenario de paginación.</div>
                    @else
                        @if ($selectedScenario && ! $selectedScenario->configuration && ! $canConfigure)
                            <p class="text-body-secondary mb-0">Este escenario todavía no tiene una configuración guardada.</p>
                        @else
                            <form id="memory-configuration-form" wire:submit="save" novalidate>
                                <fieldset @disabled(! $canConfigure)>
                                    <legend class="visually-hidden">Parámetros de memoria</legend>
                                    <div class="mb-4">
                                        <label class="form-label" for="memory-name">Nombre del escenario</label>
                                        <input id="memory-name" type="text" class="form-control @error('name') is-invalid @enderror" wire:model="name" maxlength="100" @readonly($selectedScenario !== null) @error('name') aria-describedby="name-error" aria-invalid="true" @enderror>
                                        @error('name')<div id="name-error" class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-sm-6">
                                            <label class="form-label" for="memory-ram">RAM total (KB)</label>
                                            <input id="memory-ram" type="number" class="form-control @error('ramKb') is-invalid @enderror" wire:model.live="ramKb" min="1" max="{{ $maxSizeKb }}" step="1" @error('ramKb') aria-describedby="ram-error" aria-invalid="true" @enderror>
                                            @error('ramKb')<div id="ram-error" class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                        <div class="col-sm-6">
                                            <label class="form-label" for="memory-page">Tamaño de página (KB)</label>
                                            <input id="memory-page" type="number" class="form-control @error('pageKb') is-invalid @enderror" wire:model.live="pageKb" min="1" max="{{ $maxSizeKb }}" step="1" @error('pageKb') aria-describedby="page-error" aria-invalid="true" @enderror>
                                            @error('pageKb')<div id="page-error" class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                        <div class="col-sm-6">
                                            <label class="form-label" for="memory-frames">Número de marcos</label>
                                            <input id="memory-frames" class="form-control" type="text" value="{{ $this->frameCount ?? '—' }}" readonly aria-describedby="frames-help">
                                            <p id="frames-help" class="form-text mb-0">RAM ÷ tamaño de página. Máximo {{ $maxFrames }} marcos.</p>
                                        </div>
                                        <div class="col-sm-6">
                                            <label class="form-label" for="memory-secondary">Almacenamiento secundario (KB)</label>
                                            <input id="memory-secondary" type="number" class="form-control @error('secondaryKb') is-invalid @enderror" wire:model="secondaryKb" min="0" max="{{ $maxSizeKb }}" step="1" @error('secondaryKb') aria-describedby="secondary-error" aria-invalid="true" @enderror>
                                            @error('secondaryKb')<div id="secondary-error" class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>
                                </fieldset>
                                @if ($canConfigure)
                                    <p class="small text-body-secondary mt-4">Puedes cambiar las capacidades antes de agregar procesos. Un escenario en ejecución o completado conserva su configuración.</p>
                                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Guardar configuración</button>
                                    <span class="small text-body-secondary ms-2" wire:loading wire:target="save">Guardando…</span>
                                @endif
                            </form>
                        @endif
                    @endif
                </div>
            </section>
        </div>
        <div class="col-lg-4">
            <aside class="card" aria-labelledby="memory-example-title">
                <div class="card-body">
                    <span class="avatar avatar-sm mb-3" aria-hidden="true"><span class="avatar-initial rounded bg-label-primary"><i class="icon-base bx bx-chip"></i></span></span>
                    <h2 id="memory-example-title" class="h5">Configuración recomendada</h2>
                    <dl class="row mb-4">
                        <dt class="col-7 fw-normal">RAM total</dt><dd class="col-5 text-end">16 KB</dd>
                        <dt class="col-7 fw-normal">Página</dt><dd class="col-5 text-end">1 KB</dd>
                        <dt class="col-7 fw-normal">Marcos</dt><dd class="col-5 text-end">16</dd>
                    </dl>
                    <p class="small">1 KB equivale a 1024 bytes. La RAM debe ser un múltiplo exacto del tamaño de página.</p>
                    <p class="small text-body-secondary">Las capacidades son simuladas. No se reserva la RAM indicada ni se modifica el disco físico del equipo.</p>
                    <p class="small text-body-secondary">Capacidad máxima por campo: {{ number_format($maxSizeKb, 0, ',', '.') }} KB. El almacenamiento secundario puede ser cero.</p>
                    <a class="btn btn-outline-primary w-100" href="{{ route('dashboard') }}">Ver dashboard</a>
                </div>
            </aside>
        </div>
    </div>
</div>
