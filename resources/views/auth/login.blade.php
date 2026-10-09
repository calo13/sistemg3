<x-guest-layout>
    <x-slot name="title">Iniciar sesión</x-slot>
    <x-authentication-card title="Bienvenido a MemoryLab" description="Inicia sesión para acceder al simulador de administración de memoria.">
        <x-slot name="logo"><x-authentication-card-logo /></x-slot>
        <x-validation-errors class="mb-4" />
        @session('status')
            <div class="alert alert-success" role="status">{{ $value }}</div>
        @endsession
        <form method="POST" action="{{ route('login') }}" class="mb-4">
            @csrf
            <div class="mb-4">
                <x-label for="email" value="Correo electrónico" />
                <x-input id="email" type="email" name="email" :value="old('email')" placeholder="nombre@ejemplo.com" required autofocus autocomplete="username" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" />
            </div>
            <div class="mb-4">
                <x-label for="password" value="Contraseña" />
                <x-password-input id="password" name="password" />
            </div>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
                <div class="form-check mb-0">
                    <x-checkbox id="remember_me" name="remember" />
                    <label class="form-check-label" for="remember_me">Recordarme</label>
                </div>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
                @endif
            </div>
            <x-button class="w-100">Iniciar sesión</x-button>
        </form>
        @if (Route::has('register'))
            <p class="text-center mb-0">¿Aún no tienes una cuenta? <a href="{{ route('register') }}">Crear cuenta</a></p>
        @endif
    </x-authentication-card>
</x-guest-layout>
