<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="layout-menu-fixed layout-compact" dir="ltr" data-skin="default" data-bs-theme="light" data-template="vertical-menu-template">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@isset($title){{ $title }} · @endisset{{ config('app.name') }}</title>

        <!-- Scripts -->
        @vite(['resources/scss/app.scss', 'resources/js/app.js'])

        <!-- Styles -->
        @livewireStyles
    </head>
    <body>
        <a class="visually-hidden-focusable position-absolute top-0 start-0 m-3 btn btn-primary memorylab-skip-link" href="#contenido-principal">Ir al contenido</a>
        <x-banner />

        {{-- Structure adapted from Sneat's contentNavbarLayout.blade.php. --}}
        <div class="layout-wrapper layout-content-navbar">
            <div class="layout-container">
                @include('layouts.partials.sneat-menu')
                <div class="layout-page">
                    @include('layouts.partials.sneat-navbar')
                    <div class="content-wrapper">
                        <main id="contenido-principal" class="container-xxl flex-grow-1 container-p-y" tabindex="-1">
                            @if (isset($header))
                                <header class="mb-4">{{ $header }}</header>
                            @endif
                            {{ $slot }}
                        </main>
                        @include('layouts.partials.sneat-footer')
                        <div class="content-backdrop fade"></div>
                    </div>
                </div>
            </div>
            <div class="layout-overlay layout-menu-toggle" aria-hidden="true"></div>
        </div>

        @stack('modals')

        <x-livewire-config />
    </body>
</html>
