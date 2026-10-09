<x-guest-layout>
    <x-slot name="title">Confirmar contraseña</x-slot>
    <x-authentication-card title="Confirma tu contraseña" description="Por seguridad, vuelve a ingresar tu contraseña para continuar.">
        <x-slot name="logo"><x-authentication-card-logo /></x-slot>
        <x-validation-errors class="mb-4" />
        <form method="POST" action="{{ route('password.confirm') }}">
            @csrf
            <div class="mb-4">
                <x-label for="password" value="Contraseña" />
                <x-password-input id="password" name="password" autofocus />
            </div>
            <x-button class="w-100">Confirmar contraseña</x-button>
        </form>
    </x-authentication-card>
</x-guest-layout>
