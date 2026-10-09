# Fase 28 — Despliegue local y preparación Linux

Fecha de cierre: **8 de octubre de 2026**.

Estado: **alcance local comprobado y guía Linux preparada; VM opcional no provisionada**.

## Alcance autorizado

La solicitud inicial contempla Ubuntu con Apache/Nginx, PHP y MySQL, y permite VirtualBox o VMware de forma opcional. Se conserva esa distinción: el entorno real comprobado es el Apache local de Windows/XAMPP; la guía y configuraciones Linux son entregables preparados para adaptar a un destino disponible. No se afirma una publicación externa ni ejecución dentro de una VM.

La autorización general del usuario permite completar las fases sin nuevas confirmaciones. Esta fase no cambia credenciales, configuración global ni datos existentes para demostrar un despliegue inexistente.

## Entorno local comprobado

- Proyecto en C:\xampp\htdocs\sistemg3, servido desde http://localhost/sistemg3/public.
- PHP de XAMPP 8.2.12 y MySQL Community Server 8.0.44 oficial, separados del MariaDB incluido por XAMPP.
- Apache entrega las pantallas y recursos compilados. Login, navegación e interacciones Livewire se verificaron durante las fases.
- El prefijo /sistemg3/public funciona para recursos, fuentes y actualización de Livewire.
- La suite integral de Fase 27 aprobó 655 pruebas, con una omisión condicional y 4.575 aserciones. Los datos locales y las cuentas se conservaron.
- La pareja base está preparada mediante el seeder explícito; repetirlo se verificó sin cambios.

La evidencia de HTTP inicial está en [Fase 1](fase-1.md), la corrección de recursos bajo subcarpeta en [Fase 4](fase-4.md), y las comprobaciones de terminal/presentación en [Fase 24](fase-24.md) y [Fase 25](fase-25.md).

## Entregables de preparación Linux

- [Guía de despliegue](../despliegue.md): requisitos, entorno manual, conservación de APP_KEY y datos, migraciones selectivas, catálogo, cuentas existentes, validación y recuperación.
- [Apache 2.4 + PHP-FPM](../../deploy/apache-memorylab.conf): ejemplos TLS con public/ como DocumentRoot, redirección HTTP y socket a adaptar.
- [Nginx + PHP-FPM](../../deploy/nginx-memorylab.conf): recursos públicos y front controller index.php, parámetros de consulta y POST de Livewire.
- [Ajustes PHP](../../deploy/php-production.ini): ejemplo para combinar con PHP-FPM, sin sustituir la configuración completa.

La guía exige PHP 8.2 o superior compatible con composer.lock, MySQL oficial 8.0.16 o superior y las extensiones requeridas. Conserva CACHE_STORE=file y QUEUE_CONNECTION=sync; no requiere preparar las tablas pendientes de caché o cola para esos drivers. Indica configuración privada de .env, cuenta de aplicación y SMTP cuando corresponda, sin credenciales versionadas.

## Límites reales de la verificación

Los archivos Linux no se instalaron en un servidor. Las rutas, dominio, socket y certificados incluidos son ejemplos que deben adaptarse. No se ejecutaron apachectl configtest o nginx -t sobre un destino Linux, no se provisionó una VM y no se emitió un certificado o publicó un dominio del proyecto.

El responsable de un futuro destino podrá seguir la guía, validar la configuración real y registrar su evidencia. El funcionamiento del Apache local y la preparación de documentación completan el alcance de esta entrega, conservando la VM como opción.

## Continuación

Fase 28 completada en el alcance indicado. Continúan informe, diapositivas y video de las Fases 29–31.

Commit sugerido: `docs: prepare deployment guide and server configuration examples`.
