<section class="card h-100" aria-labelledby="paging-secondary-title" data-paging-panel="secondary">
    <div class="card-header"><h2 id="paging-secondary-title" class="h5 mb-1">Almacenamiento secundario</h2><p class="small text-body-secondary mb-0">Capacidad simulada del escenario</p></div>
    <div class="card-body">
        @if ($snapshot !== null)
            <dl class="row mb-3">
                <dt class="col-7 fw-normal">Total</dt><dd class="col-5 text-end" data-paging-secondary="total">{{ number_format($snapshot['secondary']['total_bytes'] / 1024, 0, ',', '.') }} KB</dd>
                <dt class="col-7 fw-normal">Utilizado</dt><dd class="col-5 text-end" data-paging-secondary="used">{{ number_format($snapshot['secondary']['used_bytes'] / 1024, 0, ',', '.') }} KB</dd>
                <dt class="col-7 fw-normal">Disponible</dt><dd class="col-5 text-end" data-paging-secondary="available">{{ number_format($snapshot['secondary']['available_bytes'] / 1024, 0, ',', '.') }} KB</dd>
            </dl>
            @if ($snapshot['selected_process'])
                <p class="small mb-2">Páginas del proceso en secundaria: <strong>{{ $snapshot['secondary']['selected_pages'] }}</strong>.</p>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @forelse ($secondaryRows as $page)
                        <span class="badge bg-label-primary text-break text-wrap mw-100" wire:key="secondary-page-{{ $page->id }}" data-secondary-page="{{ $page->page_number }}">{{ $snapshot['selected_process']->name }} · P{{ $page->page_number }}</span>
                    @empty
                        <span class="small text-body-secondary">Todas sus páginas están en RAM.</span>
                    @endforelse
                </div>
                @if ($secondaryRows->hasPages()){{ $secondaryRows->links() }}@endif
            @endif
            <p class="small text-body-secondary mb-0">Cada página sin marco ocupa su tamaño completo en este almacenamiento simulado.</p>
        @else
            <p class="text-body-secondary mb-0">Sin escenario configurado.</p>
        @endif
    </div>
</section>
