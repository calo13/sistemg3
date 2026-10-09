@props(['id' => null, 'maxWidth' => null])

<x-modal :id="$id" :maxWidth="$maxWidth" {{ $attributes }}>
    <div class="modal-header">
        <h2 class="modal-title h5" id="{{ $id ?? md5($attributes->wire('model')) }}-title">
            {{ $title }}
        </h2>
    </div>
    <div class="modal-body">{{ $content }}</div>
    <div class="modal-footer">
        {{ $footer }}
    </div>
</x-modal>
