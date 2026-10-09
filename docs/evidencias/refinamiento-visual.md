# Refinamiento de portada y color del panel

Fecha: 2026-10-09.

## Solicitud

El usuario pidió mejorar los efectos de carga y el diseño de la portada. Durante el ajuste añadió una captura del dashboard y señaló que sus colores se veían apagados. Solicitó realizar un commit al terminar. Se mantuvieron la paleta elegida anteriormente —azul marino, rojo y blanco—, el escudo original de la UMG y la plantilla Sneat.

## Cambios

- La portada reúne título, explicación, acciones y escudo en una sola tarjeta. El lado de presentación tiene fondo azul marino; el escudo conserva un fondo blanco. El título utiliza Public Sans, peso 500 y un máximo de 38 píxeles.
- Los conceptos utilizan tres tarjetas de Sneat, los pasos una lista numerada y los integrantes una tabla. Se conservan los cinco nombres y carnés de la configuración académica, incluidos sus ceros iniciales.
- La entrada tiene cinco grupos completos. Cada uno cambia su opacidad de 0,92 a 1 durante 220 ms, una sola vez al entrar en pantalla. No hay desplazamiento, escalado, retrasos individuales ni contenido oculto al cargar. El encabezado permanece estático.
- Se mantienen efectos discretos en acciones y tarjetas y foco visible por teclado. La preferencia de movimiento reducido desactiva las entradas y transiciones; sin JavaScript la portada sigue visible.
- El dashboard presenta una bienvenida azul con borde rojo, el escudo, la universidad, la carrera, el curso y el grupo. El menú activo utiliza azul marino con texto blanco y una marca roja.
- Los ocho indicadores tienen una línea superior e iconos de color: azul para RAM, azul verdoso para marcos, verde para procesos activos y rojo para fallos de página. El botón de selección de escenario utiliza el estilo primario de Sneat.
- La revisión de capturas detectó que `.avatar .avatar-initial` de Sneat sobrescribía el fondo de los iconos. Se corrigió la prioridad dentro del layout autenticado, conservando los archivos originales de la plantilla.
- Los valores, permisos, consultas, actualizaciones periódicas y acciones del simulador conservan su comportamiento. Sin escenario seleccionado se muestran guiones; en segmentación se mantiene «No aplica» donde corresponde.

## Verificación

- Compilación de recursos con Vite y revisión de sintaxis del módulo de movimiento aprobadas.
- **75 pruebas aprobadas y 399 aserciones**, en **10,98 segundos**: portada, autenticación, configuración/estadísticas de memoria y roles/permisos. Las pruebas utilizan `memorylab_testing`.
- Chrome comprobó portada y panel a **1366, 768, 390 y 320 píxeles**, sin desbordamiento horizontal ni imágenes rotas. Se revisaron visualmente las capturas de escritorio y las páginas completas en móvil.
- Se comprobó Public Sans cargada, peso/tamaño del título, colores reales del panel, los cinco integrantes, icono de pestaña y enlaces de navegación.
- Se observaron cinco entradas por bloques, sin contenido oculto ni movimiento de posición. Movimiento reducido produjo cero entradas; con JavaScript deshabilitado permanecieron los textos, acciones, escudo e integrantes.
- El menú móvil abrió correctamente. No se registraron excepciones JavaScript ni respuestas HTTP 4xx/5xx durante la revisión.
- El panel se consultó con la cuenta local existente. Las comprobaciones no crearon escenarios, procesos ni eventos: se conservaron **7 escenarios, 16 procesos y 128 eventos** antes y después.

Capturas de esta revisión:

- [Portada en escritorio](portada-institucional-escritorio.png).
- [Portada en móvil](portada-institucional-movil.png).
- [Panel en escritorio](panel-color-escritorio.png).
- [Panel en móvil](panel-color-movil.png).

## Actualización del hosting

Los cambios están compilados y comprobados localmente. Para aplicarlos en el hosting:

```sh
git pull --ff-only origin main
npm ci
npm run build
php artisan optimize:clear
```

Si el hosting no dispone de npm, cargar la carpeta `build/` del paquete de recursos compilados actualizado dentro de `public/`, en lugar de ejecutar los dos comandos npm. El escudo permanece en `public/images/umg-logo.png`, incluido en Git. Este ajuste visual no requiere migraciones ni seeders. No se accedió al hosting durante esta revisión.
