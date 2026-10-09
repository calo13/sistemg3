# Fase 3 — Bootstrap 5

## Autorización y alcance

Solicitud del usuario: «procede», tras el cierre de la Fase 2. Se interpreta como autorización para la siguiente fase del plan: Bootstrap 5.

Antes de modificar se revisaron package.json, las entradas de Vite, los layouts Blade, la página de inicio, el panel provisional y el registro de la fase anterior. Se informó que Sneat corresponde a la Fase 4 y la adaptación visual de autenticación a la Fase 5.

## Cambios

- Instalación de Bootstrap 5.3.8 y Popper 2.11.8 mediante npm. package-lock.json registra las versiones resueltas.
- Importación del CSS compilado oficial desde resources/css/app.css. Se conserva la regla x-cloak utilizada por Alpine.
- Importación de los plugins JavaScript de Bootstrap desde resources/js/app.js y exposición mediante window.bootstrap. Los atributos data-bs-* utilizan los manejadores oficiales; tooltips y popovers deberán inicializarse explícitamente cuando se incorporen.
- Livewire mantiene su integración ESM con Vite, su Alpine incorporado y la URL de actualización generada por Laravel. No se agregaron scripts duplicados de Alpine ni Livewire.
- Los layouts principales utilizan fondos y contenedores Bootstrap. La página de inicio reutiliza el layout de invitados, carga los assets compartidos y presenta botones Bootstrap con enlaces de acceso y registro condicional. El enlace de perfil del panel provisional también utiliza Bootstrap.
- Se retiraron de los layouts las solicitudes externas de la fuente Figtree del scaffolding. Bootstrap utiliza por ahora su tipografía de sistema.
- No se instaló jQuery, Tailwind ni Sass. El CSS ya compilado de Bootstrap cubre esta fase; la personalización de Sneat se resolverá con sus assets oficiales en la fase correspondiente.
- No se ejecutaron migraciones ni cambios de datos sobre memorylab. La navegación y los formularios de Jetstream siguen pendientes de adaptación visual.
- README actualizado con el estado y el uso de Bootstrap.

## Verificación

- npm run build: compilación Vite correcta con Bootstrap, Popper y Livewire.
- npm ls: Bootstrap 5.3.8 y Popper 2.11.8, sin duplicados de Popper.
- npm audit: cero vulnerabilidades reportadas al cerrar esta fase.
- node --check resources/js/app.js: sintaxis correcta.
- artisan view:cache: vistas Blade compiladas correctamente; se limpió después la caché de vistas.
- artisan test, utilizando memorylab_testing: 26 pruebas aprobadas, 66 assertions y un caso condicional omitido porque el registro está activo. La desactivación del registro tiene una prueba independiente que pasó.
- Apache: inicio, login, registro y recuperación devolvieron HTTP 200. Se comprobó que todas las páginas cargan assets con el prefijo /sistemg3/public y conservan la URL correcta de actualización de Livewire.
- Los archivos CSS y JavaScript compilados devolvieron HTTP 200. El CSS servido contiene Bootstrap 5.3.8 y las clases container y btn-primary; el JavaScript servido incluye Bootstrap y window.bootstrap.
- El HTML de inicio contiene el contenedor responsive y los botones Bootstrap. Esta comprobación fue HTTP; no se realizó una inspección visual automatizada en navegador.

## Referencias oficiales

- [Bootstrap con Vite](https://getbootstrap.com/docs/5.3/getting-started/vite/).
- [Plugins JavaScript de Bootstrap](https://getbootstrap.com/docs/5.3/getting-started/javascript/).

## Corrección visual solicitada después del cierre

El usuario mostró una captura de /register con el logo ocupando casi toda la pantalla y controles sin formato. La revisión confirmó que el CSS Bootstrap se sirve correctamente con HTTP 200 y Content-Type text/css, pero la tarjeta y los controles seguían usando clases de Tailwind. En particular, size-16 no tenía estilos y el SVG carecía de dimensiones explícitas. La comprobación HTTP inicial no había detectado este problema visual.

Se corrigió la base Bootstrap sin iniciar la integración de Sneat:

- Logo con width y height explícitos de 64 píxeles, viewBox correcto y enlace de inicio generado por Laravel para conservar /sistemg3/public.
- Tarjeta centrada, con ancho máximo de 28rem y ancho adaptable al dispositivo.
- Componentes compartidos input, label, button, checkbox y validation-errors adaptados a Bootstrap, conservando atributos, bindings, validación y acciones.
- Filas de acciones de registro, login, recuperación, restablecimiento y confirmación adaptadas a flex de Bootstrap; las filas con enlaces pueden distribuirse en varias líneas.
- Nueva compilación Vite y limpieza de vistas compiladas.

Se verificó en Chrome headless con un perfil temporal separado del perfil del usuario. En registro, el logo mide 64 × 64 píxeles y la tarjeta está centrada, sin desbordamiento horizontal, en anchos de 1366, 390 y 320 píxeles. Bootstrap y Livewire están cargados y no hubo excepciones JavaScript. Login y recuperación también muestran el logo y los campos corregidos. Se revisaron las capturas de escritorio y móvil:

- [Registro en escritorio](registro-bootstrap-escritorio.png).
- [Registro en móvil](registro-bootstrap-movil.png).

La suite existente volvió a pasar: 26 pruebas, 66 assertions y un caso condicional omitido. No se modificaron datos de la aplicación ni se avanzó a otra fase.

## Cierre

Fase 3 completada. La Fase 4, integración de Sneat, requiere la siguiente autorización del usuario según el plan original.

Commit sugerido: `feat: integrate Bootstrap 5 with Vite`.
