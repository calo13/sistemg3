<x-guest-layout>
    <x-slot name="title">Administración de memoria</x-slot>
    @php($academic = config('memorylab.academic'))

    <div class="ml-home">
        <a class="ml-skip-link" href="#explora">Ir al contenido</a>
        <header class="ml-site-header">
            <div class="ml-container ml-header-inner">
                <a class="ml-brand" href="{{ url('/') }}" aria-label="MemoryLab — Inicio">
                    <img src="{{ asset('images/umg-logo.png') }}" width="44" height="44" alt="Escudo de la Universidad Mariano Gálvez" fetchpriority="high">
                    <span><strong>MemoryLab</strong><small>{{ $academic['course'] }}</small></span>
                </a>
                <nav class="ml-site-nav" aria-label="Navegación de la portada">
                    <a href="#conceptos">Conceptos</a>
                    <a href="#como-funciona">Cómo empezar</a>
                    <a href="#equipo">Integrantes</a>
                </nav>
                <a class="btn btn-primary btn-sm ml-header-access" href="{{ auth()->check() ? route('dashboard') : route('login') }}">
                    {{ auth()->check() ? 'Ir al panel' : 'Iniciar sesión' }} <x-home-icon name="arrow" />
                </a>
            </div>
        </header>

        <div class="ml-container ml-page-content">
            <section id="explora" class="card ml-intro" aria-labelledby="ml-hero-title" data-ml-reveal>
                <div class="ml-hero-copy bg-primary text-white">
                    <div class="ml-intro-heading"><p class="ml-intro-label">Simulador interactivo</p><span class="badge bg-danger">{{ $academic['group'] }}</span></div>
                    <h1 id="ml-hero-title">Administración<br>de memoria</h1>
                    <p class="ml-hero-description">Estudia cómo un sistema operativo organiza la memoria de sus procesos. Practica paginación, segmentación y traducción de direcciones con escenarios que puedes consultar en el laboratorio.</p>
                    <div class="ml-hero-actions">
                        <a class="btn btn-light ml-button" href="{{ auth()->check() ? route('dashboard') : route('login') }}">Entrar al simulador <x-home-icon name="arrow" /></a>
                        <a class="btn btn-outline-light ml-button" href="#conceptos">Ver conceptos</a>
                    </div>
                    <p class="ml-hero-note"><x-home-icon name="info" />Proyecto educativo · La memoria representada es simulada.</p>
                </div>
                <figure class="ml-university">
                    <img class="ml-university-logo" src="{{ asset('images/umg-logo.png') }}" width="184" height="185" alt="Escudo de la Universidad Mariano Gálvez de Guatemala" decoding="async">
                    <figcaption><strong>{{ $academic['university'] }}</strong><span>{{ $academic['degree'] }}</span></figcaption>
                </figure>
            </section>

            <section id="conceptos" class="ml-section" aria-labelledby="ml-concepts-title">
                <div class="ml-section-heading"><div><h2 id="ml-concepts-title">Conceptos del simulador</h2><p>Los temas que puedes practicar en los módulos.</p></div></div>
                <div class="ml-concept-grid" data-ml-reveal>
                    <article class="card ml-concept-card">
                        <div class="ml-concept-title"><span class="ml-concept-glyph"><i class="bx bx-grid-alt" aria-hidden="true"></i></span><h3>Paginación</h3></div>
                        <p>Divide un proceso en páginas de igual tamaño. La tabla de páginas indica en qué marco de RAM se encuentra cada página presente.</p>
                        <div class="ml-concept-tags"><span class="badge bg-label-primary">Páginas y marcos</span><span class="badge bg-label-secondary">FIFO</span></div>
                    </article>
                    <article class="card ml-concept-card">
                        <div class="ml-concept-title"><span class="ml-concept-glyph ml-concept-glyph-red"><x-home-icon name="layers" /></span><h3>Segmentación</h3></div>
                        <p>Organiza un proceso en partes, como código, datos y pila. Cada segmento tiene una dirección base y un límite para validar los accesos.</p>
                        <div class="ml-concept-tags"><span class="badge bg-label-primary">Base</span><span class="badge bg-label-secondary">Límite</span></div>
                    </article>
                    <article class="card ml-concept-card">
                        <div class="ml-concept-title"><span class="ml-concept-glyph"><x-home-icon name="map" /></span><h3>Traducción de direcciones</h3></div>
                        <p>Calcula dónde está un dato en la RAM a partir de la dirección que utiliza el proceso y de la información de su tabla.</p>
                        <div class="ml-concept-tags"><span class="badge bg-label-primary">Dirección lógica</span><span class="badge bg-label-secondary">Dirección física</span></div>
                    </article>
                </div>
            </section>

            <section id="como-funciona" class="ml-section" aria-labelledby="ml-how-title">
                <div class="ml-section-heading"><div><h2 id="ml-how-title">Cómo empezar</h2><p>Consulta las opciones disponibles según el rol de tu cuenta.</p></div></div>
                <ol class="card ml-steps" data-ml-reveal>
                    <li><span class="ml-step-number">1</span><div><h3>Inicia sesión</h3><p>Accede con tu cuenta para abrir el panel del proyecto.</p></div></li>
                    <li><span class="ml-step-number">2</span><div><h3>Selecciona un módulo</h3><p>Elige paginación, segmentación o traducción desde el menú.</p></div></li>
                    <li><span class="ml-step-number">3</span><div><h3>Revisa el escenario</h3><p>Relaciona la tabla y el mapa de RAM con el resultado de cada acceso.</p></div></li>
                </ol>
            </section>

            <section id="equipo" class="ml-section" aria-labelledby="ml-team-title">
                <div class="ml-section-heading"><div><h2 id="ml-team-title">Integrantes del proyecto</h2><p>{{ $academic['degree'] }} · {{ $academic['course'] }} · {{ $academic['group'] }}</p></div></div>
                <div class="card ml-team-card" data-ml-reveal>
                    <table class="table ml-team-table mb-0">
                        <caption class="visually-hidden">Integrantes de {{ $academic['group'] }} y sus carnés universitarios.</caption>
                        <thead><tr><th scope="col">Nombres y apellidos</th><th scope="col">Carné</th></tr></thead>
                        <tbody>
                            @foreach ($academic['members'] as $member)
                                <tr class="ml-team-member"><th scope="row">{{ $member['name'] }}</th><td>{{ $member['carnet'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="ml-closing" data-ml-reveal>
                    <div><h2>Continuar al simulador</h2><p>Accede a los escenarios disponibles desde el panel.</p></div>
                    <div class="ml-closing-actions">
                        <a class="btn btn-primary ml-button" href="{{ auth()->check() ? route('dashboard') : route('login') }}">Abrir MemoryLab <x-home-icon name="arrow" /></a>
                        @guest
                            @if (Route::has('register'))<a class="ml-create-account" href="{{ route('register') }}">Crear una cuenta</a>@endif
                        @endguest
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-guest-layout>
