<x-guest-layout>
    <x-slot name="title">MemoryLab · Aprende explorando</x-slot>
    @php($academic = config('memorylab.academic'))

    <div class="ml-home">
        <a class="ml-skip-link" href="#explora">Ir al contenido</a>
        <header class="ml-site-header">
            <div class="ml-container ml-header-inner">
                <a class="ml-brand" href="{{ url('/') }}" aria-label="MemoryLab — Inicio">
                    <img src="{{ asset('images/umg-logo.png') }}" width="56" height="56" alt="Escudo de la Universidad Mariano Gálvez" fetchpriority="high">
                    <span><strong>MemoryLab<span class="ml-brand-dot">.</span></strong><small>{{ $academic['degree'] }}</small></span>
                </a>
                <nav class="ml-site-nav" aria-label="Navegación de la portada">
                    <a href="#conceptos">Qué aprenderás</a>
                    <a href="#como-funciona">Cómo empezar</a>
                    <a href="#equipo">Integrantes</a>
                </nav>
                @auth
                    <a class="ml-header-access" href="{{ route('dashboard') }}">Ir al panel <x-home-icon name="arrow" /></a>
                @else
                    <a class="ml-header-access" href="{{ route('login') }}">Iniciar sesión <x-home-icon name="arrow" /></a>
                @endauth
            </div>
        </header>

        <section id="explora" class="ml-hero" aria-labelledby="ml-hero-title">
            <div class="ml-container ml-hero-grid">
                <div class="ml-hero-copy">
                    <p class="ml-eyebrow"><span></span>{{ $academic['course'] }} · {{ $academic['group'] }}</p>
                    <h1 id="ml-hero-title">La memoria,<br><span>paso a paso.</span></h1>
                    <p class="ml-hero-description">Entiende cómo un sistema operativo organiza la memoria. Explora páginas, marcos y segmentos en un laboratorio que puedes ver y usar.</p>
                    <div class="ml-hero-actions">
                        <a class="ml-button ml-button-primary" href="{{ auth()->check() ? route('dashboard') : route('login') }}">Entrar al laboratorio <x-home-icon name="arrow" /></a>
                        <a class="ml-button ml-button-secondary" href="#conceptos">Conocer el proyecto</a>
                    </div>
                    <div class="ml-hero-caption"><x-home-icon name="book" />Un proyecto de {{ $academic['university'] }}</div>
                </div>

                <figure class="ml-memory-preview">
                    <div class="ml-preview-topbar"><span class="ml-preview-window-dots" aria-hidden="true"><i></i><i></i><i></i></span><span>MEMORYLAB / PAGINACIÓN</span><span class="ml-preview-live">Ejemplo visual</span></div>
                    <div class="ml-preview-body">
                        <div class="ml-preview-heading"><div><p>MEMORIA PRINCIPAL</p><h2>Un vistazo a la RAM</h2></div><span class="ml-preview-chip"><i class="bx bx-chip" aria-hidden="true"></i></span></div>
                        <div class="ml-preview-stats"><span><strong>8</strong> marcos</span><span><strong>4</strong> ocupados</span><span><strong>4</strong> libres</span></div>
                        <div class="ml-frame-grid" aria-label="Ejemplo de ocho marcos: cuatro ocupados por el proceso Editor y cuatro libres">
                            @foreach ([0 => 0, 1 => null, 2 => 1, 3 => null, 4 => null, 5 => 2, 6 => 3, 7 => null] as $frame => $page)
                                <div @class(['ml-frame', 'ml-frame-occupied' => $page !== null])>
                                    <span>Marco {{ $frame }}</span>
                                    @if ($page !== null)
                                        <strong>P{{ $page }}</strong><small>Editor</small>
                                    @else
                                        <strong class="ml-frame-free">—</strong><small>Libre</small>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <div class="ml-preview-legend"><span><i class="ml-dot-occupied"></i>Página en RAM</span><span><i class="ml-dot-free"></i>Marco disponible</span></div>
                        <div class="ml-preview-translation"><span class="ml-translation-icon"><x-home-icon name="transfer" /></span><div><small>TABLA DE PÁGINAS</small><p>Página 2 <span aria-hidden="true">→</span> Marco 5</p></div><span class="ml-translation-check"><x-home-icon name="check" /></span></div>
                    </div>
                    <figcaption>Cada página ocupa un marco. La tabla indica dónde encontrarla.</figcaption>
                </figure>
            </div>
        </section>

        <section id="conceptos" class="ml-section ml-concepts" aria-labelledby="ml-concepts-title">
            <div class="ml-container">
                <div class="ml-section-heading"><div><p class="ml-eyebrow">APRENDE EXPERIMENTANDO</p><h2 id="ml-concepts-title">De la teoría al mapa de memoria.</h2></div><p>Tres ideas para comprender lo que ocurre<br class="ml-desktop-break"> cuando un programa necesita memoria.</p></div>
                <div class="ml-concept-grid">
                    <article class="ml-concept-card">
                        <div class="ml-concept-icon"><span class="ml-concept-glyph"><i class="bx bx-grid-alt" aria-hidden="true"></i></span><span>01</span></div>
                        <h3>Paginación</h3>
                        <p>Divide un proceso en páginas del mismo tamaño. Cada página puede ocupar un marco de RAM; la tabla de páginas guarda esa ubicación.</p>
                        <div class="ml-pages-diagram" aria-label="Páginas de igual tamaño"><span>P0</span><span>P1</span><span>P2</span><small>Partes del mismo tamaño</small></div>
                    </article>
                    <article class="ml-concept-card">
                        <div class="ml-concept-icon ml-concept-icon-red"><span class="ml-concept-glyph"><x-home-icon name="layers" /></span><span>02</span></div>
                        <h3>Segmentación</h3>
                        <p>Organiza un proceso en partes de distinto tamaño: código, datos o pila. Cada segmento tiene una base donde empieza y un límite de acceso.</p>
                        <div class="ml-segments-diagram" aria-label="Segmentos de distintos tamaños"><span>Código</span><span>Datos</span><span>Pila</span><small>Partes con una función</small></div>
                    </article>
                    <article class="ml-concept-card">
                        <div class="ml-concept-icon"><span class="ml-concept-glyph"><x-home-icon name="map" /></span><span>03</span></div>
                        <h3>Traducción de direcciones</h3>
                        <p>Relaciona la dirección que utiliza un proceso con su ubicación en la RAM. Observa cómo la tabla permite encontrar el dato solicitado.</p>
                        <div class="ml-address-diagram" aria-label="De la dirección lógica a la dirección física"><span>Lógica</span><x-home-icon name="arrow" /><span>Física</span><small>Del proceso a la RAM</small></div>
                    </article>
                </div>
                <p class="ml-educational-note"><x-home-icon name="info" />La memoria de este laboratorio es simulada: te ayuda a estudiar sin modificar la RAM de tu equipo.</p>
            </div>
        </section>

        <section id="como-funciona" class="ml-how-section" aria-labelledby="ml-how-title">
            <div class="ml-container ml-how-grid">
                <div><p class="ml-eyebrow">TU PRIMER RECORRIDO</p><h2 id="ml-how-title">Observa.<br>Prueba.<br>Comprende.</h2><p>No necesitas memorizarlo todo antes de empezar. Relaciona cada explicación con lo que muestra el simulador.</p></div>
                <ol class="ml-steps">
                    <li><span>01</span><div><h3>Entra al laboratorio</h3><p>Inicia sesión para acceder al panel y a los módulos de memoria.</p></div></li>
                    <li><span>02</span><div><h3>Elige qué explorar</h3><p>Comienza con paginación o descubre cómo funcionan los segmentos y sus direcciones.</p></div></li>
                    <li><span>03</span><div><h3>Sigue lo que ocurre</h3><p>Relaciona la tabla, el mapa de RAM y el resultado de cada acceso. Avanza a tu ritmo.</p></div></li>
                </ol>
            </div>
        </section>

        <section id="equipo" class="ml-section ml-team-section" aria-labelledby="ml-team-title">
            <div class="ml-container">
                <div class="ml-section-heading"><div><p class="ml-eyebrow">{{ $academic['degree'] }} · {{ $academic['group'] }}</p><h2 id="ml-team-title">El equipo detrás de MemoryLab.</h2></div><p>{{ $academic['university'] }}<br>{{ $academic['course'] }}</p></div>
                <div class="ml-team-grid">
                    @foreach ($academic['members'] as $member)
                        <article class="ml-team-member"><span class="ml-member-number" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><div><h3>{{ $member['name'] }}</h3><p>Carné <span>{{ $member['carnet'] }}</span></p></div></article>
                    @endforeach
                </div>
                <div class="ml-closing">
                    <div><h2>Ahora, míralo en acción.</h2><p>Un acceso a memoria puede explicar más que una fórmula.</p></div>
                    <div class="ml-closing-actions">
                        <a class="ml-button ml-button-primary" href="{{ auth()->check() ? route('dashboard') : route('login') }}">Abrir MemoryLab <x-home-icon name="arrow" /></a>
                        @guest
                            @if (Route::has('register'))<a class="ml-create-account" href="{{ route('register') }}">Crear una cuenta</a>@endif
                        @endguest
                    </div>
                </div>
            </div>
        </section>
    </div>
</x-guest-layout>
