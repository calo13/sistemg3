<div @if ($snapshot !== null) wire:poll.5s.visible @endif>
    <section class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="segment-scenario">Escenario de segmentación</label>
                    <select id="segment-scenario" class="form-select @error('scenarioId') is-invalid @enderror" wire:model.live="scenarioId">
                        <option value="">Seleccionar escenario</option>
                        @foreach ($scenarios as $scenario)<option value="{{ $scenario->id }}">{{ $scenario->name }} · #{{ $scenario->id }}</option>@endforeach
                    </select>
                    @error('scenarioId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="segment-process">Proceso</label>
                    <select id="segment-process" class="form-select @error('processId') is-invalid @enderror" wire:model.live="processId" @disabled($snapshot === null)>
                        <option value="">Seleccionar proceso</option>
                        @foreach ($snapshot['processes'] ?? [] as $process)<option value="{{ $process->id }}">{{ $process->name }} · #{{ $process->id }}</option>@endforeach
                    </select>
                    @error('processId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </section>
    @if ($canConfigure)
        <section class="card mb-4">
            <div class="card-header"><h2 class="h5 mb-0">Nuevo escenario</h2></div>
            <div class="card-body">
                <form wire:submit="configure" class="row g-3 align-items-end">
                    <div class="col-md-6"><label class="form-label" for="segment-scenario-name">Nombre</label><input id="segment-scenario-name" class="form-control @error('scenarioName') is-invalid @enderror" wire:model="scenarioName" maxlength="100">@error('scenarioName')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-3"><label class="form-label" for="segment-ram">RAM total (KB)</label><input id="segment-ram" class="form-control @error('ramKb') is-invalid @enderror" type="number" min="1" max="65536" wire:model="ramKb">@error('ramKb')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-3"><button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Crear escenario</button></div>
                </form>
            </div>
        </section>
    @endif
    @if ($snapshot !== null)
        <div class="row g-4 mb-4">
            @if ($canCreateProcess)
                <div class="col-lg-5">
                    <section class="card h-100">
                        <div class="card-header"><h2 class="h5 mb-0">Crear proceso</h2></div>
                        <div class="card-body">
                            <form wire:submit="createProcess">
                                <label class="form-label" for="segment-process-name">Nombre</label><input id="segment-process-name" class="form-control @error('processName') is-invalid @enderror" wire:model="processName" maxlength="100">@error('processName')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <label class="form-label mt-3" for="segment-process-size">Tamaño del proceso (KB)</label><input id="segment-process-size" class="form-control @error('processSizeKb') is-invalid @enderror" type="number" min="1" max="65536" wire:model="processSizeKb">@error('processSizeKb')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">Crear proceso</button>
                            </form>
                            <p class="small text-body-secondary mt-3 mb-0">La suma de sus segmentos activos no puede superar el tamaño del proceso. Crear el proceso no reserva RAM.</p>
                        </div>
                    </section>
                </div>
            @endif
            @if ($canCreateSegment && $snapshot['selected_process'])
                <div class="col-lg-7">
                    <section class="card h-100">
                        <div class="card-header"><h2 class="h5 mb-0">Agregar segmento</h2></div>
                        <div class="card-body">
                            <form wire:submit="createSegment" class="row g-3">
                                <div class="col-12"><label class="form-label" for="segment-name">Nombre</label><input id="segment-name" class="form-control @error('segmentName') is-invalid @enderror" wire:model="segmentName" maxlength="100">@error('segmentName')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-sm-6"><label class="form-label" for="segment-base">Base (bytes)</label><input id="segment-base" class="form-control @error('segmentBase') is-invalid @enderror" type="number" min="0" wire:model="segmentBase">@error('segmentBase')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-sm-6"><label class="form-label" for="segment-size">Tamaño / límite (bytes)</label><input id="segment-size" class="form-control @error('segmentSize') is-invalid @enderror" type="number" min="1" wire:model="segmentSize">@error('segmentSize')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-12"><button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Agregar segmento</button></div>
                            </form>
                            <p class="small text-body-secondary mt-3 mb-0">El número se asigna automáticamente. El intervalo [base, base + tamaño) debe quedar dentro de la RAM y no solaparse con otro segmento activo.</p>
                        </div>
                    </section>
                </div>
            @endif
        </div>
        <section class="card mb-4" data-segment-table>
            <div class="card-header"><h2 class="h5 mb-1">Tabla de segmentos</h2><p class="small text-body-secondary mb-0 text-break">{{ $snapshot['selected_process']?->name ?? 'Selecciona un proceso' }}</p></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <caption class="visually-hidden">Número, nombre, base, tamaño y estado de segmentos del proceso seleccionado</caption>
                    <thead><tr><th scope="col">Segmento</th><th scope="col">Nombre</th><th scope="col">Base</th><th scope="col">Tamaño / límite</th><th scope="col">Estado</th></tr></thead>
                    <tbody>
                        @forelse ($snapshot['segments'] as $segment)
                            <tr wire:key="segment-{{ $segment->id }}" data-segment-number="{{ $segment->segment_number }}">
                                <th scope="row">{{ $segment->segment_number }}</th><td class="text-break">{{ $segment->name }}</td>
                                <td data-segment-base="{{ $segment->base }}">{{ $segment->base }}</td><td data-segment-size="{{ $segment->size_bytes }}">{{ $segment->size_bytes }} bytes</td>
                                <td><span class="badge bg-label-{{ $segment->status === \App\Enums\SegmentStatus::Active ? 'info' : 'secondary' }}">{{ $segment->status === \App\Enums\SegmentStatus::Active ? 'Activo' : 'Liberado' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ $snapshot['selected_process'] ? 'Este proceso aún no tiene segmentos.' : 'Selecciona un proceso para consultar su tabla.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        <section class="card" data-segment-map>
            <div class="card-header"><h2 class="h5 mb-1">Mapa de RAM simulada</h2><p class="small text-body-secondary mb-0">Escenario completo · Total {{ $snapshot['ram']['total_bytes'] }} bytes · Utilizada <span data-segment-used>{{ $snapshot['ram']['used_bytes'] }}</span> · Disponible {{ $snapshot['ram']['available_bytes'] }}</p></div>
            <div class="card-body">
                <div class="progress mb-3" style="height: 1.25rem" aria-label="Intervalos proporcionales de la RAM">
                    @foreach ($snapshot['blocks'] as $block)
                        <div class="progress-bar bg-{{ $block['state'] === 'FREE' ? 'secondary' : 'info' }}" style="width: {{ $block['size_bytes'] / $snapshot['ram']['total_bytes'] * 100 }}%" aria-label="{{ $block['state'] === 'FREE' ? 'Libre' : $block['segment']->name }}: {{ $block['base'] }} a {{ $block['base'] + $block['size_bytes'] - 1 }} bytes"></div>
                    @endforeach
                </div>
                <div class="row g-2 row-cols-2 row-cols-md-4">
                    @foreach ($snapshot['blocks'] as $block)
                        <div class="col" wire:key="segment-block-{{ $block['base'] }}">
                            <div class="border rounded p-3 h-100 bg-label-{{ $block['state'] === 'FREE' ? 'secondary' : 'info' }}" data-segment-block="{{ $block['base'] }}" data-segment-state="{{ $block['state'] }}">
                                <div class="fw-semibold small text-break">{{ $block['state'] === 'FREE' ? 'Libre' : $block['segment']->name }}</div>
                                @if ($block['segment'])<div class="small text-break">{{ $block['segment']->process->name }}</div>@endif
                                <div class="small text-break">{{ $block['base'] }}–{{ $block['base'] + $block['size_bytes'] - 1 }}</div>
                                <div class="small">{{ $block['size_bytes'] }} bytes</div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="small text-body-secondary mt-3 mb-0">Cada segmento ocupa un intervalo contiguo. Los segmentos de un proceso pueden estar separados por espacios libres o por otros procesos. El límite es su tamaño: el último offset válido es tamaño − 1.</p>
            </div>
        </section>
        <livewire:segment-access :scenario-id="$snapshot['scenario']->id" :process-id="$snapshot['selected_process']?->id" :key="'segment-access-'.$snapshot['scenario']->id.'-'.($snapshot['selected_process']?->id ?? 'none')" />
    @else
        <div class="alert alert-info" role="status">Selecciona un escenario de segmentación. El Administrador puede crear uno desde esta pantalla.</div>
    @endif
</div>
