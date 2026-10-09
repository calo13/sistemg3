@if ($errors->any())
    <div {{ $attributes->merge(['class' => 'alert alert-danger']) }} role="alert">
        <div class="fw-semibold">{{ __('Revisa los siguientes datos:') }}</div>

        <ul class="mt-2 mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
