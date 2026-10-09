<x-app-layout>
    <x-slot name="title">Demanda de memoria</x-slot>
    <x-slot name="header">
        <h1 class="h3 mb-1">Simular alta demanda</h1>
        <p class="text-body-secondary mb-0">Procesos ficticios, ocupación de RAM y reemplazo FIFO.</p>
    </x-slot>
    @livewire('memory-stress')
</x-app-layout>
