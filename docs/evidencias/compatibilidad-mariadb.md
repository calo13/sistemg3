# Compatibilidad con MariaDB del hosting

Fecha: 2026-10-09.

## Solicitud y causa

El usuario informó que `php artisan migrate --seed` completó las tablas de usuarios, caché, trabajos y permisos, pero rechazó la migración del dominio con el mensaje «Utiliza MySQL 8.0.16 o superior para aplicar las restricciones CHECK». La consulta solicitada al usuario devolvió `10.6.28-MariaDB`.

La comprobación original rechazaba cualquier MariaDB, aunque ese servidor admite las restricciones utilizadas. El fallo ocurrió antes de crear `scenarios`; no requiere borrar las migraciones ni las tablas anteriores.

## Cambio

- `DatabaseCompatibility` valida MySQL >= 8.0.16 o MariaDB >= 10.4.3, incluidos sufijos de distribución y el prefijo de compatibilidad `5.5.5-` de algunos servidores MariaDB.
- La migración conserva los seis CHECK explícitos, las claves foráneas, los índices únicos, ENUM y JSON. El mínimo MariaDB conserva la validación JSON automática del esquema.
- `memorylab:database` utiliza la misma validación y admite tanto `DB_CONNECTION=mysql` como `mariadb`. Ambos conectores usan `pdo_mysql`; no hace falta cambiar el driver existente del hosting.
- Las pruebas del esquema exigen el código real de fallo CHECK de cada motor: 3819 en MySQL y 4025 en MariaDB. Una prueba adicional escribe JSON inválido directamente y comprueba que la base lo rechaza.
- La verificación de GitHub conserva MySQL/PHP 8.2–8.4 y añade MariaDB 10.6/PHP 8.2.

## Verificación local

Se utilizó MySQL 8.0.44 en la base de pruebas separada `memorylab_testing`. La suite completa aprobó **728 pruebas**, con una omisión condicional y **4865 aserciones**, en **96,27 segundos**. La omisión es el caso de registro deshabilitado cuando el registro está activo; otro caso verifica explícitamente su desactivación.

Se preparó una instancia temporal MariaDB 10.4.32 de XAMPP en el puerto 3307, con directorio de datos dentro de `tmp/`, limitada a `127.0.0.1` y sin registrar un servicio de Windows. La base principal y su configuración no se modificaron.

Las 45 pruebas de versiones y las 44 pruebas de esquema aprobaron juntas: **89 pruebas y 168 aserciones**. La suite de demostraciones aprobó sus **19 pruebas y 99 aserciones**.

La primera ejecución integral de MariaDB obtuvo 727 aprobadas y una omisión, y encontró un error temporal de sockets de Windows al abrir una conexión PDO. La prueba afectada aprobó al repetir su clase completa. La segunda ejecución integral aprobó **728 pruebas**, con una omisión condicional y **4865 aserciones**, en **87,72 segundos**, sin fallos.

Una segunda base temporal, `memorylab_hosting_smoke`, reprodujo las cuatro migraciones previas al fallo. El comando de preparación funcionó con ambos drivers; con el driver nativo `mariadb`, `migrate --seed --force` aplicó las dos migraciones pendientes. Repetirlo indicó «Nothing to migrate» y conservó **6 migraciones, 3 roles, 13 permisos y 0 usuarios**. Se comprobó la continuación y la repetición sin crear cuentas ni escenarios.

El conector nativo `mariadb` también aprobó **44 pruebas y 216 aserciones** de solicitudes y pasos de paginación, en **8,701 segundos**, usando una configuración temporal de PHPUnit y una tercera base aislada. La configuración normal de pruebas permaneció intacta.

Pint y `git diff --check` aprobaron. La ejecución automática de GitHub y la instalación real del hosting son verificaciones distintas de estos resultados locales.

## Continuación en el hosting

Desde la carpeta existente del proyecto:

```sh
git pull --ff-only origin main
php artisan config:clear
php artisan migrate --seed --force
```

Se conservan `.env`, `APP_KEY` y la base existente. No se necesita `migrate:fresh` ni rollback. `DatabaseSeeder` prepara roles/permisos, sin crear usuarios ni demostraciones. Tras ejecutar los comandos, revisar su salida y `php artisan migrate:status` en el hosting.

No se accedió al servidor del usuario desde esta sesión ni se ejecutaron sus migraciones remotamente.

## Fuentes

- [Bases de datos admitidas por Laravel 12](https://laravel.com/framework/docs/12.x/database): Laravel admite MariaDB desde 10.3; el requisito de MemoryLab es más estricto por la validación JSON.
- [Restricciones de MariaDB](https://mariadb.com/docs/server/reference/sql-statements/data-definition/constraint): CHECK y claves foráneas.
- [Notas de MariaDB 10.4.3](https://mariadb.com/docs/release-notes/community-server/old-releases/10.4/10.4.3): validación JSON automática mediante JSON_VALID. Es un mínimo técnico; utilizar una versión estable y mantenida del proveedor.
- [Errores 4000–4099 de MariaDB](https://mariadb.com/docs/server/reference/error-codes/mariadb-error-codes-4000-to-4099): 4025 corresponde a una restricción incumplida.
