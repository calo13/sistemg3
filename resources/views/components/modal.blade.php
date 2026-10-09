@props(['id', 'maxWidth'])

@php
$id = $id ?? md5($attributes->wire('model'));

$maxWidth = [
    'sm' => 'modal-sm',
    'md' => '',
    'lg' => 'modal-lg',
    'xl' => 'modal-xl',
    '2xl' => 'modal-lg',
][$maxWidth ?? '2xl'];
@endphp

<div
    x-data="{ show: @entangle($attributes->wire('model')) }"
    x-on:close.stop="show = false"
    x-on:keydown.escape.window="show = false"
    x-show="show"
    id="{{ $id }}"
    class="jetstream-modal modal show memorylab-modal"
    style="display: none;"
    role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title"
>
    <div class="position-fixed top-0 start-0 w-100 h-100 bg-dark opacity-50" x-on:click="show = false" aria-hidden="true"></div>
    <div class="modal-dialog modal-dialog-centered position-relative {{ $maxWidth }}">
        <div class="modal-content" x-trap.inert.noscroll="show">
            {{ $slot }}
        </div>
    </div>
</div>
