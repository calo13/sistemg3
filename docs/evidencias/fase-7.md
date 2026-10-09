# Fase 7 — Layout y dashboard académico

## Solicitud y alcance

El usuario autorizó la Fase 7 después de completar roles y permisos e indicó que el dashboard debe identificar a la Universidad Mariano Gálvez, la carrera Ingeniería en Sistemas y a los siguientes integrantes:

| Integrante | Carné |
| --- | --- |
| Enmer Antonio Buch Xinic | 1990-22-15514 |
| Lester Emilio Pedro Juan | 6590-23-7214 |
| Deivid Alberto Guerra Carpio | 0905-24-23552 |
| Rebeca Alizon Najarro Duarte | 3190-23-11451 |
| Carlos Lopez Urizar | 6590-24-18604 |

Se conserva Sistemas Operativos 1 y Grupo 3 de la solicitud inicial. Se implementa únicamente el layout y dashboard. El modelo de memoria, migrations y funcionalidades del simulador corresponden a las fases siguientes.

## Diseño y cambios

- Se revisaron las tarjetas originales de `dashboards-analytics.blade.php` de Sneat Free 2.0.0, commit `66f35780e852f4323868055f25b3e5dada8261bb`, ya disponible en el proyecto.
- La tarjeta de presentación identifica universidad, carrera, MemoryLab, curso y grupo. Un enlace lleva a los integrantes.
- El controlador DashboardController entrega la información académica desde `config/memorylab.php`. La universidad, carrera, curso, grupo, nombres y carnés se mantienen en una única configuración.
- Los carnés se guardan como cadenas para conservar el cero inicial de `0905-24-23552`. No se crean cuentas de usuario a partir de los integrantes.
- DashboardStats separa los indicadores en un componente Livewire: RAM total, utilizada y disponible; marcos totales, ocupados y libres; procesos activos y Page Faults.
- El estado inicial muestra «—», «Sin escenario cargado» y «Sin datos». No se generan cifras de simulación. La barra de RAM no publica un porcentaje ni aria-valuenow mientras no exista un escenario.
- Los totales y la utilización se conectarán a los Services de memoria al implementar esos módulos. No se añade polling sin datos disponibles.
- La tabla de integrantes incluye encabezados y caption accesible, con nombres en su escritura original y carnés completos. Adapta el ancho y las líneas en móvil.
- La tarjeta de contenido académico presenta paginación, segmentación y comparación de asignación como próximos contenidos, sin enlaces a rutas inexistentes.
- Se reutilizan el grid, cards, avatars, badges, tabla, progress e iconografía de Sneat/Bootstrap. No se incorporan gráficos comerciales, valores del dashboard de ejemplo ni nuevas dependencias.
- Los seis iconos nuevos se extrajeron de las reglas originales de Iconify/Boxicons del mismo commit. Se amplió el subconjunto local y se actualizó PROVENANCE.md, conservando las licencias.
- Sidebar: marca MemoryLab con UMG y Grupo 3; Inicio e Integrantes bajo Proyecto; Usuarios y roles bajo Administración, solo con permiso; Mi perfil bajo Cuenta.
- Navbar: UMG, curso, nombre de sesión y rol en el menú de cuenta. Se preserva el cierre de sesión POST con CSRF.
- Layout: títulos de página para dashboard, perfil y administración; enlace de teclado Ir al contenido; un único main. Se mantiene el menú oficial responsive y la configuración de Livewire para la subcarpeta de Apache.
- Footer: MemoryLab, UMG, grupo y créditos de ThemeSelection.

## Verificación

- Pint: formato correcto de los archivos PHP modificados.
- npm run build: compilación correcta de SCSS, CSS, iconos y JavaScript existentes.
- artisan view:cache: todas las vistas Blade compilaron correctamente; se limpió esa caché después de la comprobación.
- Suite existente: 41 pruebas aprobadas, 164 assertions y un caso condicional omitido por estar habilitado el registro. Se preservaron autenticación, perfil, permisos y restricciones de administración.
- Chrome headless con cuenta y perfil temporales, desde `http://localhost/sistemg3/public/dashboard`.
- Comparación de la tabla renderizada con los cinco nombres y carnés indicados por el usuario: coincidencia exacta, incluido el cero inicial.
- Título de dashboard e identidad de universidad y carrera correctos; un solo h1 y un solo main.
- Ocho indicadores sin cifras ficticias y barra sin porcentaje calculado ni aria-valuenow.
- Una actualización real de DashboardStats por Livewire respondió HTTP 200 desde `/sistemg3/public/livewire/update`.
- Comprobación en anchos de 1366, 768, 390 y 320 píxeles: sin desbordamiento horizontal de la página, con todos los iconos locales cargados y créditos visibles.
- Menú en tableta y móvil: apertura correcta, aria-expanded sincronizado y cierre por Escape.
- Integrantes funciona desde el perfil, navega al dashboard y desplaza la vista al equipo.
- Los tres roles mostraron su etiqueta correcta en el menú de cuenta. El enlace administrativo apareció únicamente para Administrador y la pantalla de roles siguió funcionando con el layout actualizado.
- Logout y redirección de invitados al login correctos. El login conserva su logo de 32 píxeles y no desborda en 320 píxeles.
- Sin excepciones JavaScript ni recursos necesarios fallidos. La cuenta temporal, sesiones y relaciones de roles se eliminaron al terminar; no se modificaron cuentas existentes.
- No se instalaron paquetes, ejecutaron seeders, cambiaron credenciales ni añadieron migraciones en esta fase.

## Capturas revisadas

- [Dashboard de escritorio](fase-7-dashboard-escritorio.png).
- [Dashboard móvil](fase-7-dashboard-movil.png).

Las capturas muestran los datos académicos solicitados y una cuenta temporal de verificación; no incluyen datos de otras cuentas.

## Cierre

Fase 7 completada. La siguiente fase del plan es Fase 8, modelo MySQL y migrations, pendiente de autorización del usuario.

Commit sugerido: `feat: add UMG academic dashboard and team layout`.
