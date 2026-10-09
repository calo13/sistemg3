@props(['title' => null, 'description' => null])

{{-- Adapted from Sneat's auth-login/register/forgot-password-basic templates. --}}
<div class="container-xxl flex-grow-1 d-flex">
    <div class="authentication-wrapper authentication-basic container-p-y">
        <div class="authentication-inner">
            <div class="card">
                <div class="card-body">
                    <div class="app-brand justify-content-center">{{ $logo }}</div>
                    @if ($title)
                        <h1 class="h4 mb-2">{{ $title }}</h1>
                    @endif
                    @if ($description)
                        <p class="mb-4">{{ $description }}</p>
                    @endif
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
</div>
