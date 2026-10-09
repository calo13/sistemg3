<x-guest-layout>
    <x-slot name="title">Restablecer contraseña</x-slot>
    <x-authentication-card title="Restablece tu contraseña" description="Elige una nueva contraseña y confírmala para recuperar el acceso.">
        <x-slot name="logo"><x-authentication-card-logo /></x-slot>
        <x-validation-errors class="mb-4" />
        <form method="POST" action="{{ route('password.update') }}" class="mb-4">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">
            <div class="mb-4">
                <x-label for="email" value="Correo electrónico" />
                <x-input id="email" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
            </div>
            <div class="mb-4">
                <x-label for="password" value="Nueva contraseña" />
                <x-password-input id="password" name="password" autocomplete="new-password" />
            </div>
            <div class="mb-4">
                <x-label for="password_confirmation" value="Confirmar contraseña" />
                <x-password-input id="password_confirmation" name="password_confirmation" autocomplete="new-password" />
            </div>
            <x-button class="w-100">Restablecer contraseña</x-button>
        </form>
        <p class="text-center mb-0"><a href="{{ route('login') }}">Volver al inicio de sesión</a></p>
    </x-authentication-card>
</x-guest-layout>
