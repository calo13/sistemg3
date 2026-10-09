<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class MemoryComparisonService
{
    public const RAM_SIZE_BYTES = 16384;

    public const PAGE_SIZE_BYTES = 1024;

    /** @return array<string, mixed> */
    public function compare(int $requestedBytes): array
    {
        if ($requestedBytes < 1 || $requestedBytes > self::RAM_SIZE_BYTES) {
            throw ValidationException::withMessages([
                'requested_bytes' => 'El tamaño solicitado debe estar entre 1 y 16384 bytes.',
            ]);
        }

        return [
            'ram_size_bytes' => self::RAM_SIZE_BYTES,
            'page_size_bytes' => self::PAGE_SIZE_BYTES,
            'requested_bytes' => $requestedBytes,
            'initial_free_bytes' => 9216,
            'initial_largest_hole_bytes' => 6144,
            'modes' => [
                'contiguous' => $this->contiguous($requestedBytes),
                'paging' => $this->paging($requestedBytes),
                'segmentation' => $this->segmentation($requestedBytes),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function contiguous(int $requestedBytes): array
    {
        $holes = $this->initialHoles();
        $base = $this->firstFit($holes, $requestedBytes);
        $accepted = $base !== null;
        $allocations = $accepted ? [['base' => $base, 'size_bytes' => $requestedBytes, 'label' => 'Nuevo proceso']] : [];
        $note = $accepted
            ? 'First fit ubica el proceso completo en el primer hueco suficientemente grande; necesita un único intervalo continuo.'
            : ($requestedBytes <= 9216
                ? 'Hay suficientes bytes libres en total, pero ningún hueco continuo contiene el proceso completo: fragmentación externa.'
                : 'El proceso supera los bytes libres totales del escenario, además de no caber en un único hueco.');

        return $this->result($accepted, $allocations, $requestedBytes, $note);
    }

    /** @return array<string, mixed> */
    private function paging(int $requestedBytes): array
    {
        $pageCount = intdiv($requestedBytes + self::PAGE_SIZE_BYTES - 1, self::PAGE_SIZE_BYTES);
        $freeFrames = [4, 5, 6, 10, 11, 12, 13, 14, 15];
        if ($pageCount > count($freeFrames)) {
            return $this->result(false, [], $requestedBytes,
                'La carga completa requiere más marcos de los disponibles. Este comparador exige que todas las páginas de la solicitud quepan en RAM.');
        }

        $allocations = [];
        for ($page = 0; $page < $pageCount; $page++) {
            $allocations[] = [
                'base' => $freeFrames[$page] * self::PAGE_SIZE_BYTES,
                'size_bytes' => self::PAGE_SIZE_BYTES,
                'label' => "Página {$page}",
            ];
        }

        return $this->result(true, $allocations, $requestedBytes,
            'Cada página usa un marco de 1024 bytes, tomando los libres en orden ascendente. El proceso puede ocupar marcos separados; el espacio no utilizado de la última página es fragmentación interna.');
    }

    /** @return array<string, mixed> */
    private function segmentation(int $requestedBytes): array
    {
        $dataBytes = min(3072, intdiv($requestedBytes * 3, 7));
        $units = [
            ['label' => 'Código', 'size_bytes' => $requestedBytes - $dataBytes],
            ['label' => 'Datos', 'size_bytes' => $dataBytes],
        ];
        $holes = $this->initialHoles();
        $allocations = [];
        foreach ($units as $unit) {
            if ($unit['size_bytes'] === 0) {
                continue;
            }
            $base = $this->firstFit($holes, $unit['size_bytes']);
            if ($base === null) {
                return $this->result(false, [], $requestedBytes,
                    'Al menos un segmento no cabe en un hueco continuo disponible. Se descarta toda la asignación de esta solicitud.');
            }
            $allocations[] = ['base' => $base, 'size_bytes' => $unit['size_bytes'], 'label' => $unit['label']];
        }

        $note = $dataBytes === 0
            ? 'La partición ilustrativa Datos tiene tamaño cero y se omite; Código ocupa exactamente los bytes solicitados.'
            : 'La partición ilustrativa asigna Código y después Datos con first fit. Cada segmento es continuo, pero ambos pueden estar separados. Se reservan sus tamaños exactos; otros tamaños de segmentos pueden sufrir fragmentación externa.';

        return $this->result(true, $allocations, $requestedBytes, $note);
    }

    /**
     * @param  list<array{base:int,size_bytes:int}>  $holes
     */
    private function firstFit(array &$holes, int $sizeBytes): ?int
    {
        foreach ($holes as $index => $hole) {
            if ($hole['size_bytes'] < $sizeBytes) {
                continue;
            }
            $base = $hole['base'];
            $holes[$index] = ['base' => $base + $sizeBytes, 'size_bytes' => $hole['size_bytes'] - $sizeBytes];
            if ($holes[$index]['size_bytes'] === 0) {
                array_splice($holes, $index, 1);
            }

            return $base;
        }

        return null;
    }

    /** @return list<array{base:int,size_bytes:int}> */
    private function initialHoles(): array
    {
        return [['base' => 4096, 'size_bytes' => 3072], ['base' => 10240, 'size_bytes' => 6144]];
    }

    /**
     * @param  list<array{base:int,size_bytes:int,label:string}>  $allocations
     * @return array<string, mixed>
     */
    private function result(bool $accepted, array $allocations, int $requestedBytes, string $note): array
    {
        $reservedBytes = array_sum(array_column($allocations, 'size_bytes'));
        $layout = [
            ['base' => 0, 'size_bytes' => 4096, 'label' => 'A', 'state' => 'EXISTING'],
            ['base' => 7168, 'size_bytes' => 3072, 'label' => 'B', 'state' => 'EXISTING'],
        ];
        foreach ($allocations as $allocation) {
            $layout[] = $allocation + ['state' => 'ALLOCATED'];
        }
        usort($layout, fn (array $left, array $right): int => $left['base'] <=> $right['base']);

        $intervals = [];
        $cursor = 0;
        foreach ($layout as $interval) {
            if ($cursor < $interval['base']) {
                $intervals[] = ['base' => $cursor, 'size_bytes' => $interval['base'] - $cursor, 'label' => 'Libre', 'state' => 'FREE'];
            }
            $intervals[] = $interval;
            $cursor = $interval['base'] + $interval['size_bytes'];
        }
        if ($cursor < self::RAM_SIZE_BYTES) {
            $intervals[] = ['base' => $cursor, 'size_bytes' => self::RAM_SIZE_BYTES - $cursor, 'label' => 'Libre', 'state' => 'FREE'];
        }
        $freeBytes = 0;
        $largestHole = 0;
        foreach ($intervals as $interval) {
            if ($interval['state'] === 'FREE') {
                $freeBytes += $interval['size_bytes'];
                $largestHole = max($largestHole, $interval['size_bytes']);
            }
        }

        return [
            'layout' => $intervals,
            'accepted' => $accepted,
            'reserved_bytes' => $reservedBytes,
            'internal_waste_bytes' => $accepted ? $reservedBytes - $requestedBytes : 0,
            'remaining_free_bytes' => $freeBytes,
            'largest_hole_bytes' => $largestHole,
            'note' => $note,
            'allocations' => $allocations,
        ];
    }
}
