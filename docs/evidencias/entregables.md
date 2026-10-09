# Integración de entregables académicos

Fecha de verificación: **8 de octubre de 2026**.

## Alcance

Se agregó la página `/entregables` al layout y menú de Sneat, con la identidad académica de la Universidad Mariano Gálvez y los cinco integrantes configurados. Reúne el manual de uso PDF, el informe PDF, la presentación PowerPoint y el video WebM; este último admite reproducción en la página y descarga. El manual se presenta primero, con la indicación «Cómo usar y demostrar MemoryLab», para orientar la secuencia de la práctica.

El informe de **nueve páginas** está generado y revisado, como registra [Fase 29](fase-29.md). La presentación de **doce diapositivas** está confirmada con comprobación nativa aprobada. El video final de **180 segundos**, WebM VP9 de 1280 × 720 con subtítulos en español, se verificó en [Fase 31](fase-31.md). El [manual adicional](manual-de-uso.md) completa **ocho páginas**, con cuatro capturas, secuencia operativa, ejercicios, resultados y guion de exposición de 14 minutos. Las fases originales y el manual están completos, y los cuatro archivos están disponibles. Si un archivo falta en otra instalación, la página muestra «Preparación temporal» sin ofrecer un enlace de descarga inválido.

## Acceso y archivos

Administrador, Operador y Observador pueden consultar la página cuando conservan los cuatro permisos `memory.view`, `tables.view`, `simulations.view` y `results.view`. Las rutas y el controlador verifican la autorización en servidor; el controlador vuelve a consultar el usuario y sus permisos antes de servir archivos.

Las rutas son:

| Ruta | Uso |
| --- | --- |
| `/entregables` | Página académica y disponibilidad actual. |
| `/entregables/descargar/manual` | Descarga del manual PDF con pasos y resultados esperados. |
| `/entregables/descargar/informe` | Descarga del PDF. |
| `/entregables/descargar/presentacion` | Descarga del PowerPoint. |
| `/entregables/descargar/video` | Descarga del WebM. |
| `/entregables/video` | Reproducción del WebM con soporte de rangos HTTP. |

El controlador utiliza una lista cerrada de cuatro claves y rutas fijas dentro de `output/pdf`, `output/presentations` y `output/video`. El manual corresponde exclusivamente a `output/pdf/memorylab-manual-de-uso.pdf`. No acepta una ruta de archivo enviada por el cliente. Una clave desconocida o un archivo ausente devuelve 404; un usuario sin los permisos requeridos recibe 403 y un visitante debe iniciar sesión.

Las respuestas declaran el MIME y la disposición adecuados. La descarga utiliza `attachment`; la reproducción, `inline`. Ambas se marcan privadas y sin almacenamiento de caché. Se corrigió la construcción de `BinaryFileResponse` para aplicar `setPrivate()` después de crear la respuesta: su constructor podía reemplazar la directiva privada inicial.

En Apache local, la URL directa del PDF dentro de `output` respondía 200 porque el proyecto está bajo `htdocs`. Se añadió `output/.htaccess` con `Require all denied` y se verificó una respuesta **403** para esa URL directa. PHP conserva la lectura del archivo mediante el controlador autorizado. La guía de [despliegue](../despliegue.md) exige que el DocumentRoot apunte a `public`, manteniendo `output` fuera del árbol público en una instalación preparada de esa forma.

## Verificación realizada

La integración inicial de tres archivos aprobó **24 pruebas y 203 aserciones** de `DeliverablesTest`, en **6,54 segundos**. Tras agregar el manual, la verificación actual aprobó **27 pruebas y 243 aserciones**, en **8,31 segundos**. Las pruebas utilizan archivos temporales aislados y no sustituyen los entregables reales. Fixtures, proveedores, comprobaciones de permisos y estado vacío contemplan los cuatro archivos; se verifica además que el manual aparezca primero y que no admita recorridos de ruta.

Se verificaron:

- Redirección al login para visitantes y acceso de los tres roles.
- Rechazo tras retirar cualquiera de los cuatro permisos, incluso con permisos previamente cargados en la instancia original del usuario.
- Identidad académica, enlaces y estado de preparación cuando faltan archivos.
- Lista cerrada de archivos, rechazo de claves desconocidas y recorridos de ruta, e imposibilidad de sustituir el archivo con parámetros de consulta.
- MIME, nombre de archivo, disposición de descarga y cabeceras privadas sin caché.
- Reproducción WebM con rango válido 206 y rango fuera del archivo 416.
- Conservación de las siete tablas del dominio durante las consultas.

Pint, la comprobación de sintaxis de los tres archivos PHP afectados y la compilación/limpieza de vistas Blade aprobaron para la integración inicial. El listado de rutas registra **17 rutas propias**, después de añadir las tres rutas de integración; agregar la clave manual utiliza la misma ruta de descarga.

Estas verificaciones son **posteriores al corte integral de Fase 27**. Se mantienen como evidencia adicional: no sustituyen las cifras históricas de 655 pruebas aprobadas, una omisión condicional, 4.575 aserciones y 14 rutas propias consignadas en esa fase y en el informe.

## Verificación final en navegador

La revisión de la aplicación real aprobó después de completar los cuatro archivos:

- El visitante fue redirigido al login.
- Administrador, Operador y Observador descargaron los cuatro artefactos mediante sus rutas protegidas.
- El video se reprodujo por HTTP; sus metadatos confirmaron **180 segundos y 1280 × 720**, y se verificaron reproducción y búsqueda temporal.
- La página se comprobó en anchos de **320 a 1366 píxeles**, sin desbordamiento horizontal.
- Las firmas del dominio, autenticación, catálogo y asignaciones de roles/permisos quedaron intactas; no se conservaron datos temporales de la verificación.

Capturas finales: [escritorio](entregables-escritorio.png) y [móvil](entregables-movil.png).

La instalación local Apache está comprobada. La [guía Linux](../despliegue.md) y sus ejemplos están preparados; la VM es opcional y no fue provisionada, ni se publicó un servidor externo. No hay entregables pendientes dentro del alcance solicitado.
