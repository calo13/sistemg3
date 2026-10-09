# Fase 6 — Roles y permisos

## Autorización y alcance

Solicitud del usuario: «procede», después de la Fase 5. Se implementan los roles y permisos previstos en la solicitud original. Los módulos del simulador y el contenido definitivo del dashboard corresponden a fases posteriores.

Se utiliza spatie/laravel-permission 6.25.0, instalado con Composer y fijado en composer.lock. Su manifiesto admite PHP 8.2 y Laravel 12. Se revisó el código instalado del registro de permisos, caché, roles y limpieza de relaciones. La autorización de acciones sigue las recomendaciones de [seguridad de Livewire 3](https://livewire.laravel.com/docs/3.x/security).

## Catálogo

Todos los roles y permisos utilizan el guard de sesión `web`. No se incorpora un bypass global de autorización.

| Permiso | Administrador | Operador | Observador |
| --- | --- | --- | --- |
| users.manage | Sí | No | No |
| memory.configure | Sí | No | No |
| scenarios.create | Sí | No | No |
| simulations.execute | Sí | Sí | No |
| memory.reset | Sí | No | No |
| history.view | Sí | No | No |
| processes.create | Sí | Sí | No |
| pages.request | Sí | Sí | No |
| segmentation.execute | Sí | Sí | No |
| results.view | Sí | Sí | Sí |
| memory.view | Sí | Sí | Sí |
| tables.view | Sí | Sí | Sí |
| simulations.view | Sí | Sí | Sí |

El administrador incluye las capacidades del operador. Son 13 permisos, con 13, 8 y 4 asignaciones respectivamente. Los enums PermissionName y RoleName centralizan el catálogo para los módulos futuros.

## Implementación

- Se publicó la configuración y migración oficial de Spatie. Se aplicó únicamente la migración de permisos a memorylab; caché y colas siguen pendientes.
- RolesAndPermissionsSeeder crea el catálogo de forma repetible, conserva roles existentes y asigna Observador a cuentas sin rol. DatabaseSeeder llama a este seeder y no genera cuentas de ejemplo.
- El seeder restablece la caché antes de crear permisos, después de crearlos y al finalizar; funciona también cuando DatabaseSeeder suprime eventos de modelos.
- CreateNewUser crea la cuenta y asigna Observador en una transacción. Ignora los campos role, roles y permissions enviados al registro.
- UserPolicy comprueba la capacidad de administrar usuarios y restringe los cambios sobre la propia cuenta.
- UserRoleService vuelve a cargar al actor antes de autorizar, usa una transacción y bloquea la fila del rol Administrador para serializar cambios. Impide degradar al último administrador.
- El comando local memorylab:user-role permite designar al primer administrador a partir del correo de una cuenta existente, sin crear usuarios ni cambiar contraseñas. Rechaza roles inválidos, cuentas inexistentes y la degradación del último administrador.
- GET /administracion/usuarios exige sesión y users.manage. La pantalla utiliza tarjetas, tabla responsive, selectores y paginación Bootstrap/Sneat. El enlace del menú aparece únicamente con ese permiso.
- El componente Livewire valida el rol y autoriza montaje, renderizado y acciones. Los IDs y roles recibidos del navegador no otorgan autorización. El servicio rechaza cambios tras revocar los permisos del actor.
- La vista 403 comunica «Acceso restringido» en español con el diseño de autenticación de Sneat.
- En móvil, la tabla tiene desplazamiento horizontal dentro de su contenedor y los selectores conservan un ancho legible; la página no desborda.

## Verificación

- Suite final: 41 pruebas aprobadas, 164 assertions y un caso condicional omitido porque el registro está habilitado.
- La nueva suite cubre la matriz completa de permisos, seed repetido, preservación de cuentas y contraseñas, registro manipulado, acceso de invitados, administración restringida, validación de roles e IDs, protección del propio rol, último administrador y revocación de permisos entre renderizado y acción Livewire.
- npm run build: compilación correcta. Pint: formato correcto de los archivos modificados.
- Chrome headless: Observador y Operador recibieron acceso restringido; Administrador abrió la pantalla y cambió realmente una cuenta temporal de Observador a Operador por Livewire, confirmado en MySQL.
- La cuenta del administrador no mostraba selector para modificar su propio rol.
- Se comprobaron 1366, 390 y 320 píxeles: sin desbordamiento de página y con los selectores accesibles mediante el desplazamiento de la tabla. Sin excepciones JavaScript ni recursos necesarios fallidos.
- Las cuentas de navegador fueron aleatorias y temporales. Sus cuentas, sesiones y relaciones de roles se eliminaron al terminar.
- La base principal conservó su única cuenta existente, que recibió Observador. No se eligió automáticamente un administrador; queda disponible el comando para designar la cuenta que indique el usuario.
- Las cinco tablas de permisos utilizan InnoDB, con claves primarias, índices únicos y cuatro claves foráneas hacia roles y permisos. Las relaciones con users son polimórficas, conforme a la migración oficial.

## Capturas revisadas

- [Roles de escritorio](fase-6-roles-escritorio.png).
- [Roles en móvil](fase-6-roles-movil.png).

Las capturas muestran únicamente las cuentas temporales de prueba. Se ocultaron visualmente las filas de cuentas existentes para excluir sus datos personales de la evidencia; sus registros no se alteraron.

## Uso y cierre

Registrar una cuenta y sustituir el correo de ejemplo por el de esa cuenta:

```powershell
& 'C:\xampp\php\php.exe' artisan memorylab:user-role "tu-correo@ejemplo.com" administrador
```

Fase 6 completada. La siguiente fase del plan es Fase 7, layout/dashboard, pendiente de autorización del usuario.

Commit sugerido: `feat: configure roles and permissions`.
