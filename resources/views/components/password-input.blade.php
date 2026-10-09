@props(['id', 'name', 'autocomplete' => 'current-password'])

<div class="input-group input-group-merge" x-data="{ visible: false }">
    <input id="{{ $id }}" name="{{ $name }}" type="password" autocomplete="{{ $autocomplete }}"
        x-bind:type="visible ? 'text' : 'password'"
        {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}
        aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}" required>
    <button type="button" class="input-group-text" x-on:click="visible = !visible"
        aria-label="Mostrar contraseña" x-bind:aria-label="visible ? 'Ocultar contraseña' : 'Mostrar contraseña'"
        aria-controls="{{ $id }}" aria-pressed="false" x-bind:aria-pressed="visible">
        <i class="icon-base bx" x-bind:class="visible ? 'bx-show' : 'bx-hide'" aria-hidden="true"></i>
    </button>
</div>
