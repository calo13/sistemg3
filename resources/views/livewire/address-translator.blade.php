<div>
    <section class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="translation-scenario">Escenario</label>
                    <select id="translation-scenario" class="form-select @error('scenarioId') is-invalid @enderror" wire:model.live="scenarioId">
                        <option value="">Seleccionar escenario</option>
                        @foreach ($scenarios as $scenario)<option value="{{ $scenario->id }}">{{ $scenario->name }} · #{{ $scenario->id }}</option>@endforeach
                    </select>
                    @error('scenarioId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="translation-process">Proceso</label>
                    <select id="translation-process" class="form-select @error('processId') is-invalid @enderror" wire:model.live="processId" @disabled($snapshot === null)>
                        <option value="">Seleccionar proceso</option>
                        @foreach ($snapshot['processes'] ?? [] as $process)<option value="{{ $process->id }}">{{ $process->name }} · #{{ $process->id }}</option>@endforeach
                    </select>
                    @error('processId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </section>
    <div class="row g-4">
        <div class="col-lg-5">
            <section class="card">
                <div class="card-header"><h2 class="h5 mb-0">Dirección lógica</h2></div>
                <div class="card-body">
                    <form wire:submit="translate">
                        <label class="form-label" for="logical-address">Dirección en bytes, desde cero</label>
                        <input id="logical-address" class="form-control @error('logicalAddress') is-invalid @enderror" type="number" min="0" step="1" wire:model="logicalAddress">
                        @error('logicalAddress')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @if ($snapshot['selected_process'] ?? null)<p class="form-text">Rango válido: 0 a {{ $snapshot['selected_process']->size_bytes - 1 }} bytes. Página: {{ $snapshot['configuration']->page_size_bytes }} bytes.</p>@endif
                        <button class="btn btn-primary mt-3" type="submit" wire:loading.attr="disabled" @disabled(! ($snapshot['selected_process'] ?? null))>Traducir dirección</button>
                    </form>
                    <p class="small text-body-secondary mt-3 mb-0">El cálculo consulta la tabla actual y no carga páginas ni registra accesos.</p>
                </div>
            </section>
        </div>
        <div class="col-lg-7">
            <section class="card">
                <div class="card-header"><h2 class="h5 mb-0">Cálculo de dirección</h2></div>
                <div class="card-body" data-translation-result>
                    @if ($result)
                        <ol class="mb-3">
                            <li>Página = floor({{ $result['logical_address'] }} / {{ $result['page_size_bytes'] }}) = <strong data-translation-page>{{ $result['page_number'] }}</strong>.</li>
                            <li>Offset = {{ $result['logical_address'] }} % {{ $result['page_size_bytes'] }} = <strong data-translation-offset>{{ $result['offset'] }}</strong> bytes.</li>
                            <li>Tabla de páginas: P{{ $result['page_number'] }} → <strong data-translation-frame>{{ $result['frame_number'] ?? 'sin marco' }}</strong>.</li>
                        </ol>
                        @if ($result['present'])
                            <div class="alert alert-success mb-0" role="status">Dirección física = {{ $result['frame_number'] }} × {{ $result['page_size_bytes'] }} + {{ $result['offset'] }} = <strong data-translation-physical>{{ $result['physical_address'] }}</strong> bytes.</div>
                        @else
                            <div class="alert alert-warning mb-0" role="status" data-translation-absent>Página no presente: la dirección física no está disponible. Solicita la página desde <a href="{{ route('paging.index') }}">Paginación</a> y vuelve a calcular.</div>
                        @endif
                    @else
                        <p class="text-body-secondary mb-0">Selecciona un proceso e introduce una dirección dentro de su tamaño real.</p>
                    @endif
                </div>
            </section>
        </div>
    </div>
</div>
