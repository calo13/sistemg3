<div {{ $attributes->merge(['class' => 'row g-4']) }}>
    <x-section-title>
        <x-slot name="title">{{ $title }}</x-slot>
        <x-slot name="description">{{ $description }}</x-slot>
    </x-section-title>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">{{ $content }}</div>
        </div>
    </div>
</div>
