# Sneat Free — ThemeSelection

Repositorio: https://github.com/themeselection/sneat-bootstrap-html-laravel-admin-template-free

Versión declarada: 2.0.0.
Commit utilizado: `66f35780e852f4323868055f25b3e5dada8261bb`.

Los archivos de `scss/` proceden de `resources/assets/vendor/scss/`. Los archivos `js/helpers.js` y `js/menu.js` proceden de `resources/assets/vendor/js/`. Se conservan sin cambios.

`icons/boxicons.css` contiene un subconjunto de las reglas oficiales de `resources/assets/vendor/fonts/iconify/iconify.css`, para los iconos utilizados en el menú, los controles de visibilidad de contraseña y el dashboard académico. En la Fase 7 se agregaron las reglas originales bx-chip, bx-grid-alt, bx-error-circle, bx-group, bx-data y bx-transfer-alt. Para agregar iconos, extraer sus reglas desde el mismo commit; no requiere un servicio externo ni ejecutar Iconify en el navegador.

Las plantillas de `resources/views/layouts/app.blade.php` y `resources/views/layouts/partials/sneat-*` adaptan la estructura oficial de `contentNavbarLayout.blade.php` y sus secciones de menú, navbar y footer. Utilizan rutas y autenticación de MemoryLab. El contenido de demostración, las cuentas ficticias, la búsqueda sin funcionalidad y los enlaces comerciales no se incorporan.

`resources/js/sneat.js` adapta la inicialización del menú de `resources/assets/js/main.js` utilizando los módulos originales y Perfect Scrollbar, dependencia oficial necesaria para el cambio de tamaño del menú. No requiere jQuery.

`resources/css/app.css` incluye las dimensiones de marca adaptadas de `resources/assets/css/demo.css`. La tipografía Public Sans se distribuye mediante `@fontsource/public-sans` y se compila localmente con Vite.

Desde la Fase 5, `resources/views/components/authentication-card.blade.php` y las vistas de `resources/views/auth` adaptan las plantillas oficiales `content/authentications/auth-login-basic`, `auth-register-basic` y `auth-forgot-password-basic`. Se reutiliza el archivo SCSS original `pages/page-auth.scss`. La adaptación conserva las rutas POST, CSRF, campos y acciones de Fortify/Jetstream y agrega la identidad MemoryLab, textos en español y controles accesibles para mostrar contraseñas. El perfil y su diálogo utilizan los estilos Bootstrap/Sneat con el estado y bindings originales de Livewire/Alpine.

Desde la Fase 7, `resources/views/dashboard.blade.php` y `resources/views/livewire/dashboard-stats.blade.php` adaptan las tarjetas de bienvenida e indicadores de `content/dashboard/dashboards-analytics.blade.php`. La adaptación usa la información académica de MemoryLab, una tabla de integrantes y el estado sin escenario; conserva el grid, las cards, avatars, badges, tabla y progress de Sneat/Bootstrap. No incorpora métricas comerciales, gráficos ni cifras de demostración de la plantilla.

Licencias conservadas: `LICENSE` (Sneat, MIT), `icons/LICENSE-boxicons` (Boxicons, MIT), `LICENSE-perfect-scrollbar` (Perfect Scrollbar, MIT) y `LICENSE-public-sans` (Public Sans, SIL Open Font License). El footer acredita y enlaza a ThemeSelection.

No se copiaron `.env`, configuración de Laravel, migraciones, controladores ni dependencias de demostración del repositorio.
