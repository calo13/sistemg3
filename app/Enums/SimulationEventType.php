<?php

namespace App\Enums;

enum SimulationEventType: string
{
    case ProcessCreated = 'PROCESS_CREATED';
    case PageRequest = 'PAGE_REQUEST';
    case PageHit = 'PAGE_HIT';
    case PageFault = 'PAGE_FAULT';
    case PageLoaded = 'PAGE_LOADED';
    case SegmentAccess = 'SEGMENT_ACCESS';
    case SegmentationFault = 'SEGMENTATION_FAULT';
    case MemoryReset = 'MEMORY_RESET';
    case ScenarioCreated = 'SCENARIO_CREATED';
    case MemoryConfigured = 'MEMORY_CONFIGURED';
    case ProcessTerminated = 'PROCESS_TERMINATED';
}
