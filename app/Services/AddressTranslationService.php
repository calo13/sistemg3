<?php

namespace App\Services;

use App\Enums\PermissionName;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AddressTranslationService
{
    /** @return array<string, int|bool|null> */
    public function translate(User $actor, int $scenarioId, int $processId, int $logicalAddress): array
    {
        return DB::transaction(function () use ($actor, $scenarioId, $processId, $logicalAddress): array {
            $currentActor = User::findOrFail($actor->getKey());
            Gate::forUser($currentActor)->authorize(PermissionName::ViewResults->value);
            $snapshot = app(PagingService::class)->snapshot($currentActor, $scenarioId, $processId);
            $process = $snapshot['selected_process'];

            if ($logicalAddress < 0 || $logicalAddress >= $process->size_bytes) {
                throw ValidationException::withMessages([
                    'logical_address' => 'La dirección lógica debe estar entre cero y el último byte del proceso; el espacio de relleno de la última página no pertenece al proceso.',
                ]);
            }

            $pageBytes = $snapshot['configuration']->page_size_bytes;
            $pages = $snapshot['pages'];
            $expectedPages = intdiv($process->size_bytes + $pageBytes - 1, $pageBytes);
            if ($pages->count() !== $expectedPages
                || $pages->min('page_number') !== 0
                || $pages->max('page_number') !== $expectedPages - 1) {
                throw ValidationException::withMessages([
                    'process_id' => 'Las páginas del proceso no coinciden con su tamaño y la configuración de memoria.',
                ]);
            }

            $pageNumber = intdiv($logicalAddress, $pageBytes);
            $offset = $logicalAddress % $pageBytes;
            $page = $pages->firstWhere('page_number', $pageNumber);
            if ($page === null) {
                throw ValidationException::withMessages([
                    'process_id' => 'La página correspondiente a la dirección lógica no está registrada en el proceso seleccionado.',
                ]);
            }

            $frameNumber = $page->frame?->frame_number;

            return [
                'scenario_id' => $snapshot['scenario']->getKey(),
                'process_id' => $process->getKey(),
                'logical_address' => $logicalAddress,
                'page_number' => $pageNumber,
                'offset' => $offset,
                'page_size_bytes' => $pageBytes,
                'frame_number' => $frameNumber,
                'present' => $page->present,
                'physical_address' => $frameNumber === null ? null : $frameNumber * $pageBytes + $offset,
                'process_size_bytes' => $process->size_bytes,
            ];
        }, 3);
    }
}
