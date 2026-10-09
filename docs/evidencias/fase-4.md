# Fase 4 — Integración de Sneat

## Autorización y alcance

Solicitud del usuario: «continua», después de completar Bootstrap y corregir la presentación de registro. Se continúa con la siguiente fase del plan original: integración de Sneat.

Se revisaron las entradas de Vite, layouts, componentes, rutas y dependencias del proyecto. La integración incorpora los assets oficiales y la estructura básica de navegación. La adaptación visual completa de autenticación corresponde a la Fase 5 y el contenido definitivo del dashboard a la Fase 7.

## Procedencia

Se descargó el [repositorio oficial de ThemeSelection](https://github.com/themeselection/sneat-bootstrap-html-laravel-admin-template-free), que declara Sneat Free 2.0.0, en el commit `66f35780e852f4323868055f25b3e5dada8261bb`.

Los assets seleccionados se conservan en resources/vendor/sneat junto con las licencias y PROVENANCE.md. Los 64 archivos SCSS y los módulos helpers.js y menu.js coinciden por hash con los originales. No se copiaron .env, configuración Laravel, controladores, rutas, migraciones ni cuentas de demostración del repositorio.

## Implementación

- El core SCSS oficial de Sneat se compila desde resources/scss/app.scss con Vite. Incluye Bootstrap y su personalización: se retiró la importación adicional del CSS Bootstrap para evitar duplicarlo.
- El layout autenticado adapta la estructura oficial contentNavbarLayout: layout-wrapper, layout-container, menú vertical, navbar, content-wrapper, footer y overlay móvil.
- Los partials de menú y navbar enlazan únicamente con las rutas existentes: Inicio, Mi perfil y logout. Logout mantiene POST y CSRF. No se incluyeron búsqueda ficticia, estadísticas ni módulos sin implementar.
- El footer acredita a ThemeSelection con el enlace solicitado por el proyecto oficial y se muestra también en el layout de invitados.
- Los módulos originales Helpers y Menu se integran como ES modules. La inicialización es idempotente y admite el evento livewire:navigated. Los controles móviles mantienen aria-controls y aria-expanded; overlay y Escape permiten cerrar el menú.
- Perfect Scrollbar 1.5.6 acompaña al menú oficial; Bootstrap 5.3.8 y Popper existentes se conservan. No se instaló jQuery.
- Public Sans, instalada con @fontsource/public-sans 5.3.0, se compila localmente en pesos 400, 500, 600 y 700. Se utiliza un subconjunto de las reglas oficiales de Boxicons para los iconos del menú, sin consultas a servicios externos.
- Se conservaron las licencias de Sneat, Boxicons, Perfect Scrollbar y Public Sans.
- No se ejecutaron migraciones, seeders ni cambios de configuración de credenciales. Las cuentas temporales de verificación se eliminaron junto con sus sesiones.

## Hallazgos resueltos

- Sass 1.76.0, fijado por la plantilla original, incorporaba una dependencia de vigilancia de archivos con avisos npm. Se actualizó a Sass 1.105.1 y se comprobó la compilación. Los avisos de deprecación de imports y funciones Sass usados por las fuentes oficiales se silencian expresamente en Vite; no se modificaron las fuentes originales. El audit final no reporta vulnerabilidades.
- Aunque el constructor del menú admite desplazamiento nativo, manageScroll presupone Perfect Scrollbar al cambiar el tamaño de pantalla. La prueba en navegador detectó excepciones al pasar a móvil; se incorporó la dependencia oficial y desaparecieron.
- En 320 píxeles, la navbar desbordaba por el título y el nombre de cuenta. Se limita el ancho del nombre y se oculta el título del curso en pantallas estrechas. Se comprobó nuevamente en 390 y 320 píxeles.
- Las URLs de fuentes generadas inicialmente por Vite apuntaban a /build y fallaban bajo la subcarpeta de Apache. Se configuró base relativa durante build, siguiendo la [documentación oficial de Vite](https://vite.dev/guide/build#relative-base). La prueba final del navegador confirmó que los recursos necesarios cargan correctamente.

## Verificación

- npm run build: compilación final correcta.
- npm audit: cero vulnerabilidades reportadas.
- node --check para app.js y sneat.js: sintaxis correcta.
- artisan view:cache: vistas compiladas; se limpió después la caché de vistas.
- artisan test sobre memorylab_testing: 26 pruebas aprobadas, 66 assertions y un caso condicional omitido porque el registro está activo. La prueba independiente de registro deshabilitado pasó.
- Los diez assets registrados en el manifiesto, incluyendo las fuentes, respondieron HTTP 200 desde /sistemg3/public/build; también se comprobaron los CSS asociados.
- Chrome headless, con un perfil temporal separado: login real, menú oficial inicializado, dropdown que abre y cierra, y layout de escritorio sin desbordamiento. Menú móvil correcto en 390 y 320 píxeles, con aria-expanded sincronizado; overlay y Escape lo cierran.
- La prueba de Chrome actualizó realmente el perfil mediante Livewire y comprobó su persistencia en MySQL. Tras logout, el acceso al dashboard redirigió al login.
- Registro y recuperación se comprobaron en 320 píxeles: sin desbordamiento y con el logo de 64 píxeles conservado.
- La ejecución final no detectó excepciones JavaScript ni recursos necesarios fallidos. La cuenta aleatoria y sus sesiones fueron eliminadas al terminar cada ejecución, sin modificar cuentas existentes.
- Capturas revisadas: [panel de escritorio](sneat-panel-escritorio.png) y [menú móvil](sneat-menu-movil.png).

## Cierre

Fase 4 completada. No se inició la Fase 5: autenticación visual de Sneat.

Commit sugerido: `feat: integrate official Sneat theme and navigation`.
