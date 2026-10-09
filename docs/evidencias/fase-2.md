# Evidencia de desarrollo: Fase 2

Fecha: 6 de octubre de 2026, Guatemala.

## Alcance autorizado

El usuario indicó «procede» después del cierre de la Fase 1. Se implementa exclusivamente Jetstream y Livewire para autenticación. Bootstrap y Sneat siguen reservados para las fases siguientes.

## Versiones y decisiones

- Jetstream 5.5.3, Livewire 3.8.10 y Fortify 1.41.0, sobre Laravel 12.69.3 y MySQL 8.0.44.
- Se revisó el instalador oficial. Agrega Tailwind y ofrece ejecutar migrate:fresh; para respetar el stack y conservar el control de migrations, se publicaron sus configuraciones, acciones, componentes y vistas directamente desde los stubs oficiales.
- Se conserva el guard `web` de Laravel. API/Sanctum, Teams, eliminación de cuenta, 2FA y passkeys no se habilitaron.
- El User existente se amplió con HasProfilePhoto y el acceso a profile_photo_url. La migración de usuarios, todavía no ejecutada al comenzar esta fase, incorporó profile_photo_path.
- Login, logout, registro, perfil, actualización y recuperación de contraseña utilizan las acciones oficiales de Jetstream/Fortify. El perfil utiliza los componentes Livewire del paquete.
- AUTH_REGISTRATION_ENABLED permite deshabilitar el registro público al arrancar la configuración.
- Las sesiones se guardan en MySQL. Caché por archivos y cola síncrona continúan vigentes.
- Solo se ejecutó en memorylab la migración de autenticación. Sus tablas son users, password_reset_tokens, sessions y migrations. No se ejecutaron migrate:fresh, seeders ni migrations del simulador sobre esta base.
- Se preparó memorylab_testing, comprobando primero que no tenía tablas, para las pruebas de persistencia. PHPUnit fuerza el nombre de esta base para aislar los datos de la aplicación.
- Se compilaron los assets existentes con Vite 6.4.4, Laravel Vite Plugin 1.3.0 y Axios 1.20.0. No se instaló Tailwind. Las clases de los stubs se adaptarán visualmente en las fases 3–5.
- La verificación de Apache detectó que las rutas predeterminadas de Livewire omitían /sistemg3/public. Se integró su módulo ESM con Vite y su Alpine incorporado, usando @livewireScriptConfig y la URL absoluta generada por la ruta livewire.update. No se modificaron vendor ni la configuración de Apache.
- npm detectó avisos en concurrently/shell-quote. Se retiró concurrently porque los scripts del proyecto no lo utilizan; la instalación resultante informó cero vulnerabilidades.
- El envío de correo permanece en modo log. El envío real requiere configurar SMTP en .env.

## Pruebas y correcciones

- Se incorporaron las pruebas oficiales de autenticación, confirmación de contraseña, recuperación, registro, perfil y cambio de contraseña.
- Se corrigió la referencia del stub de confirmación a withPersonalTeam, porque no se utilizan Teams.
- Se agregaron comprobaciones de logout, protección de dashboard/perfil, límite de intentos y desactivación de GET/POST de registro.
- La prueba de registro deshabilitado reinicia el repositorio de variables de entorno antes del nuevo arranque para evitar que Dotenv conserve valores cargados anteriormente.
- La prueba de recuperación comprueba que el hash corresponde a la nueva contraseña y rechaza la anterior.
- La prueba de otras sesiones utiliza registros reales de MySQL y una sesión explícita: el helper unitario de Livewire omite el middleware que normalmente proporciona la sesión al componente.
- Se agregó LivewireRoutingTest para verificar las URLs cuando Laravel se sirve desde una subcarpeta de Apache.
- Resultado final: 26 pruebas aprobadas y 66 assertions. Un caso condicional del scaffolding se omite porque el registro está activo; la desactivación queda cubierta por RegistrationDisabledTest.
- Apache devuelve HTTP 200 en inicio, login, registro y solicitud de recuperación. El formulario de login utiliza la URL con el prefijo /sistemg3/public.
- Se comprobó directamente en Apache el registro, la redirección al panel, una actualización real de perfil por POST Livewire, logout y la redirección de invitados al login. Se usaron cuentas temporales con contraseñas aleatorias, eliminadas al terminar; memorylab conserva cero usuarios, sin cuentas predeterminadas.
- Una petición de verificación que conservaba Accept application/json obtuvo 401 después de logout, comportamiento correcto para JSON. Se repitió como petición HTML y se confirmó la redirección al login.
- La compilación Vite, la compilación Blade y las verificaciones de dependencias pasaron.
- .env, vendor, node_modules y public/build permanecen ignorados por Git. Los archivos lock registran las versiones instaladas.

## Fuentes oficiales revisadas

- https://jetstream.laravel.com/installation.html
- https://github.com/laravel/jetstream/blob/5.x/src/Console/InstallCommand.php
- Stubs y código de los paquetes instalados en vendor, usados como referencia de implementación.

## Cierre

Fase 2 completada. No se inició la Fase 3.

Commit sugerido: `feat: configure Jetstream and Livewire authentication`.
