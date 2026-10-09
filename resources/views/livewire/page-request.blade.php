<section class="card h-100" aria-labelledby="paging-cpu-title" data-paging-panel="cpu" data-flow-process="{{ $processId }}" @if($snapshot) wire:poll.5s.visible @endif>
    <div class="card-header"><h2 id="paging-cpu-title" class="h5 mb-1">Solicitud de CPU</h2><p class="small text-body-secondary mb-0">Proceso seleccionado</p></div>
    <div class="card-body">
        @foreach (['scenarioId', 'processId'] as $field)
            @error($field)<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
        @endforeach
        @if (! ($snapshot['selected_process'] ?? null))
            <p class="text-body-secondary mb-0">Selecciona un proceso para consultar su última solicitud registrada.</p>
        @else
            @if ($canRequest)
                <form wire:submit="requestPage" class="mb-3">
                    <label class="form-label" for="cpu-mode">Modo de simulación</label>
                    <select id="cpu-mode" class="form-select mb-3 @error('mode') is-invalid @enderror" wire:model.live="mode" wire:loading.attr="disabled">
                        <option value="automatic">Automático</option>
                        <option value="step">Paso a paso</option>
                    </select>
                    @error('mode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <label class="form-label text-break" for="cpu-page">Página del proceso {{ $snapshot['selected_process']->name }}</label>
                    <select id="cpu-page" class="form-select @error('pageNumber') is-invalid @enderror" wire:model.live="pageNumber" wire:loading.attr="disabled">
                        @foreach ($snapshot['pages'] as $page)
                            <option value="{{ $page->page_number }}">Página {{ $page->page_number }} · {{ $page->present ? 'RAM' : 'DISCO' }}</option>
                        @endforeach
                    </select>
                    @error('pageNumber')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <button class="btn btn-primary mt-3" type="submit" wire:loading.attr="disabled" @disabled($snapshot['pages']->isEmpty() || ($pending !== null && $currentStep < count($flowSteps)))>Acceder a página</button>
                    <span wire:loading wire:target="requestPage" class="small ms-2" role="status">Registrando solicitud…</span>
                </form>
            @endif
            @if ($flowSteps)
                <ol class="list-group mb-3" aria-label="Flujo de acceso a memoria" data-memory-flow>
                    @foreach ($flowSteps as $index => $flowStep)
                        <li class="list-group-item {{ $currentStep === $index + 1 ? 'bg-label-primary' : '' }} {{ $currentStep < $index + 1 ? 'opacity-50' : '' }}" data-flow-step="{{ $flowStep['id'] }}" data-flow-index="{{ $index + 1 }}" @if ($currentStep === $index + 1) aria-current="step" @endif>
                            <div class="fw-semibold small">{{ $index + 1 }}. {{ $flowStep['label'] }}</div>
                            @if ($currentStep >= $index + 1)<div class="small text-break">{{ $flowStep['detail'] }}</div>@endif
                        </li>
                    @endforeach
                </ol>
                @if ($canRequest && $pending !== null && $currentStep < count($flowSteps))
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button type="button" class="btn btn-primary" wire:click="nextStep" wire:loading.attr="disabled" data-next-step>Siguiente</button>
                        <button type="button" class="btn btn-outline-secondary" wire:click="clearFlow" wire:loading.attr="disabled">Cerrar recorrido</button>
                    </div>
                    <p class="small text-body-secondary">La solicitud se registra al iniciar. {{ $pending['present'] ? 'El acceso presente se confirma en el paso 4.' : 'La carga se confirma en el paso 6.' }} El servidor vuelve a comprobar el estado actual antes de resolver.</p>
                @endif
            @endif
            @if ($stateChanged)<div class="alert alert-warning small" role="status">La memoria cambió durante el recorrido. La resolución utiliza la tabla actual y adapta el resultado.</div>@endif
            @if ($displayResult)
                <div class="alert alert-{{ $displayResult['outcome'] === 'PAGE_HIT' ? 'success' : 'danger' }} text-break" role="status" data-cpu-result data-cpu-outcome="{{ $displayResult['outcome'] }}">
                    @if ($sharedResult)<p class="small mb-2" data-shared-result>Último acceso guardado · {{ $displayResult['occurred_at']->timezone(config('memorylab.display_timezone'))->format('d/m/Y H:i:s') }}</p>@endif
                    <strong>{{ $displayResult['outcome'] }}</strong> · CPU solicita P{{ $displayResult['page_number'] }}.
                    <div class="mt-1">{{ $displayResult['outcome'] === 'PAGE_HIT' ? 'Página localizada en RAM.' : 'Página ausente: se cargó desde almacenamiento secundario simulado.' }}</div>
                    <div class="small mt-1">Marco {{ $displayResult['frame_number'] }} · Dirección física inicial: {{ $displayResult['physical_address'] }} bytes.</div>
                    @if ($displayResult['evicted'])<div class="small mt-1">RAM llena: FIFO devuelve {{ $displayResult['evicted']['process_name'] }} P{{ $displayResult['evicted']['page_number'] }} a secundaria.</div>@endif
                    <div class="fw-semibold text-success mt-2">{{ $currentStep >= count($flowSteps) ? 'Acceso completado.' : 'RAM y tabla actualizadas. Continúa el recorrido explicativo.' }}</div>
                </div>
            @endif
            @if ($snapshot['last_request'] === null)
                <p class="text-body-secondary mb-0" data-cpu-empty>No hay solicitudes registradas para este proceso.</p>
            @else
                <span class="badge bg-label-info mb-3">PAGE_REQUEST</span>
                <dl class="row mb-3">
                    <dt class="col-6 fw-normal">Página solicitada</dt><dd class="col-6 text-end" data-cpu-page>{{ $snapshot['last_request']['page_number'] ?? 'Sin dato' }}</dd>
                    <dt class="col-6 fw-normal">Fecha (Guatemala)</dt><dd class="col-6 text-end small">{{ $snapshot['last_request']['occurred_at']->timezone(config('memorylab.display_timezone'))->format('d/m/Y H:i:s') }}</dd>
                </dl>
                <p class="small text-break mb-0">{{ $snapshot['last_request']['description'] }}</p>
            @endif
        @endif
    </div>
</section>
