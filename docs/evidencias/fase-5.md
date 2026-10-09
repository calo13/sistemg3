# Fase 5 — Autenticación visual con Sneat

## Autorización y alcance

Solicitud del usuario: «continua», después del cierre de la Fase 4. Se implementa la adaptación visual de autenticación prevista en el plan original.

Se revisaron las vistas de Fortify/Jetstream, layouts, componentes de perfil, reglas de contraseña y las plantillas básicas de autenticación de Sneat del commit `66f35780e852f4323868055f25b3e5dada8261bb`, ya descargado y registrado en PROVENANCE.md.

## Cambios

- Login, registro, solicitud de recuperación, restablecimiento y confirmación de contraseña utilizan la estructura oficial authentication-wrapper/authentication-basic/authentication-inner y la tarjeta de Sneat.
- Se compila el archivo original pages/page-auth.scss, incluyendo sus detalles decorativos y comportamiento responsive, sin modificar los archivos SCSS del proveedor.
- La marca se muestra dentro de la tarjeta, con MemoryLab y el logo con dimensiones explícitas de 32 × 32 píxeles. El layout de invitados conserva un único main y el footer con los créditos de ThemeSelection.
- Títulos de página, encabezados, etiquetas, descripciones, botones y enlaces están en español. Las traducciones de mensajes de autenticación, validación, recuperación, perfil y notificación de restablecimiento se encuentran en lang/es y lang/es.json.
- Se agregó password-input como componente compartido para los formularios de autenticación. Conserva type=password sin JavaScript y permite alternar visibilidad mediante Alpine, con botones type=button, aria-controls, aria-label y aria-pressed. Los iconos show/hide proceden del mismo CSS oficial de Sneat.
- Se mantienen los nombres de campos, rutas POST, CSRF, valores anteriores de nombre/correo, autocomplete, confirmación de contraseña del registro, token de recuperación y condiciones de términos de Jetstream.
- El enlace Crear cuenta en login solo aparece cuando la ruta register está habilitada. No se modificó AUTH_REGISTRATION_ENABLED ni las reglas de contraseña.
- El perfil utiliza secciones y tarjetas Bootstrap/Sneat: información personal, cambio de contraseña y sesiones. Se adaptaron los componentes compartidos de formularios, títulos, mensajes y botón secundario, conservando los bindings y acciones Livewire.
- Los iconos de dispositivos tienen tamaño explícito de 32 píxeles para evitar el problema de SVG sin dimensiones observado en fases anteriores.
- El diálogo de cierre de otras sesiones utiliza los estilos de modal de Sneat. Conserva entangle, focus trap, bloqueo de scroll y cierre por Escape de Alpine, sin crear un segundo controlador Bootstrap para el mismo estado. El contenedor incluye la clase modal requerida por los estilos oficiales y una regla específica de display que permite a x-show controlar su visibilidad.
- No se instalaron paquetes adicionales ni se modificaron configuración de correo, credenciales, migraciones, roles o permisos.

## Verificación

- npm run build: compilación final correcta.
- npm audit: cero vulnerabilidades reportadas.
- artisan view:cache: compilación correcta; se limpió después la caché de vistas.
- Pint: traducciones PHP y prueba de registro deshabilitado con formato correcto. lang/es.json se pudo interpretar correctamente.
- Suite existente: 26 pruebas aprobadas y un caso condicional omitido porque el registro está activo. Incluye autenticación, registro, recuperación, confirmación, cambio de contraseña, sesiones y perfil.
- Se amplió RegistrationDisabledTest para comprobar que login no muestra Crear cuenta al deshabilitar el registro. La comprobación específica pasó con cinco assertions, incluyendo GET/POST deshabilitados y los enlaces en inicio y login.
- Chrome headless con un perfil temporal: login, registro, recuperación y restablecimiento comprobados en anchos de 1366, 390 y 320 píxeles; tarjetas centradas, un h1 por pantalla y sin desbordamiento horizontal.
- El control de contraseña alterna entre password/text y sincroniza aria-pressed. Un login inválido muestra el error en español y no repuebla la contraseña.
- Login válido, menú responsive, dropdown de cuenta y logout funcionaron en el navegador real. Tras logout, dashboard redirige al login.
- El perfil guardó una actualización real por Livewire en MySQL. Su diálogo abre, enfoca la contraseña y permite cancelar. Perfil y diálogo también se comprobaron en 390 y 320 píxeles; Escape cierra el diálogo y no se observó desbordamiento.
- La confirmación de contraseña se completó con el formulario adaptado.
- La ejecución final no detectó excepciones JavaScript ni recursos necesarios fallidos. Las cuentas aleatorias de verificación y sus sesiones se eliminaron al terminar, sin modificar cuentas existentes.

## Capturas revisadas

- [Login de escritorio](fase-5-login-escritorio.png).
- [Registro de escritorio](fase-5-register-escritorio.png).
- [Registro móvil](fase-5-registro-movil.png).
- [Recuperación](fase-5-forgot-password-escritorio.png).
- [Restablecimiento](fase-5-reset-password-escritorio.png): se utilizó un token ficticio para revisar la vista; el flujo válido está cubierto por las pruebas de recuperación.
- [Perfil](fase-5-perfil-escritorio.png).
- [Diálogo de sesiones](fase-5-confirmar-sesiones.png).
- [Diálogo móvil](fase-5-sesiones-movil.png).
- [Confirmación de contraseña](fase-5-confirm-password-escritorio.png).

## Cierre

Fase 5 completada. La Fase 6, roles y permisos, queda pendiente de la siguiente autorización según el plan original.

Commit sugerido: `feat: adapt authentication and profile views to Sneat`.
