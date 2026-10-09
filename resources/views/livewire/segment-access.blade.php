<section class="card mt-4" aria-labelledby="segment-access-title" data-segment-access @if($snapshot) wire:poll.5s.visible @endif>
    <div class="card-header"><h2 id="segment-access-title" class="h5 mb-1">Direccionamiento por segmentación</h2><p class="small text-body-secondary mb-0">Validar el offset antes de sumar la base.</p></div>
    <div class="card-body">
        @foreach (['scenarioId', 'processId'] as $field)
            @error($field)<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
        @endforeach
        @if ($snapshot['selected_process'] ?? null)
            @if ($canAccess)
                <form wire:submit="access" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label" for="access-segment">Segmento</label>
                        <select id="access-segment" class="form-select @error('segmentNumber') is-invalid @enderror" wire:model.live="segmentNumber">
                            <option value="">Seleccionar segmento</option>
                            @foreach ($snapshot['segments']->where('status', \App\Enums\SegmentStatus::Active) as $segment)<option value="{{ $segment->segment_number }}">{{ $segment->segment_number }} · {{ $segment->name }} · límite {{ $segment->size_bytes }}</option>@endforeach
                        </select>
                        @error('segmentNumber')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="segment-offset">Offset en bytes</label>
                        <input id="segment-offset" class="form-control @error('offset') is-invalid @enderror" type="number" min="0" step="1" wire:model="offset">
                        @error('offset')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3"><button type="submit" class="btn btn-primary" wire:loading.attr="disabled" @disabled($snapshot['segments']->where('status', \App\Enums\SegmentStatus::Active)->isEmpty())>Acceder</button></div>
                </form>
            @endif
            @if ($displayResult)
                <div class="alert alert-{{ $displayResult['valid'] ? 'success' : 'danger' }} mt-3 mb-0 text-break" role="status" data-segment-outcome="{{ $displayResult['outcome'] }}">
                    @if ($sharedResult)<p class="small mb-2" data-shared-segment-result>Último acceso guardado · {{ $displayResult['occurred_at']->timezone(config('memorylab.display_timezone'))->format('d/m/Y H:i:s') }}</p>@endif
                    <strong>{{ $displayResult['outcome'] }}</strong> · {{ $displayResult['segment_name'] }} (S{{ $displayResult['segment_number'] }})
                    <p class="mb-2 mt-2">Offset solicitado: {{ $displayResult['offset'] }} · Tamaño/límite: {{ $displayResult['size_bytes'] }} · Último offset válido: {{ $displayResult['size_bytes'] - 1 }}.</p>
                    @if ($displayResult['valid'])
                        <p class="mb-0">{{ $displayResult['offset'] }} &lt; {{ $displayResult['size_bytes'] }}. Dirección física = base {{ $displayResult['base'] }} + offset {{ $displayResult['offset'] }} = <strong data-segment-physical>{{ $displayResult['physical_address'] }}</strong> bytes.</p>
                    @else
                        <p class="mb-0">{{ $displayResult['offset'] }} ≥ {{ $displayResult['size_bytes'] }}: el offset queda fuera del segmento. El acceso se rechaza y no se calcula una dirección física.</p>
                    @endif
                </div>
            @elseif (! $canAccess)
                <p class="text-body-secondary mb-0">Consulta la tabla y el mapa. Administrador y Operador pueden ejecutar accesos a segmentos.</p>
            @endif
        @else
            <p class="text-body-secondary mb-0">Selecciona un proceso con segmentos activos.</p>
        @endif
    </div>
</section>
