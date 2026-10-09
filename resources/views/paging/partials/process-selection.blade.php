<section class="card h-100" aria-labelledby="paging-process-title" data-paging-panel="processes">
    <div class="card-header"><h2 id="paging-process-title" class="h5 mb-0">Procesos</h2></div>
    <div class="card-body">
        <label class="form-label" for="paging-process">Seleccionar proceso</label>
        <select id="paging-process" class="form-select @error('processId') is-invalid @enderror" wire:model.live="processId" wire:loading.attr="disabled" @disabled($snapshot === null) aria-describedby="paging-process-help @error('processId') paging-process-error @enderror">
            <option value="">Seleccionar un proceso</option>
            @foreach ($snapshot['processes'] ?? [] as $process)
                <option value="{{ $process->id }}">{{ $process->name }} · #{{ $process->id }}</option>
            @endforeach
        </select>
        @error('processId')<div id="paging-process-error" class="invalid-feedback">{{ $message }}</div>@enderror
        <p id="paging-process-help" class="form-text">Sus páginas y solicitud de CPU se actualizan al seleccionarlo.</p>
        @if ($snapshot !== null && $snapshot['processes']->isEmpty())
            <p class="small text-body-secondary mb-0">No hay procesos en este escenario. {{ $canCreateProcesses ? 'Agrega uno desde Gestionar procesos.' : 'Un administrador u operador puede agregar procesos.' }}</p>
        @elseif ($snapshot['selected_process'] ?? null)
            <p class="fw-medium text-break mb-2" data-selected-process-name>{{ $snapshot['selected_process']->name }}</p>
            <span class="badge bg-label-{{ $snapshot['selected_process']->status->color() }} mb-3">{{ $snapshot['selected_process']->status->value }}</span>
            <dl class="row mb-0">
                <dt class="col-7 fw-normal">Tamaño</dt><dd class="col-5 text-end">{{ number_format($snapshot['selected_process']->size_bytes / 1024, $snapshot['selected_process']->size_bytes % 1024 === 0 ? 0 : 1, ',', '.') }} KB</dd>
                <dt class="col-7 fw-normal">Páginas</dt><dd class="col-5 text-end" data-selected-pages>{{ $snapshot['pages']->count() }}</dd>
                <dt class="col-7 fw-normal">En RAM</dt><dd class="col-5 text-end" data-selected-ram>{{ $snapshot['ram']['selected_frames_used'] }}</dd>
                <dt class="col-7 fw-normal">En secundaria</dt><dd class="col-5 text-end" data-selected-secondary>{{ $snapshot['secondary']['selected_pages'] }}</dd>
            </dl>
        @else
            <p class="small text-body-secondary mb-0">Selecciona un proceso para consultar sus páginas.</p>
        @endif
    </div>
</section>
