# Seeder de administrador para práctica

Fecha: 2026-10-09.

## Alcance original del 9 de octubre

El usuario pidió dejar un seeder para la cuenta administradora con contraseña inicial `123` y completar por su cuenta los datos del simulador. Se conservó el correo `admin@admin.com` solicitado anteriormente.

`AdminUserSeeder` se ejecuta de forma explícita:

```sh
php artisan db:seed --class=AdminUserSeeder
```

En producción se añade `--force`. El seeder prepara el catálogo de roles/permisos, crea la cuenta si falta y le asigna el rol Administrador. La contraseña se guarda mediante el hash de Laravel. Si la cuenta ya existe, se conservan su nombre, contraseña y demás campos; repetirlo no duplica el usuario.

No crea escenarios, configuración de memoria, procesos, páginas, marcos, segmentos ni eventos. `DatabaseSeeder` continúa preparando solo roles/permisos; `MemoryDemoSeeder` permanece independiente. Tampoco borra los datos que el usuario ya haya creado.

## Verificación

- Sintaxis PHP, Pint y `git diff --check` aprobados.
- **44 pruebas aprobadas y 254 aserciones**, en 14,74 segundos: nuevo seeder, autenticación, roles/permisos y seeder de demostración.
- Se verificaron login con `123`, permisos administrativos, repetición sin cuentas duplicadas, hash conservado y ausencia de datos de demostración.
- Se comprobó que una cuenta existente conserva todos sus campos, que otra cuenta mantiene su rol Operador y que un escenario manual permanece idéntico.
- Las pruebas utilizaron `memorylab_testing`. No se ejecutó el seeder sobre la base principal ni sobre el hosting durante esta entrega.

## Inicio vacío para aprendizaje: 21 de octubre de 2026

El usuario pidió que la aplicación quede sin datos de simulación y con un único usuario `admin@admin.com`, contraseña `123`, para aprender el recorrido manual y explicarlo a sus compañeros.

`DatabaseSeeder` ahora invoca `AdminUserSeeder`, que prepara los tres roles, 13 permisos y la cuenta Administrador. No invoca `MemoryDemoSeeder`; esa demostración sigue siendo explícita y opcional. En una base nueva, `php artisan migrate --seed` deja solo esta cuenta y el catálogo necesario. Ejecutar `php artisan db:seed` de nuevo conserva cuentas existentes, contraseñas y prácticas: no es un comando de borrado.

Antes de actuar se comprobó la conexión local MySQL en `127.0.0.1`, base `memorylab`: ya tenía cero usuarios y cero filas en las siete tablas del dominio. No fue necesario borrar datos ni reconstruir el esquema. Se ejecutó únicamente `php artisan db:seed` y se verificó:

- Un usuario, correo `admin@admin.com`, contraseña `123` validada contra su hash y único rol `administrador`.
- Cero escenarios, configuraciones, procesos, marcos, páginas, segmentos y eventos.
- Tres roles y 13 permisos para permitir el funcionamiento de la aplicación.
- Página local `/login` disponible con HTTP 200 y formulario en español.

Las **44 pruebas aprobaron con 269 aserciones**, en 22,98 segundos, sobre `memorylab_testing`: seeders, autenticación y roles/permisos. Incluyen login con `123`, ejecución repetida sin duplicar cuentas ni cambiar su contraseña, ausencia de simulaciones y conservación de datos existentes. La base inicial de las pruebas prepara solo el catálogo para que cada caso cree sus propias cuentas; los casos de instalación ejecutan `DatabaseSeeder` expresamente. Pint, sintaxis PHP y `git diff --check` aprobaron.

Esta preparación se realizó en la instalación local; el hosting no se modificó.
