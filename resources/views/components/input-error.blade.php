@props(['for'])

@error($for)
    <p {{ $attributes->merge(['class' => 'text-danger small mb-0']) }} role="alert">{{ $message }}</p>
@enderror
