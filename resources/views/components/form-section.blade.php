@props(['submit'])

<div {{ $attributes->merge(['class' => 'row g-4']) }}>
    <x-section-title>
        <x-slot name="title">{{ $title }}</x-slot>
        <x-slot name="description">{{ $description }}</x-slot>
    </x-section-title>

    <div class="col-lg-8">
        <form wire:submit="{{ $submit }}" class="card">
            <div class="card-body">
                <div class="d-grid gap-4">
                    {{ $form }}
                </div>
            </div>

            @if (isset($actions))
                <div class="card-footer d-flex flex-wrap align-items-center justify-content-end gap-2">
                    {{ $actions }}
                </div>
            @endif
        </form>
    </div>
</div>
