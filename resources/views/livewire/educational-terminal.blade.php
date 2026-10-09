<div>
    <section class="card mb-4">
        <div class="card-body">
            <label class="form-label" for="terminal-scenario">Escenario</label>
            <select id="terminal-scenario" class="form-select @error('scenarioId') is-invalid @enderror" wire:model.live="scenarioId">
                <option value="">Seleccionar escenario</option>
                @foreach ($scenarios as $scenario)<option value="{{ $scenario->id }}">{{ $scenario->name }} · #{{ $scenario->id }}</option>@endforeach
            </select>
            @error('scenarioId')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </section>
    <div class="row g-4">
        <div class="col-lg-8">
            <section class="card h-100">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2"><h2 class="h5 mb-0">Consola de MemoryLab</h2><button class="btn btn-sm btn-outline-secondary" wire:click="clear">Limpiar consola</button></div>
                <div class="card-body">
                    <pre class="bg-dark text-white p-3 rounded text-break" style="white-space: pre-wrap; min-height: 260px; max-height: 500px; overflow: auto" aria-live="polite" data-terminal-output>{{ $lines ? implode("\n", $lines) : 'Selecciona un escenario y escribe help para ver los comandos disponibles.' }}</pre>
                    <form wire:submit="execute">
                        <label class="form-label" for="terminal-command">Comando educativo</label>
                        <input id="terminal-command" class="form-control font-monospace @error('command') is-invalid @enderror" type="text" maxlength="160" wire:model="command" placeholder="memory status" autocomplete="off" spellcheck="false">
                        @error('command')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <button class="btn btn-primary mt-3" type="submit" wire:loading.attr="disabled" @disabled($scenarioId === null || $scenarioId === '')>Ejecutar comando</button>
                    </form>
                </div>
            </section>
        </div>
        <div class="col-lg-4">
            <section class="card h-100">
                <div class="card-header"><h2 class="h5 mb-0">Comandos disponibles</h2></div>
                <div class="card-body">
                    <dl class="small">
                        <dt><code>help</code></dt><dd>Ver instrucciones.</dd>
                        <dt><code>memory status</code></dt><dd>Consultar la ocupación actual.</dd>
                        <dt><code>process list</code></dt><dd>Listar procesos del escenario.</dd>
                        <dt><code>page table chrome</code></dt><dd>Consultar las páginas de Chrome.</dd>
                        @if ($canRequest)<dt><code>request chrome 3</code></dt><dd>Solicitar P3 y mostrar el flujo de acceso.</dd>@endif
                        @if ($canReset)<dt><code>reset</code></dt><dd>Finalizar procesos y liberar asignaciones del escenario seleccionado. Conserva configuración e historial.</dd>@endif
                    </dl>
                    <p class="small mb-0">Puedes usar nombres con espacios o <code>#ID</code> para distinguir procesos con el mismo nombre. Las respuestas muestran el estado al ejecutar cada comando.</p>
                </div>
            </section>
        </div>
    </div>
</div>
