<?php

namespace App\Support;

use RuntimeException;

class DatabaseCompatibility
{
    public static function assertSupported(string $driver, string $serverVersion): void
    {
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('MemoryLab requiere MySQL 8.0.16 o superior, o MariaDB 10.4.3 o superior.');
        }

        if (stripos($serverVersion, 'MariaDB') !== false) {
            // Some servers prepend the MySQL compatibility version 5.5.5.
            if (! preg_match('/(\d+\.\d+\.\d+)(?=-MariaDB\b)/i', $serverVersion, $matches)
                || version_compare($matches[1], '10.4.3', '<')) {
                throw new RuntimeException('Utiliza MariaDB 10.4.3 o superior para aplicar las restricciones CHECK y JSON de MemoryLab.');
            }

            return;
        }

        if ($driver === 'mariadb'
            || ! preg_match('/^\d+\.\d+\.\d+/', $serverVersion, $matches)
            || version_compare($matches[0], '8.0.16', '<')) {
            throw new RuntimeException('Utiliza MySQL 8.0.16 o superior para aplicar las restricciones CHECK de MemoryLab.');
        }
    }
}
