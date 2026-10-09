<x-guest-layout>
    <x-slot name="title">Crear cuenta</x-slot>
    <x-authentication-card title="Crea tu cuenta" description="Explora la administración de memoria con MemoryLab.">
        <x-slot name="logo"><x-authentication-card-logo /></x-slot>
        <x-validation-errors class="mb-4" />
        <form method="POST" action="{{ route('register') }}" class="mb-4">
            @csrf
            <div class="mb-4">
                <x-label for="name" value="Nombre" />
                <x-input id="name" type="text" name="name" :value="old('name')" placeholder="Tu nombre" required autofocus autocomplete="name" />
            </div>
            <div class="mb-4">
                <x-label for="email" value="Correo electrónico" />
                <x-input id="email" type="email" name="email" :value="old('email')" placeholder="nombre@ejemplo.com" required autocomplete="username" />
            </div>
            <div class="mb-4">
                <x-label for="password" value="Contraseña" />
                <x-password-input id="password" name="password" autocomplete="new-password" />
            </div>
            <div class="mb-4">
                <x-label for="password_confirmation" value="Confirmar contraseña" />
                <x-password-input id="password_confirmation" name="password_confirmation" autocomplete="new-password" />
            </div>
            @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
                <div class="form-check mb-4">
                    <x-checkbox name="terms" id="terms" required />
                    <label class="form-check-label" for="terms">
                        Acepto los <a href="{{ route('terms.show') }}" target="_blank" rel="noopener">términos del servicio</a>
                        y la <a href="{{ route('policy.show') }}" target="_blank" rel="noopener">política de privacidad</a>.
                    </label>
                </div>
            @endif
            <x-button class="w-100">Crear cuenta</x-button>
        </form>
        <p class="text-center mb-0">¿Ya tienes una cuenta? <a href="{{ route('login') }}">Iniciar sesión</a></p>
    </x-authentication-card>
</x-guest-layout>
