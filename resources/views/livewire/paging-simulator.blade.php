<div @if ($snapshot !== null) wire:poll.5s.visible @endif>
    <section class="card mb-4" aria-labelledby="paging-scenario-title">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h2 id="paging-scenario-title" class="h5 mb-0">Escenario</h2>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('memory.configuration') }}">Ver memoria</a>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('processes.index') }}">{{ $canCreateProcesses ? 'Gestionar procesos' : 'Consultar procesos' }}</a>
                    @can('results.view')<a class="btn btn-sm btn-outline-secondary" href="{{ route('translation.index') }}">Traducir dirección</a>@endcan
                </div>
            </div>
            <label class="form-label" for="paging-scenario">Seleccionar escenario</label>
            <select id="paging-scenario" class="form-select @error('scenarioId') is-invalid @enderror" wire:model.live="scenarioId" wire:loading.attr="disabled" aria-describedby="paging-scenario-help @error('scenarioId') paging-scenario-error @enderror">
                <option value="">Seleccionar un escenario</option>
                @foreach ($scenarios as $scenario)
                    <option value="{{ $scenario->id }}">{{ $scenario->name }} · #{{ $scenario->id }}</option>
                @endforeach
            </select>
            @error('scenarioId')<div id="paging-scenario-error" class="invalid-feedback">{{ $message }}</div>@enderror
            <p id="paging-scenario-help" class="form-text mb-0">La selección se comparte con tu dashboard. Los datos representan memoria simulada; 1 KB equivale a 1024 bytes.</p>
        </div>
    </section>

    @if ($snapshot === null)
        <div class="alert alert-info" role="status">Selecciona un escenario de paginación con memoria configurada para consultar sus procesos y páginas.</div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            @include('paging.partials.process-selection')
        </div>
        <div class="col-lg-8">
            @include('paging.partials.page-overview')
        </div>
    </div>
    <div class="row g-4">
        <div class="col-12">@include('paging.partials.ram-summary')</div>
        <div class="col-md-6">@include('paging.partials.secondary-summary')</div>
        <div class="col-md-6">@include('paging.partials.cpu-request')</div>
    </div>
</div>
