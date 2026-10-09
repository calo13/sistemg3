<?php

namespace App\Services;

use App\Enums\PermissionName;
use App\Enums\SimulationEventType;
use App\Enums\SimulationMode;
use App\Models\Process;
use App\Models\Scenario;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EducationalTerminalService
{
    private const MAX_COMMAND_LENGTH = 160;

    private const MAX_OUTPUT_LINES = 100;

    private const MAX_TABLE_ROWS = 40;

    /** @return array{command:string,lines:list<string>,mutated:bool,scenario_id:int} */
    public function execute(User $actor, int $scenarioId, string $command): array
    {
        $command = $this->validatedCommand($command);
        $parsed = $this->parse($command);
        $currentActor = User::findOrFail($actor->getKey());
        foreach ([
            PermissionName::ViewMemory,
            PermissionName::ViewTables,
            PermissionName::ViewSimulations,
            PermissionName::ViewResults,
        ] as $permission) {
            Gate::forUser($currentActor)->authorize($permission->value);
        }

        try {
            $snapshot = $this->snapshot($currentActor, $scenarioId);
            $operation = $parsed['operation'];
            if (in_array($operation, ['page-table', 'request'], true)
                && $snapshot['scenario']->mode !== SimulationMode::Paging) {
                $this->invalidCommand('Los comandos page table y request requieren un escenario de paginación.');
            }

            $lines = match ($operation) {
                'help' => $this->helpLines(),
                'memory-status' => $this->memoryLines($snapshot),
                'process-list' => $this->processLines($snapshot),
                'page-table' => $this->pageTableLines(
                    $currentActor,
                    $scenarioId,
                    $this->resolveProcess($snapshot['processes'], $parsed['selector']),
                ),
                'request' => $this->requestLines(
                    $currentActor,
                    $scenarioId,
                    $this->resolveProcess($snapshot['processes'], $parsed['selector']),
                    $parsed['page_number'],
                ),
                'reset' => $this->resetLines($currentActor, $scenarioId),
            };

            return [
                'command' => $command,
                'lines' => $this->boundedLines($lines),
                'mutated' => in_array($operation, ['request', 'reset'], true),
                'scenario_id' => $scenarioId,
            ];
        } catch (ValidationException $exception) {
            $messages = [];
            foreach ($exception->errors() as $errors) {
                foreach ($errors as $message) {
                    $messages[] = $message;
                }
            }
            throw ValidationException::withMessages(['command' => $messages]);
        }
    }

    private function validatedCommand(string $command): string
    {
        if (! mb_check_encoding($command, 'UTF-8')
            || preg_match('/[\x00-\x1F\x7F]/', $command) === 1) {
            $this->invalidCommand('Escribe un solo comando sin saltos de línea ni caracteres de control.');
        }
        if (mb_strlen($command) > self::MAX_COMMAND_LENGTH) {
            $this->invalidCommand('El comando es obligatorio y no puede superar 160 caracteres.');
        }
        $command = trim($command);
        if ($command === '') {
            $this->invalidCommand('El comando es obligatorio y no puede superar 160 caracteres.');
        }

        return $command;
    }

    /** @return array{operation:string,selector?:string,page_number?:int} */
    private function parse(string $command): array
    {
        foreach ([
            'help' => '/\Ahelp\z/i',
            'memory-status' => '/\Amemory[ ]+status\z/i',
            'process-list' => '/\Aprocess[ ]+list\z/i',
            'reset' => '/\Areset\z/i',
        ] as $operation => $pattern) {
            if (preg_match($pattern, $command) === 1) {
                return ['operation' => $operation];
            }
        }
        if (preg_match('/\Apage[ ]+table[ ]+(.+)\z/i', $command, $matches) === 1) {
            return ['operation' => 'page-table', 'selector' => trim($matches[1])];
        }
        if (preg_match('/\Arequest[ ]+(.+)[ ]+([0-9]+)\z/i', $command, $matches) === 1) {
            $pageNumber = ltrim($matches[2], '0');
            if (strlen($pageNumber) > 10
                || (strlen($pageNumber) === 10 && strcmp($pageNumber, '4294967295') > 0)) {
                $this->invalidCommand('El número de página debe ser un entero entre 0 y 4294967295.');
            }

            return [
                'operation' => 'request',
                'selector' => trim($matches[1]),
                'page_number' => $pageNumber === '' ? 0 : (int) $pageNumber,
            ];
        }

        $this->invalidCommand('Comando no reconocido. Escribe help para consultar los comandos disponibles.');
    }

    /** @return array<string,mixed> */
    private function snapshot(User $actor, int $scenarioId): array
    {
        $scenario = Scenario::query()->select(['id', 'mode'])->findOrFail($scenarioId);

        return match ($scenario->mode) {
            SimulationMode::Paging => app(PagingService::class)->snapshot($actor, $scenarioId),
            SimulationMode::Segmentation => app(SegmentationService::class)->snapshot($actor, $scenarioId),
            default => throw ValidationException::withMessages([
                'command' => 'Selecciona un escenario válido de paginación o segmentación.',
            ]),
        };
    }

    /** @param Collection<int,Process> $processes */
    private function resolveProcess(Collection $processes, string $selector): Process
    {
        if (str_starts_with($selector, '#')) {
            if (preg_match('/\A#([0-9]+)\z/', $selector, $matches) !== 1) {
                $this->invalidCommand('Identifica el proceso por su nombre exacto o por #ID.');
            }
            $id = ltrim($matches[1], '0');
            $maxId = (string) PHP_INT_MAX;
            if ($id === '' || strlen($id) > strlen($maxId)
                || (strlen($id) === strlen($maxId) && strcmp($id, $maxId) > 0)) {
                $this->invalidCommand('El identificador del proceso no es válido.');
            }
            $process = $processes->firstWhere('id', (int) $id);
            if ($process === null) {
                $this->invalidCommand('No se encontró ese proceso en el escenario seleccionado.');
            }

            return $process;
        }

        $name = mb_strtolower(trim($selector), 'UTF-8');
        $matches = $processes->filter(
            fn (Process $process): bool => mb_strtolower(trim($process->name), 'UTF-8') === $name,
        );
        if ($matches->count() > 1) {
            $this->invalidCommand('Hay varios procesos con ese nombre. Utiliza #ID para elegir uno.');
        }
        if ($matches->isEmpty()) {
            $this->invalidCommand('No se encontró ese proceso en el escenario seleccionado.');
        }

        return $matches->first();
    }

    /** @return list<string> */
    private function helpLines(): array
    {
        return [
            'Comandos de la terminal educativa:',
            'help — Mostrar esta ayuda.',
            'memory status — Consultar RAM y almacenamiento secundario simulado.',
            'process list — Listar los procesos del escenario seleccionado.',
            'page table {nombre o #ID} — Mostrar la tabla de páginas (paginación).',
            'request {nombre o #ID} {página} — Ejecutar un acceso real en el simulador de paginación.',
            'reset — Reiniciar la memoria simulada con los permisos correspondientes.',
            'Los nombres pueden contener espacios. Ejemplo: request VS Code 0.',
        ];
    }

    /** @param array<string,mixed> $snapshot
     * @return list<string>
     */
    private function memoryLines(array $snapshot): array
    {
        $scenario = $snapshot['scenario'];
        $ram = $snapshot['ram'];
        $mode = $scenario->mode === SimulationMode::Paging ? 'Paginación' : 'Segmentación';
        $lines = [
            "Escenario #{$scenario->getKey()}: {$scenario->name} ({$mode}, {$scenario->status->value}).",
            "RAM: {$ram['used_bytes']} de {$ram['total_bytes']} bytes ocupados; {$ram['available_bytes']} bytes disponibles.",
            'Procesos: '.$snapshot['processes']->count().'.',
        ];
        if ($scenario->mode === SimulationMode::Paging) {
            $secondary = $snapshot['secondary'];
            $lines[] = "Marcos: {$ram['frames_used']} ocupados y {$ram['frames_free']} libres de {$ram['frames_total']}.";
            $lines[] = "Tamaño de página: {$snapshot['configuration']->page_size_bytes} bytes.";
            $lines[] = "Almacenamiento secundario simulado: {$secondary['used_bytes']} de {$secondary['total_bytes']} bytes ocupados; {$secondary['available_bytes']} bytes disponibles.";
        } else {
            $lines[] = 'Segmentos activos: '.$snapshot['all_segments']->count().'.';
        }

        return $lines;
    }

    /** @param array<string,mixed> $snapshot
     * @return list<string>
     */
    private function processLines(array $snapshot): array
    {
        if ($snapshot['processes']->isEmpty()) {
            return ['No hay procesos en el escenario seleccionado.'];
        }
        $lines = [];
        foreach ($snapshot['processes'] as $process) {
            $counts = $snapshot['scenario']->mode === SimulationMode::Paging
                ? "{$process->pages_count} páginas, {$process->resident_pages_count} en RAM"
                : "{$process->segments_count} segmentos, {$process->active_segments_count} activos";
            $lines[] = "#{$process->getKey()} {$process->name} | {$process->size_bytes} bytes | {$process->status->label()} | {$counts}.";
        }

        return $lines;
    }

    /** @return list<string> */
    private function pageTableLines(User $actor, int $scenarioId, Process $process): array
    {
        $snapshot = app(PagingService::class)->snapshot($actor, $scenarioId, $process->getKey());
        $lines = ["Tabla de páginas de #{$process->getKey()} {$process->name}:"];
        if ($snapshot['pages']->isEmpty()) {
            $lines[] = 'El proceso no tiene páginas asignadas.';
        }
        foreach ($snapshot['pages']->take(self::MAX_TABLE_ROWS) as $page) {
            $frame = $page->frame?->frame_number;
            $location = $page->present ? 'RAM' : 'Almacenamiento secundario simulado';
            $present = $page->present ? 'Sí' : 'No';
            $lines[] = "Página {$page->page_number} | Marco ".($frame ?? '—')." | Presente: {$present} | {$location}.";
        }
        $remaining = $snapshot['pages']->count() - self::MAX_TABLE_ROWS;
        if ($remaining > 0) {
            $lines[] = "Se muestran las primeras 40 páginas; quedan {$remaining} filas por consultar en la tabla del simulador.";
        }

        return $lines;
    }

    /** @return list<string> */
    private function requestLines(User $actor, int $scenarioId, Process $process, int $pageNumber): array
    {
        $result = app(PagingService::class)->requestPage($actor, $scenarioId, $process->getKey(), $pageNumber);
        $hit = $result['outcome'] === SimulationEventType::PageHit->value;
        $lines = ["Solicitud de #{$process->getKey()} {$process->name}, página {$result['page_number']}:"];
        foreach (app(PagingFlowService::class)->steps($hit) as $index => $step) {
            $lines[] = ($index + 1).". {$step['label']}: {$step['detail']}";
        }
        $lines[] = "Resultado: {$result['outcome']}. Marco {$result['frame_number']}; dirección física inicial {$result['physical_address']} bytes.";
        if ($result['evicted'] !== null) {
            $victim = $result['evicted'];
            $lines[] = "FIFO: página {$victim['page_number']} de #{$victim['process_id']} {$victim['process_name']} retirada del marco {$victim['frame_number']} a almacenamiento secundario simulado.";
        }

        return $lines;
    }

    /** @return list<string> */
    private function resetLines(User $actor, int $scenarioId): array
    {
        $result = app(SimulationService::class)->reset($actor, $scenarioId);

        return [
            "Memoria del escenario #{$result['scenario_id']} reiniciada.",
            "Procesos finalizados: {$result['terminated_processes']}.",
            "Páginas liberadas: {$result['released_pages']}.",
            "Segmentos liberados: {$result['released_segments']}.",
        ];
    }

    /** @param list<string> $lines
     * @return list<string>
     */
    private function boundedLines(array $lines): array
    {
        if (count($lines) > self::MAX_OUTPUT_LINES) {
            $lines = array_slice($lines, 0, self::MAX_OUTPUT_LINES - 1);
            $lines[] = 'Salida limitada a 100 líneas. Consulta los detalles en el simulador.';
        }

        return array_values(array_map(
            fn (string $line): string => preg_replace('/[\x00-\x1F\x7F]/', ' ', $line),
            $lines,
        ));
    }

    private function invalidCommand(string $message): never
    {
        throw ValidationException::withMessages(['command' => $message]);
    }
}
