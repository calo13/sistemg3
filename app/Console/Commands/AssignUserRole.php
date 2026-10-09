<?php

namespace App\Console\Commands;

use App\Enums\RoleName;
use App\Models\User;
use App\Services\UserRoleService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class AssignUserRole extends Command
{
    protected $signature = 'memorylab:user-role {email : Correo de una cuenta existente} {role=administrador : administrador, operador u observador}';

    protected $description = 'Asigna un rol a una cuenta existente sin crear usuarios ni contraseñas';

    public function handle(UserRoleService $service): int
    {
        $role = RoleName::tryFrom(strtolower(trim($this->argument('role'))));
        if ($role === null) {
            $this->error('Rol inválido. Utiliza administrador, operador u observador.');

            return self::FAILURE;
        }

        $user = User::where('email', trim($this->argument('email')))->first();
        if ($user === null) {
            $this->error('La cuenta no existe. Regístrala antes de asignar un rol.');

            return self::FAILURE;
        }

        try {
            $service->assignFromConsole($user, $role);
        } catch (ValidationException $exception) {
            $this->error($exception->errors()['role'][0]);

            return self::FAILURE;
        }

        $this->info("Rol {$role->label()} asignado al usuario #{$user->id}.");

        return self::SUCCESS;
    }
}
