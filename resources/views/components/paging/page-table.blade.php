@props(['selectedProcess' => null, 'pages'])

<section class="card h-100" aria-labelledby="paging-pages-title" data-paging-panel="pages">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h2 id="paging-pages-title" class="h5 mb-0">Tabla de páginas</h2>
        <span class="small text-body-secondary">Del proceso seleccionado</span>
    </div>
    @if ($selectedProcess)
        <div class="table-responsive">
            <table class="table mb-0">
                <caption class="visually-hidden">Página, marco, presencia y estado del proceso {{ $selectedProcess->name }}</caption>
                <thead><tr><th scope="col">Página</th><th scope="col">Marco</th><th scope="col">Presente</th><th scope="col">Estado</th></tr></thead>
                <tbody>
                    @forelse ($pages as $page)
                        <tr wire:key="paging-page-{{ $page->id }}" data-paging-page="{{ $page->page_number }}">
                            <th scope="row" class="font-monospace">{{ $page->page_number }}</th>
                            <td class="font-monospace" data-page-frame="{{ $page->frame?->frame_number }}">{{ $page->frame?->frame_number ?? '—' }}</td>
                            <td data-page-present="{{ $page->present ? 'true' : 'false' }}">{{ $page->present ? 'Sí' : 'No' }}</td>
                            <td><span class="badge bg-label-{{ $page->present ? 'info' : 'primary' }}" data-page-location="{{ $page->present ? 'RAM' : 'DISCO' }}">{{ $page->present ? 'RAM' : 'DISCO' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-body-secondary text-center py-4">Este proceso no tiene páginas registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">
            @if ($pages->hasPages()){{ $pages->links() }}@endif
            <p class="small text-body-secondary mb-0">Presente significa que la página tiene un marco en RAM. DISCO representa almacenamiento secundario simulado; el guion indica que aún no tiene marco.</p>
        </div>
    @else
        <div class="card-body"><p class="text-body-secondary mb-0">Selecciona un proceso para ver la ubicación de sus páginas.</p></div>
    @endif
</section>
