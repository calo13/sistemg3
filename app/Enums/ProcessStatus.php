<?php

namespace App\Enums;

enum ProcessStatus: string
{
    case Ready = 'READY';
    case Running = 'RUNNING';
    case Waiting = 'WAITING';
    case Terminated = 'TERMINATED';

    public function label(): string
    {
        return match ($this) {
            self::Ready => 'Listo',
            self::Running => 'En ejecución',
            self::Waiting => 'En espera',
            self::Terminated => 'Finalizado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Ready => 'info',
            self::Running => 'success',
            self::Waiting => 'warning',
            self::Terminated => 'secondary',
        };
    }
}
