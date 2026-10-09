# Evidencia de desarrollo: Fase 1

Fecha: 6 de octubre de 2026, Guatemala.

## Solicitud y alcance

El usuario autorizó comenzar la Fase 1 y pidió confirmar la carpeta de trabajo y la instalación de Laravel.

Carpeta: `C:\xampp\htdocs\sistemg3`. La carpeta estaba vacía al iniciar. Solo se prepara Laravel, Git y MySQL; las fases posteriores requieren autorización independiente.

## Decisiones

- Laravel Framework 12.69.3, con dependencias fijadas en `composer.lock`.
- Composer y Artisan se ejecutaron con PHP 8.2.12 de XAMPP. No se modificó el PHP global de Windows.
- Se encontró MySQL Community Server 8.0.44 ya instalado, con el servicio MySQL80 activo. Se aprovecha este servidor sin instalar MySQL 8.4 ni utilizar MariaDB de XAMPP.
- Se desactivaron los scripts que crean SQLite o ejecutan migraciones automáticamente.
- Sesiones y caché usan archivos; la cola es síncrona para que el arranque no dependa de tablas.
- PHPUnit queda configurado para MySQL y una base separada, `memorylab_testing`. La URL de pruebas es `http://localhost` para evitar que el prefijo de Apache produzca un 404 en las solicitudes de prueba.
- Se retiraron las dependencias y directivas Tailwind del scaffolding. Vite queda preparado con la combinación 6.x/plugin 1.2.x propuesta; no se instalaron paquetes npm.
- La página inicial de Laravel se sustituyó por una página Blade mínima de MemoryLab, sin dashboard ni simulación.
- Se agregó el comando Laravel `memorylab:database`, que comprueba MySQL; con `--create` prepara únicamente la base si no existe. No crea tablas ni ejecuta migrations.
- Git quedó inicializado en la rama main. `.env`, sus variantes locales, vendor y node_modules están ignorados.

## Verificación

- Laravel informa versión 12.69.3.
- La ruta inicial está registrada.
- Las dos pruebas incluidas con Laravel pasan.
- Apache responde HTTP 200 en `http://localhost/sistemg3/public/` y entrega MemoryLab.
- Composer valida el manifiesto y las extensiones requeridas.
- Pint verifica el comando y la configuración de MySQL.
- La sintaxis de `vite.config.js` es válida.
- No se creó `database/database.sqlite` ni se ejecutaron migraciones.
- Se comprobó la conexión a MySQL 8.0.44 con las credenciales configuradas localmente en `.env`.
- `memorylab:database --create` terminó correctamente; una consulta a `information_schema.tables` confirmó que `memorylab` existe y contiene cero tablas.
- El diagnóstico `db:show` requiere la extensión `intl`, disponible en XAMPP pero desactivada por defecto. Se comprobó su carga temporal con `-d extension=intl`; no se modificó `php.ini`.

## Cierre

Fase 1 completada. La contraseña permanece exclusivamente en `.env`, ignorado por Git. No se instalaron Jetstream ni Livewire y no se inició la Fase 2.

Commit sugerido al cerrar la fase: `feat: initialize Laravel and configure MySQL`.
