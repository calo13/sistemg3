<div>
    <section class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6 col-xl-4"><label class="form-label" for="history-scenario">Escenario</label><select id="history-scenario" wire:model.live="scenarioId" class="form-select @error('scenarioId') is-invalid @enderror"><option value="">Todos los escenarios</option>@foreach ($scenarios as $scenario)<option value="{{ $scenario->id }}">{{ $scenario->name }} · #{{ $scenario->id }}</option>@endforeach</select>@error('scenarioId')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6 col-xl-4"><label class="form-label" for="history-process">Proceso</label><select id="history-process" wire:model.live="processId" class="form-select @error('processId') is-invalid @enderror" @disabled($processes->isEmpty())><option value="">Todos los procesos</option>@foreach ($processes as $process)<option value="{{ $process->id }}">{{ $process->name }} · #{{ $process->id }}</option>@endforeach</select>@error('processId')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6 col-xl-4"><label class="form-label" for="history-user">Usuario</label><select id="history-user" wire:model.live="userId" class="form-select @error('userId') is-invalid @enderror"><option value="">Todos los usuarios</option>@foreach ($users as $user)<option value="{{ $user->id }}">{{ $user->name }} · #{{ $user->id }}</option>@endforeach</select>@error('userId')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6 col-xl-4"><label class="form-label" for="history-type">Evento</label><select id="history-type" wire:model.live="eventType" class="form-select @error('eventType') is-invalid @enderror"><option value="">Todos los eventos</option>@foreach ($eventTypes as $type)<option value="{{ $type->value }}">{{ $type->value }}</option>@endforeach</select>@error('eventType')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6 col-xl-4"><label class="form-label" for="history-from">Desde (Guatemala)</label><input id="history-from" type="date" wire:model.live="fromDate" class="form-control @error('fromDate') is-invalid @enderror">@error('fromDate')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6 col-xl-4"><label class="form-label" for="history-to">Hasta, inclusive</label><input id="history-to" type="date" wire:model.live="toDate" class="form-control @error('toDate') is-invalid @enderror">@error('toDate')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-3"><button class="btn btn-outline-secondary" wire:click="clearFilters">Limpiar filtros</button><button class="btn btn-outline-primary" wire:click="$refresh">Actualizar historial</button></div>
        </div>
    </section>
    <section class="card">
        <div class="card-header"><h2 class="h5 mb-1">Eventos registrados</h2><p class="small mb-0"><span data-history-total>{{ $events->total() }}</span> eventos · Página {{ $events->currentPage() }} de {{ $events->lastPage() }}</p></div>
        <div class="table-responsive">
            <table class="table mb-0">
                <caption class="visually-hidden">Historial ordenado del más reciente al más antiguo</caption>
                <thead><tr><th>Fecha y hora</th><th>Usuario / proceso</th><th>Evento</th><th>Descripción</th></tr></thead>
                <tbody>
                    @forelse ($events as $event)
                        <tr data-history-event="{{ $event->id }}" data-history-type="{{ $event->type->value }}">
                            <td class="text-nowrap small">{{ $event->occurred_at->timezone(config('memorylab.display_timezone'))->format('d/m/Y H:i:s') }}</td>
                            <td class="text-break small">{{ $event->user?->name ?? 'Cuenta eliminada' }}<br>{{ $event->process?->name ?? 'Sin proceso' }}</td>
                            <td class="small text-break">{{ $event->type->value }}</td>
                            <td class="text-break small">{{ $event->description }}<div class="text-body-secondary mt-1">{{ $event->scenario?->name ?? 'Escenario eliminado' }} · #{{ $event->scenario_id }}</div></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-secondary py-4">No hay eventos para los filtros seleccionados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($events->hasPages())<div class="card-body pb-0">{{ $events->links() }}</div>@endif
    </section>
</div>
