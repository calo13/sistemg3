<x-app-layout>
    <x-slot name="title">Entregables académicos</x-slot>
    <x-slot name="header">
        <h1 class="h3 mb-1">Entregables académicos</h1>
        <p class="text-body-secondary mb-0">{{ $academic['course'] }} · {{ $academic['group'] }}</p>
    </x-slot>

    <section class="card mb-4" aria-labelledby="deliverables-academic-title">
        <div class="card-body">
            <p class="text-primary fw-medium mb-1" id="deliverables-academic-title">{{ $academic['university'] }}</p>
            <h2 class="h5 mb-2">{{ config('app.name') }} · {{ $academic['degree'] }}</h2>
            <p class="mb-0">Manual de uso, informe, material de exposición y demostración del simulador de administración de memoria.</p>
        </div>
    </section>

    <div class="row g-4 mb-4">
        @foreach (['manual', 'informe', 'presentacion'] as $key)
            @php($artifact = $artifacts[$key])
            <div class="{{ $key === 'manual' ? 'col-12' : 'col-md-6' }}">
                <section class="card h-100" aria-labelledby="deliverable-{{ $key }}" data-deliverable="{{ $key }}">
                    <div class="card-body d-flex flex-column align-items-start">
                        <span class="badge bg-label-primary mb-3">{{ $artifact['summary'] }}</span>
                        <h2 class="h5" id="deliverable-{{ $key }}">{{ $artifact['title'] }}</h2>
                        @if ($key === 'manual')
                            <p class="fw-medium mb-2">Cómo usar y demostrar MemoryLab</p>
                            <p class="text-body-secondary mb-2">Empieza aquí para seguir la secuencia de la práctica y entender cada resultado.</p>
                        @endif
                        <p class="text-body-secondary">{{ $artifact['description'] }}</p>
                        @if ($artifact['available'])
                            <a class="btn btn-primary mt-auto" href="{{ route('deliverables.download', ['artifact' => $key]) }}">Descargar {{ ['manual' => 'manual', 'informe' => 'informe', 'presentacion' => 'presentación'][$key] }}</a>
                        @else
                            <span class="badge bg-label-secondary mt-auto" data-deliverable-pending="{{ $key }}">Preparación temporal</span>
                        @endif
                    </div>
                </section>
            </div>
        @endforeach
    </div>

    <section class="card mb-4" aria-labelledby="deliverable-video" data-deliverable="video">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <h2 class="h5 mb-1" id="deliverable-video">{{ $artifacts['video']['title'] }}</h2>
                    <p class="text-body-secondary mb-0">{{ $artifacts['video']['summary'] }} · {{ $artifacts['video']['description'] }}</p>
                </div>
                @if ($artifacts['video']['available'])
                    <a class="btn btn-outline-primary" href="{{ route('deliverables.download', ['artifact' => 'video']) }}">Descargar video</a>
                @endif
            </div>
            @if ($artifacts['video']['available'])
                <video class="w-100 rounded bg-dark" controls playsinline preload="metadata" aria-label="Demostración de MemoryLab" data-deliverable-video>
                    <source src="{{ route('deliverables.video') }}" type="video/webm">
                    Tu navegador no reproduce este formato. Puedes descargar el video para abrirlo en tu equipo.
                </video>
            @else
                <div class="alert alert-secondary mb-0" data-deliverable-pending="video">Preparación temporal. El video aparecerá aquí cuando esté disponible.</div>
            @endif
        </div>
    </section>

    <section class="card" aria-labelledby="deliverables-team-title">
        <div class="card-header"><h2 class="h5 mb-0" id="deliverables-team-title">Integrantes del proyecto</h2></div>
        <div class="card-body">
            <ul class="list-unstyled mb-0">
                @foreach ($academic['members'] as $member)
                    <li class="d-flex flex-wrap justify-content-between gap-2 py-2 @unless($loop->last) border-bottom @endunless">
                        <span>{{ $member['name'] }}</span>
                        <span class="font-monospace">{{ $member['carnet'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
</x-app-layout>
