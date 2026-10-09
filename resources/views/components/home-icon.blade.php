@props(['name'])
<svg {{ $attributes->class(['ml-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('arrow')<path d="M5 12h14m-6-6 6 6-6 6" />@break
        @case('book')<path d="M12 5v15m0-15C8 2 4 3 2 4v15c3-1 6-1 10 1 4-2 7-2 10-1V4c-2-1-6-2-10 1Z" />@break
        @case('transfer')<path d="M4 8h16l-4-4m4 12H4l4 4" />@break
        @case('check')<path d="m5 12 4 4L19 6" />@break
        @case('layers')<path d="m12 3 10 5-10 5L2 8Zm-9 9 9 5 9-5M3 16l9 5 9-5" />@break
        @case('map')<path d="m3 5 6-2 6 2 6-2v16l-6 2-6-2-6 2Zm6-2v16m6-14v16" />@break
        @case('info')<circle cx="12" cy="12" r="9" /><path d="M12 11v6m0-10h.01" />@break
    @endswitch
</svg>
