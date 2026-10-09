# Despliegue de MemoryLab

Esta guía prepara la Fase 28. El proyecto se ha verificado en el Apache local de Windows/XAMPP con MySQL oficial. Los archivos de deploy/ son ejemplos para adaptar a un servidor Linux; no se ha accedido a una máquina virtual ni aplicado estas configuraciones en ella.

La solicitud original permite Ubuntu con Apache/Nginx, PHP y MySQL, y establece VirtualBox o VMware como opción. La máquina virtual es **opcional**: estos pasos también pueden adaptarse a un servidor Linux disponible. La guía y los ejemplos están **preparados**, sin afirmar un despliegue ejecutado fuera del entorno local. El alcance original se conserva en [la solicitud inicial](evidencias/solicitud-inicial.md).

## Requisitos del destino

- PHP 8.2 o superior compatible con composer.lock, con Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, Session, Tokenizer y XML. Para esta aplicación también se requiere pdo_mysql. CLI y PHP-FPM deben cargar las mismas extensiones. La base de requisitos corresponde a [Laravel 12](https://laravel.com/docs/12.x/deployment#server-requirements).
- MySQL oficial 8.0.16 o superior, InnoDB y utf8mb4. La migración del dominio rechaza MariaDB y versiones anteriores porque necesita restricciones CHECK efectivas; [MySQL documenta su aplicación desde 8.0.16](https://dev.mysql.com/doc/refman/8.0/en/create-table-check-constraints.html).
- Composer 2 y los archivos composer.lock/package-lock.json del proyecto. Para compilar, Node.js 22 o posterior compatible con las dependencias fijadas y npm. El Vite 6.4.4 instalado admite Node 18/20/22+, pero conviene preparar el destino con una versión mantenida y verificar sus engines antes de actualizar paquetes.
- Apache 2.4 con rewrite y PHP-FPM, o Nginx con PHP-FPM. Los ejemplos utilizan /var/www/memorylab y el socket /run/php/php8.2-fpm.sock; ambos deben ajustarse al destino real.
- Un nombre de dominio, certificado TLS y acceso administrativo al servidor, proporcionados por quien realiza el despliegue. memorylab.example es un marcador, no un dominio del proyecto.

## Preparación de archivos y entorno

Trabajar dentro de la carpeta del proyecto. Conservar .env, APP_KEY, base de datos y archivos persistentes de cualquier instalación existente. Crear .env desde .env.example únicamente si no existe; editarlo de forma privada en el servidor y no incorporarlo al repositorio.

Instalar las versiones fijadas y construir los recursos:

```sh
composer install --no-dev --optimize-autoloader
composer check-platform-reqs
npm ci
npm run build
```

La compilación puede realizarse en una máquina de construcción compatible; en ese caso publicar también public/build con su manifest.json. No utilizar el servidor de desarrollo de Vite en el destino. Después de actualizar Livewire con Composer se deben reconstruir sus recursos.

Configurar manualmente estos valores de .env:

| Variable | Valor o criterio |
| --- | --- |
| APP_ENV | production |
| APP_DEBUG | false |
| APP_URL | URL HTTPS real, sin /public cuando public/ es la raíz del sitio |
| APP_KEY | Conservar la clave existente; generar una sola vez en instalación nueva |
| APP_LOCALE | es |
| DB_CONNECTION | mysql |
| DB_HOST / DB_PORT / DB_DATABASE | Destino real de MySQL y base exclusiva de MemoryLab |
| DB_USERNAME / DB_PASSWORD | Cuenta de aplicación configurada privadamente; no utilizar root como cuenta habitual |
| SESSION_DRIVER | database |
| SESSION_SECURE_COOKIE | true con HTTPS operativo |
| CACHE_STORE | file |
| QUEUE_CONNECTION | sync |
| AUTH_REGISTRATION_ENABLED | Decisión del responsable; false después de preparar las cuentas si el registro público no se necesita |
| LOG_LEVEL | warning o el nivel acordado para el servidor |
| MAIL_* | SMTP real si se requiere recuperación por correo |

CACHE_STORE=file y QUEUE_CONNECTION=sync conservan el contrato actual: las migraciones de caché y trabajos no son necesarias para esta instalación. No cambiar esos drivers sin preparar sus tablas y operación correspondiente. MAIL_MAILER=log solo registra notificaciones; no entrega correo a un buzón. Los logs y enlaces de recuperación deben permanecer privados.

En una instalación nueva, después de editar .env:

```sh
php artisan key:generate
```

En una actualización se conserva APP_KEY. Limpiar la caché de configuración antes de comprobar el entorno editado:

```sh
php artisan config:clear
php artisan memorylab:database
```

El segundo comando comprueba la conexión y rechaza MariaDB; no migra tablas. La versión mínima se comprueba además en la migración del dominio. Si la base nueva aún no existe, el responsable puede crearla en MySQL o ejecutar memorylab:database --create con una cuenta que tenga permiso de creación; el comando no elimina una base existente. Separar la cuenta de preparación de la cuenta habitual cuando corresponda.

## Migraciones y permisos

Antes de actualizar una instalación con datos, guardar un respaldo de MySQL y de los archivos persistentes, y registrar la versión de código y APP_KEY que le corresponden. Para una actualización del esquema, colocar temporalmente la aplicación en mantenimiento y ejecutar únicamente las migraciones pendientes revisadas.

El conjunto del proyecto, con sesiones en base, caché en archivos y cola síncrona, es:

```sh
php artisan migrate --path=database/migrations/0001_01_01_000000_create_users_table.php --force
php artisan migrate --path=database/migrations/2026_10_07_131055_create_permission_tables.php --force
php artisan migrate --path=database/migrations/2026_10_07_160000_create_memory_simulation_tables.php --force
php artisan migrate --path=database/migrations/2026_10_07_220000_add_loaded_at_to_pages_table.php --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan migrate:status
```

Artisan conserva el registro de migraciones ya aplicadas. El seeder prepara el catálogo de tres roles y 13 permisos, sin crear cuentas ni otorgar Administrador automáticamente. Las migraciones de caché y trabajos pueden seguir pendientes con los drivers indicados. No usar migrate:fresh, migrate:refresh, db:wipe ni un rollback de esquema para actualizar una instalación que debe conservar sus datos.

Para preparar el primer Administrador, habilitar temporalmente el registro cuando sea necesario, registrar una cuenta propia y asignarle el rol con el comando existente:

```sh
php artisan memorylab:user-role "tu-correo@ejemplo.com" administrador
```

El correo es un marcador que debe sustituirse por la cuenta ya registrada. El comando no crea usuarios ni cambia contraseñas. Preparar las demás cuentas mediante registro y administración de roles, según necesidad; los integrantes académicos del dashboard no son cuentas. Si se deshabilita el registro después, actualizar la caché de configuración. Esta guía no publica ni establece credenciales de acceso.

## Servidor web y PHP

La raíz del sitio debe ser /var/www/memorylab/public. Esta separación impide servir .env, vendor/, fuentes y documentación como archivos públicos. Mantener index.php en public/ y dar escritura al proceso PHP únicamente donde el proyecto la necesita: storage/ y bootstrap/cache/. Es el esquema indicado por [Laravel para el servidor y sus directorios](https://laravel.com/docs/12.x/deployment#server-configuration).

Los ejemplos TLS exigen certificados existentes. Adaptar dominio, ruta y socket antes de instalarlos. Elegir solo el servidor utilizado:

- [Apache](../deploy/apache-memorylab.conf): VirtualHost de 80 con redirección y de 443 con public/ como DocumentRoot. Requiere ssl, rewrite, proxy y proxy_fcgi. Conserva public/.htaccess mediante AllowOverride All. El significado de DocumentRoot y Directory corresponde a la [documentación de Apache 2.4](https://httpd.apache.org/docs/2.4/mod/core.html#documentroot).
- [Nginx](../deploy/nginx-memorylab.conf): sirve recursos públicos y envía solo index.php a PHP-FPM, preservando parámetros de consulta y POST de Livewire. Ajustar el socket según [FastCGI de Nginx](https://nginx.org/en/docs/http/ngx_http_fastcgi_module.html#fastcgi_pass).
- [PHP](../deploy/php-production.ini): valores de producción para revisar y combinar con la configuración PHP-FPM real; no reemplaza el php.ini completo ni configura extensiones.

El usuario del proceso PHP debe poder recorrer la ruta, leer el código y escribir storage/bootstrap/cache. Definir propiedad y grupo según el servidor; evitar permisos 777 o escritura pública sobre todo el proyecto. La misma cuenta de despliegue debe poder generar las cachés sin dejar archivos inaccesibles para PHP-FPM.

Después de instalar los ejemplos adaptados, el administrador del servidor valida la sintaxis antes de recargar su servicio:

```sh
apachectl configtest
```

o, si eligió Nginx:

```sh
nginx -t
```

La validación no acredita que el destino esté desplegado; hay que completar la comprobación HTTP. Si existe un proxy inverso, configurar sus cabeceras y los proxies confiables del destino antes de utilizarlo; los ejemplos corresponden a conexión HTTPS directa.

## Cierre y comprobación del destino

Con .env definitivo, recursos compilados y migraciones correctas:

```sh
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Si la actualización utilizó mantenimiento, retirarlo al terminar. Revisar en la URL real:

1. /up responde correctamente. Esta ruta comprueba el arranque de Laravel; completar además la comprobación de MySQL y login.
2. Login, perfil, cierre de sesión y una interacción Livewire funcionan con HTTPS, sin recursos ausentes ni errores JavaScript.
3. Administrador ve usuarios/configuración/historial; Operador ejecuta accesos autorizados; Observador consulta y recibe rechazo ante acciones no permitidas.
4. public/build carga y los recursos del navegador se resuelven bajo el dominio real.
5. Las cuentas, escenarios y eventos anteriores siguen disponibles. Una consulta no prepara ni reinicia una demostración automáticamente.
6. Registro y recuperación corresponden a los valores elegidos en .env. La recuperación por correo requiere SMTP comprobado.

Las pruebas automatizadas de persistencia se ejecutan en una instalación de verificación con dependencias de desarrollo y una base separada memorylab_testing. No dirigir php artisan test a la base utilizada por las personas del proyecto. Conservar evidencia del destino: fecha, versiones, dominio, salida de validación de sintaxis y resultado de las comprobaciones, sin credenciales o enlaces de recuperación.

## Recuperación de una actualización fallida

Mantener mantenimiento mientras se investiga un fallo. Recuperar el código anterior y sus recursos junto con la configuración compatible. Si cambió el esquema o los datos y se requiere restauración, utilizar el respaldo revisado por el responsable de MySQL; no ejecutar una limpieza general o rollback destructivo como sustituto del respaldo. Conservar APP_KEY y archivos persistentes correspondientes, regenerar cachés y repetir las comprobaciones antes de abrir el servicio.

Estado de estos ejemplos: **preparados para revisión; no aplicados en Linux ni en una máquina virtual**. El Apache local utilizado durante las fases es el único entorno de navegador verificado hasta esta documentación.
