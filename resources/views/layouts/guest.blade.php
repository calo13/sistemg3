<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr" data-skin="default" data-bs-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@isset($title){{ $title }} · @endisset{{ config('app.name') }}</title>

        <x-favicon />

        <!-- Scripts -->
        @vite(['resources/scss/app.scss', 'resources/js/app.js'])

        <!-- Styles -->
        @livewireStyles
    </head>
    <body class="memorylab-guest d-flex flex-column min-vh-100">
        <main class="flex-grow-1 d-flex flex-column">
            {{ $slot }}
        </main>

        @include('layouts.partials.sneat-footer')

        <x-livewire-config />
    </body>
</html>
