<section class="card h-100" aria-labelledby="paging-ram-title" data-paging-panel="ram">
    <div class="card-header"><h2 id="paging-ram-title" class="h5 mb-1">Memoria RAM simulada</h2><p class="small text-body-secondary mb-0">Total del escenario</p></div>
    <div class="card-body">
        @if ($snapshot !== null)
            <dl class="row mb-3">
                <dt class="col-7 fw-normal">RAM total</dt><dd class="col-5 text-end" data-paging-ram="total">{{ number_format($snapshot['ram']['total_bytes'] / 1024, 0, ',', '.') }} KB</dd>
                <dt class="col-7 fw-normal">Utilizada</dt><dd class="col-5 text-end" data-paging-ram="used">{{ number_format($snapshot['ram']['used_bytes'] / 1024, 0, ',', '.') }} KB</dd>
                <dt class="col-7 fw-normal">Disponible</dt><dd class="col-5 text-end" data-paging-ram="available">{{ number_format($snapshot['ram']['available_bytes'] / 1024, 0, ',', '.') }} KB</dd>
                <dt class="col-7 fw-normal">Marcos totales</dt><dd class="col-5 text-end" data-paging-frames="total">{{ $snapshot['ram']['frames_total'] }}</dd>
                <dt class="col-7 fw-normal">Ocupados</dt><dd class="col-5 text-end" data-paging-frames="used">{{ $snapshot['ram']['frames_used'] }}</dd>
                <dt class="col-7 fw-normal">Libres</dt><dd class="col-5 text-end" data-paging-frames="free">{{ $snapshot['ram']['frames_free'] }}</dd>
            </dl>
            <p class="small text-body-secondary mb-0">Cada marco tiene {{ number_format($snapshot['configuration']->page_size_bytes / 1024, 0, ',', '.') }} KB. Los marcos libres no contienen páginas.</p>
            <hr>
            <x-paging.memory-grid :frames="$frameRows" :selected-process="$snapshot['selected_process']" />
        @else
            <p class="text-body-secondary mb-0">Sin escenario configurado.</p>
        @endif
    </div>
</section>
