<?php

namespace App\Services;

use App\Enums\ProcessStatus;
use App\Enums\SegmentStatus;
use App\Enums\SimulationEventType;
use App\Enums\SimulationMode;
use App\Models\Scenario;
use Illuminate\Support\Facades\DB;

class MemoryStatisticsService
{
    /** @return array<string, int|float|null>|null */
    public function forScenario(Scenario $scenario): ?array
    {
        // Read the configuration and its counters from the same database snapshot.
        return DB::transaction(function () use ($scenario): ?array {
            $currentScenario = Scenario::with('configuration')->find($scenario->getKey());
            $configuration = $currentScenario?->configuration;

            if ($currentScenario?->mode === SimulationMode::Segmentation && $configuration !== null) {
                $ramBytes = $configuration->ram_size_bytes;
                if (! is_int($ramBytes) || $ramBytes <= 0
                    || $currentScenario->segments()->count() > SegmentationService::MAX_SEGMENTS_PER_SCENARIO) {
                    return null;
                }

                $usedBytes = 0;
                $cursor = 0;
                $segments = $currentScenario->segments()->where('status', SegmentStatus::Active->value)
                    ->orderBy('base')->orderBy('id')->get(['base', 'size_bytes']);
                foreach ($segments as $segment) {
                    if ($segment->base < $cursor || $segment->size_bytes <= 0
                        || $segment->base > $ramBytes || $segment->size_bytes > $ramBytes - $segment->base) {
                        return null;
                    }
                    $cursor = $segment->base + $segment->size_bytes;
                    $usedBytes += $segment->size_bytes;
                }
                if ($usedBytes > $ramBytes) {
                    return null;
                }

                return [
                    'ram-total' => $ramBytes / 1024,
                    'ram-used' => $usedBytes / 1024,
                    'ram-available' => ($ramBytes - $usedBytes) / 1024,
                    'frames-total' => null,
                    'frames-used' => null,
                    'frames-free' => null,
                    'processes-active' => $currentScenario->processes()
                        ->where('status', '!=', ProcessStatus::Terminated->value)->count(),
                    'page-faults' => null,
                    'utilization' => round($usedBytes / $ramBytes * 100, 1),
                ];
            }

            if ($currentScenario?->mode !== SimulationMode::Paging || $configuration === null
                || $configuration->frame_count === null) {
                return null;
            }

            $totalFrames = $configuration->frame_count;
            $usedFrames = $currentScenario->frames()->whereHas('page')->count();
            $usedBytes = $usedFrames * $configuration->page_size_bytes;

            return [
                'ram-total' => $configuration->ram_size_bytes / 1024,
                'ram-used' => $usedBytes / 1024,
                'ram-available' => ($configuration->ram_size_bytes - $usedBytes) / 1024,
                'frames-total' => $totalFrames,
                'frames-used' => $usedFrames,
                'frames-free' => $totalFrames - $usedFrames,
                'processes-active' => $currentScenario->processes()
                    ->where('status', '!=', ProcessStatus::Terminated->value)->count(),
                'page-faults' => $currentScenario->events()
                    ->where('type', SimulationEventType::PageFault->value)->count(),
                'utilization' => round($usedBytes / $configuration->ram_size_bytes * 100, 1),
            ];
        });
    }
}
