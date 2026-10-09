@props(['frames', 'selectedProcess' => null])

<div class="d-flex flex-wrap gap-3 small mb-3" aria-label="Leyenda de marcos">
    <span><span class="badge bg-label-secondary">Libre</span> Sin página</span>
    <span><span class="badge bg-label-info">Ocupado</span> Página en RAM</span>
</div>
<div class="row row-cols-2 row-cols-sm-4 row-cols-xl-6 g-2" data-memory-grid>
    @foreach ($frames as $frame)
        <div class="col" wire:key="memory-frame-{{ $frame->id }}">
            <div class="border rounded p-2 h-100 {{ $frame->page ? 'bg-label-info' : 'bg-label-secondary' }} {{ $frame->page && $frame->page->process_id === $selectedProcess?->id ? 'border-primary' : '' }}" data-memory-frame="{{ $frame->frame_number }}" data-frame-state="{{ $frame->page ? 'OCCUPIED' : 'FREE' }}" data-frame-process="{{ $frame->page?->process_id }}" data-frame-page="{{ $frame->page?->page_number }}">
                <div class="fw-semibold small">Marco {{ $frame->frame_number }}</div>
                @if ($frame->page)
                    <div class="small text-break">{{ $frame->page->process->name }}</div>
                    <div class="small">P{{ $frame->page->page_number }} · Ocupado</div>
                @else
                    <div class="small">Libre</div>
                @endif
            </div>
        </div>
    @endforeach
</div>
@if ($frames->hasPages())<div class="mt-3">{{ $frames->links() }}</div>@endif
<p class="small text-body-secondary mt-3 mb-0">Los bloques representan marcos del escenario completo. El borde morado identifica páginas del proceso seleccionado.</p>
