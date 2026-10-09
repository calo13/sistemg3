<x-guest-layout>
    <x-slot name="title">Recuperar contraseña</x-slot>
    <x-authentication-card title="¿Olvidaste tu contraseña?" description="Ingresa tu correo y te enviaremos un enlace para restablecerla.">
        <x-slot name="logo"><x-authentication-card-logo /></x-slot>
        <x-validation-errors class="mb-4" />
        @session('status')
            <div class="alert alert-success" role="status">{{ $value }}</div>
        @endsession
        <form method="POST" action="{{ route('password.email') }}" class="mb-4">
            @csrf
            <div class="mb-4">
                <x-label for="email" value="Correo electrónico" />
                <x-input id="email" type="email" name="email" :value="old('email')" placeholder="nombre@ejemplo.com" required autofocus autocomplete="username" />
            </div>
            <x-button class="w-100">Enviar enlace de recuperación</x-button>
        </form>
        <p class="text-center mb-0"><a href="{{ route('login') }}"><i class="icon-base bx bx-chevron-left" aria-hidden="true"></i> Volver al inicio de sesión</a></p>
    </x-authentication-card>
</x-guest-layout>
