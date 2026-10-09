<div @if($snapshot) wire:poll.5s.visible @endif data-presentation>
    <section class="card mb-3" data-paging-panel="processes"><div class="card-body row g-3 align-items-end">
        <div class="col-md-5"><label class="form-label" for="paging-scenario">Escenario de paginación</label><select id="paging-scenario" class="form-select @error('scenarioId') is-invalid @enderror" wire:model.live="scenarioId"><option value="">Seleccionar escenario</option>@foreach ($scenarios as $scenario)<option value="{{ $scenario->id }}">{{ $scenario->name }} · #{{ $scenario->id }}</option>@endforeach</select>@error('scenarioId')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-5"><label class="form-label" for="paging-process">Proceso seleccionado</label><select id="paging-process" class="form-select @error('processId') is-invalid @enderror" wire:model.live="processId" @disabled(!$snapshot)><option value="">Seleccionar proceso</option>@foreach ($snapshot['processes'] ?? [] as $process)<option value="{{ $process->id }}">{{ $process->name }} · #{{ $process->id }}</option>@endforeach</select>@error('processId')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-2"><p class="small mb-1">Page Faults acumulados</p><strong class="h4" data-presentation-faults>{{ $snapshot['page_fault_count'] ?? 0 }}</strong></div>
    </div></section>
    @if (!$snapshot)<div class="alert alert-info">Selecciona un escenario preparado para comenzar la exposición.</div>@endif
    <div class="row g-3">
        <div class="col-lg-5">
            @livewire('page-request', ['scenarioId' => $scenarioId, 'processId' => $processId], key('presentation-cpu-'.$scenarioId.'-'.$processId))
        </div>
        <div class="col-lg-7">
            <div class="mb-3">@include('paging.partials.page-overview')</div>
            <section class="card mb-3" data-paging-panel="ram">
                <div class="card-header d-flex flex-wrap justify-content-between gap-2"><h2 class="h5 mb-0">RAM simulada</h2>@if ($snapshot)<span class="small">{{ $snapshot['ram']['used_bytes'] / 1024 }} / {{ $snapshot['ram']['total_bytes'] / 1024 }} KB · {{ $snapshot['ram']['frames_used'] }} marcos ocupados</span>@endif</div>
                <div class="card-body">@if($snapshot)<x-paging.memory-grid :frames="$frameRows" :selected-process="$snapshot['selected_process']" />@else<p class="mb-0 text-body-secondary">Sin memoria seleccionada.</p>@endif</div>
            </section>
            <section class="card" data-paging-panel="secondary">
                <div class="card-header"><h2 class="h5 mb-1">Almacenamiento secundario</h2>@if($snapshot)<p class="small mb-0">{{ $snapshot['secondary']['used_bytes'] / 1024 }} / {{ $snapshot['secondary']['total_bytes'] / 1024 }} KB ocupados · Páginas del proceso en DISCO: {{ $snapshot['secondary']['selected_pages'] }}</p>@endif</div>
                <div class="card-body"><div class="d-flex flex-wrap gap-2">@forelse($secondaryRows as $page)<span class="badge bg-label-primary text-break text-wrap mw-100" data-secondary-page="{{ $page->page_number }}">{{ $snapshot['selected_process']->name }} · P{{ $page->page_number }}</span>@empty<span class="small text-body-secondary">No hay páginas del proceso en secundaria.</span>@endforelse</div>@if($secondaryRows->hasPages()){{ $secondaryRows->links() }}@endif</div>
            </section>
        </div>
    </div>
</div>
