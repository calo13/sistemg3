<?php

namespace App\Enums;

enum ScenarioStatus: string
{
    case Draft = 'DRAFT';
    case Ready = 'READY';
    case Running = 'RUNNING';
    case Completed = 'COMPLETED';
}
