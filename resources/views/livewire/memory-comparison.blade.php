<div>
    <section class="card mb-4">
        <div class="card-body">
            <span class="badge bg-label-info mb-2">Ejemplo académico calculado</span>
            <h2 class="h5">RAM de 16 KB: hay 9 KB libres en dos huecos</h2>
            <p>Los procesos A y B ocupan 7 KB. Quedan huecos de 3 KB y 6 KB. Un proceso de 7 KB cabe en el espacio libre total, pero necesita distribuirse para aprovecharlo.</p>
            <form wire:submit="compare" class="row g-3 align-items-end">
                <div class="col-sm-7 col-lg-5">
                    <label class="form-label" for="comparison-bytes">Tamaño solicitado en bytes</label>
                    <input id="comparison-bytes" type="number" min="1" max="16384" step="1" wire:model="requestedBytes" class="form-control @error('requestedBytes') is-invalid @enderror">
                    @error('requestedBytes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-auto"><button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Comparar asignación</button></div>
            </form>
            <p class="small text-body-secondary mb-0 mt-3">1 KB = 1024 bytes. Estos cálculos no modifican los escenarios guardados.</p>
        </div>
    </section>
    <p class="fw-medium" role="status">Solicitud calculada: <span data-comparison-request>{{ $comparison['requested_bytes'] }}</span> bytes · Mayor hueco inicial: 6144 bytes.</p>
    <div class="row g-4 mb-4">
        @foreach (['contiguous' => 'Contigua', 'paging' => 'Paginación', 'segmentation' => 'Segmentación'] as $key => $label)
            @php($mode = $comparison['modes'][$key])
            <div class="col-lg-4">
                <section class="card h-100" data-comparison-mode="{{ $key }}" data-comparison-accepted="{{ $mode['accepted'] ? 'true' : 'false' }}">
                    <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
                        <h2 class="h5 mb-0">{{ $label }}</h2>
                        <span class="badge {{ $mode['accepted'] ? 'bg-label-success' : 'bg-label-danger' }}">{{ $mode['accepted'] ? 'Aceptada' : 'Rechazada' }}</span>
                    </div>
                    <div class="card-body">
                        <div class="d-flex rounded overflow-hidden border mb-3" style="height: 32px" aria-label="Mapa de RAM para {{ $label }}">
                            @foreach ($mode['layout'] as $block)
                                <span class="{{ $block['state'] === 'ALLOCATED' ? 'bg-primary' : ($block['state'] === 'EXISTING' ? 'bg-secondary' : 'bg-light') }} border-end" style="width: {{ $block['size_bytes'] / $comparison['ram_size_bytes'] * 100 }}%" title="{{ $block['label'] }}: {{ $block['base'] }} a {{ $block['base'] + $block['size_bytes'] - 1 }}"></span>
                            @endforeach
                        </div>
                        <dl class="row small mb-3">
                            <dt class="col-7">Reserva nueva</dt><dd class="col-5 text-end">{{ $mode['reserved_bytes'] }} B</dd>
                            <dt class="col-7">Fragmentación interna</dt><dd class="col-5 text-end" data-comparison-waste>{{ $mode['internal_waste_bytes'] }} B</dd>
                            <dt class="col-7">Memoria libre final</dt><dd class="col-5 text-end">{{ $mode['remaining_free_bytes'] }} B</dd>
                        </dl>
                        <p class="small text-break">{{ $mode['note'] }}</p>
                        <div class="table-responsive">
                            <table class="table table-sm small mb-0">
                                <caption class="visually-hidden">Intervalos finales de {{ $label }}</caption>
                                <thead><tr><th>Bloque</th><th>Base</th><th>Bytes</th></tr></thead>
                                <tbody>
                                    @foreach ($mode['layout'] as $block)
                                        <tr><th class="text-break fw-normal">{{ $block['label'] }}</th><td>{{ $block['base'] }}</td><td>{{ $block['size_bytes'] }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        @endforeach
    </div>
    <p class="small"><span class="badge bg-secondary">A / B existentes</span> <span class="badge bg-primary">Asignación nueva</span> <span class="badge bg-label-secondary">Libre</span></p>
    <section class="card">
        <div class="card-header"><h2 class="h5 mb-0">Comparación técnica</h2></div>
        <div class="table-responsive">
            <table class="table mb-0">
                <caption class="visually-hidden">Propiedades de tres técnicas de asignación</caption>
                <thead><tr><th>Propiedad</th><th>Contigua variable</th><th>Paginación</th><th>Segmentación</th></tr></thead>
                <tbody>
                    <tr><th>Unidad</th><td>Proceso completo</td><td>Página y marco fijos</td><td>Segmento de tamaño variable</td></tr>
                    <tr><th>Ubicación</th><td>Un intervalo por proceso</td><td>Marcos distribuidos</td><td>Cada segmento ocupa un intervalo; el proceso puede distribuirse</td></tr>
                    <tr><th>Fragmentación</th><td>Externa: huecos separados</td><td>Interna: espacio sobrante de la última página</td><td>Externa: huecos entre segmentos</td></tr>
                    <tr><th>Estructura</th><td>Base y límite del proceso</td><td>Tabla de páginas por proceso</td><td>Tabla de bases y límites por segmento</td></tr>
                    <tr><th>Ventaja</th><td>Asignación y traducción sencillas</td><td>Aprovecha marcos libres separados</td><td>Representa código, datos y otras unidades lógicas</td></tr>
                    <tr><th>Límite</th><td>Requiere un hueco suficientemente grande</td><td>Necesita tablas y puede desperdiciar espacio dentro de una página</td><td>Requiere un hueco suficiente para cada segmento</td></tr>
                </tbody>
            </table>
        </div>
        <div class="card-body small">
            <p>En este ejemplo, paginación carga todo el proceso y segmentación divide la solicitud en código y datos. Esa división ilustra una asignación posible; no garantiza que cualquier conjunto de segmentos encaje.</p>
            <p class="mb-0">Referencias: OSTEP, <a href="https://pages.cs.wisc.edu/~remzi/OSTEP/vm-paging.pdf" target="_blank" rel="noopener">Paging</a>, <a href="https://pages.cs.wisc.edu/~remzi/OSTEP/vm-segmentation.pdf" target="_blank" rel="noopener">Segmentation</a> y <a href="https://pages.cs.wisc.edu/~remzi/OSTEP/vm-freespace.pdf" target="_blank" rel="noopener">Free-Space Management</a>.</p>
        </div>
    </section>
</div>
