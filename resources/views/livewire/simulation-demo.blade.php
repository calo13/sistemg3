<div>
    <section class="card mb-4">
        <div class="card-body">
            <h2 class="h5">Un recorrido listo para explicar</h2>
            <p>La demostración prepara dos escenarios: paginación con RAM de 16 KB, páginas de 1 KB y 16 marcos; y segmentación con código, datos, pila y heap separados.</p>
            @if ($canStart)
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" wire:click="startDemo" wire:loading.attr="disabled">Iniciar demostración</button>
                    @if ($canReset && $demo)<button class="btn btn-outline-primary" wire:click="restartDemo" wire:loading.attr="disabled">Reiniciar demostración</button>@endif
                </div>
                <p class="small text-body-secondary mt-3 mb-0">Iniciar crea un par nuevo. Reiniciar finaliza el par anterior, conserva su historial y prepara otro par con los valores iniciales.</p>
            @else
                <div class="alert alert-info mb-0">El administrador prepara las demostraciones. Puedes abrir las existentes; el operador puede ejecutar sus accesos.</div>
            @endif
            @error('demo')<div class="alert alert-danger mt-3 mb-0">{{ $message }}</div>@enderror
        </div>
    </section>
    @if ($demo)
        <section class="card mb-4" data-demo-ready data-demo-paging="{{ $demo['paging_scenario_id'] }}" data-demo-segmentation="{{ $demo['segmentation_scenario_id'] }}">
            <div class="card-header"><h2 class="h5 mb-0">Demostración preparada</h2></div>
            <div class="card-body row g-4">
                <div class="col-md-6"><h3 class="h6">Paginación · Chrome</h3><ol class="mb-3"><li>Selecciona Chrome y solicita P0: Page Hit.</li><li>Solicita P3: Page Fault y carga desde DISCO.</li><li>Repite P3: ahora es Page Hit.</li></ol><button class="btn btn-outline-primary" wire:click="selectScenario({{ $demo['paging_scenario_id'] }})">Abrir paginación</button></div>
                <div class="col-md-6"><h3 class="h6">Segmentación · Editor</h3><ol class="mb-3"><li>Selecciona Editor y Código (S0).</li><li>Offset 100: dirección física 1100.</li><li>Offset 1200: Segmentation Fault.</li></ol><button class="btn btn-outline-primary" wire:click="selectScenario({{ $demo['segmentation_scenario_id'] }})">Abrir segmentación</button></div>
            </div>
        </section>
    @endif
    <section class="card mb-4">
        <div class="card-header"><h2 class="h5 mb-0">Demostraciones guardadas</h2></div>
        <div class="card-body">
            <div class="list-group">
                @forelse ($scenarios->where('is_demo', true)->where('status', '!=', \App\Enums\ScenarioStatus::Completed)->where('active_processes_count', '>', 0) as $scenario)
                    <button class="list-group-item list-group-item-action text-break" wire:click="selectScenario({{ $scenario->id }})">{{ $scenario->name }} · {{ $scenario->mode->value }} · #{{ $scenario->id }}</button>
                @empty
                    <p class="text-body-secondary mb-0">Aún no hay demostraciones preparadas.</p>
                @endforelse
            </div>
        </div>
    </section>
    @if ($canReset)
        <section class="card">
            <div class="card-header"><h2 class="h5 mb-0">Liberar memoria de un escenario</h2></div>
            <div class="card-body">
                <p>Finaliza sus procesos, elimina las asignaciones de páginas y libera los segmentos. Conserva configuración, marcos, procesos e historial.</p>
                <form wire:submit="resetMemory">
                    <label class="form-label" for="reset-scenario">Escenario que se reiniciará</label>
                    <select id="reset-scenario" class="form-select @error('resetScenarioId') is-invalid @enderror" wire:model.live="resetScenarioId">
                        <option value="">Seleccionar escenario</option>
                        @foreach ($scenarios as $scenario)<option value="{{ $scenario->id }}">{{ $scenario->name }} · #{{ $scenario->id }}</option>@endforeach
                    </select>
                    @error('resetScenarioId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <button class="btn btn-outline-danger mt-3" type="submit" wire:loading.attr="disabled">Finalizar procesos y liberar memoria</button>
                </form>
                @if ($resetResult)<div class="alert alert-success mt-3 mb-0" role="status" data-memory-reset>Escenario #{{ $resetResult['scenario_id'] }}: {{ $resetResult['terminated_processes'] }} procesos finalizados, {{ $resetResult['released_pages'] }} páginas retiradas y {{ $resetResult['released_segments'] }} segmentos liberados. Historial conservado.</div>@endif
            </div>
        </section>
    @endif
</div>
