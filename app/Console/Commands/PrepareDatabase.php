<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDOException;

class PrepareDatabase extends Command
{
    protected $signature = 'memorylab:database {--create : Crear la base configurada si todavía no existe, sin crear tablas}';

    protected $description = 'Comprobar la conexión a MySQL y preparar la base de MemoryLab';

    public function handle(): int
    {
        if (config('database.default') !== 'mysql') {
            $this->error('MemoryLab requiere DB_CONNECTION=mysql.');

            return self::FAILURE;
        }

        $connection = config('database.connections.mysql');
        $database = $connection['database'];

        if (! is_string($database) || ! preg_match('/\A[A-Za-z_][A-Za-z0-9_]{0,63}\z/', $database)) {
            $this->error('DB_DATABASE debe ser un identificador válido de hasta 64 caracteres.');

            return self::FAILURE;
        }

        if (! empty($connection['url'])) {
            $this->error('Para este comando configura DB_HOST, DB_PORT, DB_DATABASE y credenciales; deja DB_URL vacío.');

            return self::FAILURE;
        }

        try {
            $server = DB::build(array_replace($connection, ['database' => null]));
            $version = $server->selectOne('SELECT VERSION() AS version')->version;

            if (stripos($version, 'MariaDB') !== false) {
                $this->error('El servidor responde como MariaDB. Configura el servidor MySQL oficial del proyecto.');

                return self::FAILURE;
            }

            if ($this->option('create')) {
                $server->statement("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }

            $selected = DB::connection('mysql')->selectOne('SELECT DATABASE() AS database_name');

            $this->info("MySQL {$version}: conexión correcta a {$selected->database_name}.");
            $this->line('No se ejecutaron migraciones ni se crearon tablas.');

            return self::SUCCESS;
        } catch (QueryException|PDOException $exception) {
            $errorCode = $exception->errorInfo[1] ?? null;

            $this->error(match ($errorCode) {
                1045 => 'Acceso denegado. Revisa DB_USERNAME y DB_PASSWORD en .env.',
                1049 => 'La base no existe. Ejecuta memorylab:database --create para prepararla.',
                default => 'No se pudo preparar la conexión. Revisa el servicio MySQL, host, puerto y permisos del usuario.',
            });

            return self::FAILURE;
        }
    }
}
