<x-guest-layout>
    <x-slot name="title">Acceso restringido</x-slot>
    <x-authentication-card title="Acceso restringido" description="Tu cuenta no tiene permiso para realizar esta acción.">
        <x-slot name="logo"><x-authentication-card-logo /></x-slot>
        @auth
            <a class="btn btn-primary w-100" href="{{ route('dashboard') }}">Volver al inicio</a>
        @else
            <a class="btn btn-primary w-100" href="{{ route('login') }}">Iniciar sesión</a>
        @endauth
    </x-authentication-card>
</x-guest-layout>
