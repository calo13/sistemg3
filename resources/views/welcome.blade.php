<x-guest-layout>
    <section class="container py-5">
        <h1>{{ config('app.name') }}</h1>
        <p>Simulador Interactivo de Administración de Memoria</p>
        <p class="text-body-secondary">Sistemas Operativos 1 — Grupo 3</p>
        <p>La memoria representada por MemoryLab será una simulación educativa.</p>
        <nav class="d-flex flex-wrap gap-2" aria-label="Acceso">
            @auth
                <a class="btn btn-primary" href="{{ route('dashboard') }}">Ir al panel</a>
            @else
                <a class="btn btn-primary" href="{{ route('login') }}">Iniciar sesión</a>
                @if (Route::has('register'))
                    <a class="btn btn-outline-primary" href="{{ route('register') }}">Crear cuenta</a>
                @endif
            @endauth
        </nav>
    </section>
</x-guest-layout>
