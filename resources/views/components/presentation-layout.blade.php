<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Presentación · {{ config('app.name') }}</title>
        @vite(['resources/scss/app.scss', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="memorylab-presentation">
        <main class="container-fluid px-3 px-lg-4 py-3" id="contenido-principal">
            <header class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                <div><h1 class="h3 mb-1">{{ config('app.name') }}</h1><p class="small mb-0">{{ config('memorylab.academic.university') }} · {{ config('memorylab.academic.degree') }} · {{ config('memorylab.academic.group') }}</p></div>
                <div class="d-flex flex-wrap gap-2" x-data="{ fullScreen: false }" @fullscreenchange.window="fullScreen = !!document.fullscreenElement">
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('dashboard') }}">Inicio</a>
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('demo.index') }}">Demostraciones</a>
                    <button class="btn btn-sm btn-primary" type="button" @click="(document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen()).catch(() => {})" x-text="fullScreen ? 'Salir de pantalla completa' : 'Pantalla completa'">Pantalla completa</button>
                </div>
            </header>
            {{ $slot }}
            <footer class="small text-body-secondary d-flex flex-wrap justify-content-between gap-2 mt-3">
                <span>{{ config('app.name') }} · {{ config('memorylab.academic.group') }}</span>
                <span>{{ config('memorylab.academic.university') }} · {{ config('memorylab.academic.degree') }}</span>
            </footer>
        </main>
        <x-livewire-config />
    </body>
</html>
