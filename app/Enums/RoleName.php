<?php

namespace App\Enums;

enum RoleName: string
{
    case Administrator = 'administrador';
    case Operator = 'operador';
    case Observer = 'observador';

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrador',
            self::Operator => 'Operador',
            self::Observer => 'Observador',
        };
    }

    /** @return list<string> */
    public function permissions(): array
    {
        $read = [
            PermissionName::ViewMemory->value,
            PermissionName::ViewTables->value,
            PermissionName::ViewSimulations->value,
            PermissionName::ViewResults->value,
        ];

        return match ($this) {
            self::Administrator => array_column(PermissionName::cases(), 'value'),
            self::Operator => [...$read,
                PermissionName::CreateProcesses->value,
                PermissionName::ExecuteSimulations->value,
                PermissionName::RequestPages->value,
                PermissionName::ExecuteSegmentation->value,
            ],
            self::Observer => $read,
        };
    }
}
