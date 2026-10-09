<x-guest-layout>
    <x-slot name="title">Simulador de administración de memoria</x-slot>
    @php($academic = config('memorylab.academic'))

    <div class="ml-home">
        <a class="ml-skip-link" href="#explora">Ir al contenido</a>
        <header class="ml-site-header">
            <div class="ml-container ml-header-inner">
                <a class="ml-brand" href="{{ url('/') }}" aria-label="MemoryLab — Inicio" data-ml-reveal>
                    <img src="{{ asset('images/umg-logo.png') }}" width="56" height="56" alt="Escudo de la Universidad Mariano Gálvez" fetchpriority="high">
                    <span><strong>MemoryLab</strong><small>{{ $academic['degree'] }}</small></span>
                </a>
                <nav class="ml-site-nav" aria-label="Navegación de la portada">
                    <a href="#conceptos" data-ml-reveal data-ml-delay="40">Conceptos</a>
                    <a href="#como-funciona" data-ml-reveal data-ml-delay="80">Cómo empezar</a>
                    <a href="#equipo" data-ml-reveal data-ml-delay="120">Integrantes</a>
                </nav>
                @auth
                    <a class="ml-header-access" href="{{ route('dashboard') }}" data-ml-reveal data-ml-delay="160">Ir al panel <x-home-icon name="arrow" /></a>
                @else
                    <a class="ml-header-access" href="{{ route('login') }}" data-ml-reveal data-ml-delay="160">Iniciar sesión <x-home-icon name="arrow" /></a>
                @endauth
            </div>
        </header>

        <section id="explora" class="ml-hero" aria-labelledby="ml-hero-title">
            <div class="ml-container ml-hero-grid">
                <div class="ml-hero-copy">
                    <p class="ml-eyebrow" data-ml-reveal><span></span>{{ $academic['course'] }} · {{ $academic['group'] }}</p>
                    <h1 id="ml-hero-title" data-ml-reveal data-ml-delay="60">Simulador de<br>administración de memoria</h1>
                    <p class="ml-hero-description" data-ml-reveal data-ml-delay="120">Proyecto académico para estudiar paginación, segmentación y traducción de direcciones. Usa el simulador para ver cómo se organiza la memoria de un proceso.</p>
                    <div class="ml-hero-actions">
                        <a class="btn btn-primary ml-button" href="{{ auth()->check() ? route('dashboard') : route('login') }}" data-ml-reveal data-ml-delay="180">Entrar al simulador <x-home-icon name="arrow" /></a>
                        <a class="btn btn-outline-primary ml-button" href="#conceptos" data-ml-reveal data-ml-delay="220">Conocer el proyecto</a>
                    </div>
                    <div class="ml-hero-caption" data-ml-reveal data-ml-delay="240"><x-home-icon name="book" />{{ $academic['university'] }}</div>
                </div>

                <figure class="card ml-university" data-ml-reveal data-ml-delay="100">
                    <div class="card-body">
                        <img class="ml-university-logo" src="{{ asset('images/umg-logo.png') }}" width="256" height="257" alt="Escudo de la Universidad Mariano Gálvez de Guatemala" decoding="async" data-ml-reveal data-ml-delay="180">
                        <h2 class="h5" data-ml-reveal data-ml-delay="220">{{ $academic['university'] }}</h2>
                        <p class="mb-0" data-ml-reveal data-ml-delay="240">{{ $academic['degree'] }}</p>
                    </div>
                    <figcaption class="card-footer" data-ml-reveal>{{ $academic['course'] }} · {{ $academic['group'] }}</figcaption>
                </figure>
            </div>
        </section>

        <section id="conceptos" class="ml-section ml-concepts" aria-labelledby="ml-concepts-title">
            <div class="ml-container">
                <div class="ml-section-heading"><div data-ml-reveal><p class="ml-eyebrow">Administración de memoria</p><h2 id="ml-concepts-title">Conceptos del simulador</h2></div><p data-ml-reveal data-ml-delay="80">Estos son los temas que puedes practicar<br class="ml-desktop-break"> en los módulos de MemoryLab.</p></div>
                <div class="ml-concept-grid">
                    <article class="card ml-concept-card" data-ml-reveal>
                        <div class="ml-concept-icon"><span class="ml-concept-glyph"><i class="bx bx-grid-alt" aria-hidden="true"></i></span><span>01</span></div>
                        <h3>Paginación</h3>
                        <p>Divide un proceso en páginas del mismo tamaño. Cada página puede ocupar un marco de RAM; la tabla de páginas guarda esa ubicación.</p>
                        <div class="ml-pages-diagram" aria-label="Páginas de igual tamaño"><span>P0</span><span>P1</span><span>P2</span><small>Partes del mismo tamaño</small></div>
                    </article>
                    <article class="card ml-concept-card" data-ml-reveal data-ml-delay="80">
                        <div class="ml-concept-icon ml-concept-icon-red"><span class="ml-concept-glyph"><x-home-icon name="layers" /></span><span>02</span></div>
                        <h3>Segmentación</h3>
                        <p>Organiza un proceso en partes de distinto tamaño: código, datos o pila. Cada segmento tiene una base donde empieza y un límite de acceso.</p>
                        <div class="ml-segments-diagram" aria-label="Segmentos de distintos tamaños"><span>Código</span><span>Datos</span><span>Pila</span><small>Partes con una función</small></div>
                    </article>
                    <article class="card ml-concept-card" data-ml-reveal data-ml-delay="160">
                        <div class="ml-concept-icon"><span class="ml-concept-glyph"><x-home-icon name="map" /></span><span>03</span></div>
                        <h3>Traducción de direcciones</h3>
                        <p>Relaciona la dirección que utiliza un proceso con su ubicación en la RAM. Observa cómo la tabla permite encontrar el dato solicitado.</p>
                        <div class="ml-address-diagram" aria-label="De la dirección lógica a la dirección física"><span>Lógica</span><x-home-icon name="arrow" /><span>Física</span><small>Del proceso a la RAM</small></div>
                    </article>
                </div>
                <p class="ml-educational-note" data-ml-reveal><x-home-icon name="info" />La memoria del simulador es educativa; no modifica la RAM de tu equipo.</p>
            </div>
        </section>

        <section id="como-funciona" class="ml-how-section" aria-labelledby="ml-how-title">
            <div class="ml-container ml-how-grid">
                <div><p class="ml-eyebrow" data-ml-reveal>Guía de inicio</p><h2 id="ml-how-title" data-ml-reveal data-ml-delay="60">Cómo usar MemoryLab</h2><p data-ml-reveal data-ml-delay="120">Sigue esta secuencia para entrar y consultar los módulos. El panel muestra las opciones disponibles según tu rol.</p></div>
                <ol class="ml-steps">
                    <li data-ml-reveal><span>01</span><div><h3>Inicia sesión</h3><p>Accede al panel con tu cuenta para consultar los módulos de memoria.</p></div></li>
                    <li data-ml-reveal data-ml-delay="80"><span>02</span><div><h3>Selecciona un módulo</h3><p>Abre paginación, segmentación o traducción de direcciones desde el menú.</p></div></li>
                    <li data-ml-reveal data-ml-delay="160"><span>03</span><div><h3>Revisa la simulación</h3><p>Consulta la tabla, el mapa de RAM y el resultado de los accesos del escenario.</p></div></li>
                </ol>
            </div>
        </section>

        <section id="equipo" class="ml-section ml-team-section" aria-labelledby="ml-team-title">
            <div class="ml-container">
                <div class="ml-section-heading"><div data-ml-reveal><p class="ml-eyebrow">{{ $academic['degree'] }} · {{ $academic['group'] }}</p><h2 id="ml-team-title">Integrantes del proyecto</h2></div><p data-ml-reveal data-ml-delay="80">{{ $academic['university'] }}<br>{{ $academic['course'] }}</p></div>
                <div class="ml-team-grid">
                    @foreach ($academic['members'] as $member)
                        <article class="ml-team-member" data-ml-reveal data-ml-delay="{{ ($loop->index % 3) * 60 }}"><span class="ml-member-number" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><div><h3>{{ $member['name'] }}</h3><p>Carné <span>{{ $member['carnet'] }}</span></p></div></article>
                    @endforeach
                </div>
                <div class="card ml-closing" data-ml-reveal>
                    <div><h2>Accede al simulador</h2><p>Inicia sesión para consultar los escenarios y módulos del proyecto.</p></div>
                    <div class="ml-closing-actions">
                        <a class="btn btn-primary ml-button" href="{{ auth()->check() ? route('dashboard') : route('login') }}">Abrir MemoryLab <x-home-icon name="arrow" /></a>
                        @guest
                            @if (Route::has('register'))<a class="ml-create-account" href="{{ route('register') }}">Crear una cuenta</a>@endif
                        @endguest
                    </div>
                </div>
            </div>
        </section>
    </div>
</x-guest-layout>
