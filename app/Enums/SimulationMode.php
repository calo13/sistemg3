<?php

namespace App\Enums;

enum SimulationMode: string
{
    case Paging = 'PAGING';
    case Segmentation = 'SEGMENTATION';
    case Contiguous = 'CONTIGUOUS';
}
