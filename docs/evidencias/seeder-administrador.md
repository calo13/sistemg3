# Seeder de administrador para práctica

Fecha: 2026-10-09.

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
