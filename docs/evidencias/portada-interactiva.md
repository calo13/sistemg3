# Portada web y microinteracciones

Fecha: 2026-10-12.

## Solicitud

El usuario pidió una portada más atractiva y con más animaciones: botones con cambio de color, sombra y escala de 1,05; subrayado del menú desde la izquierda; tarjetas que se eleven; un spinner CSS durante la carga y un pequeño pulso en una acción.

## Diseño y comportamiento

- La presentación ocupa una franja azul marino a todo el ancho, con el escudo original, título blanco y acción roja. Se mantiene Public Sans, peso 500 y un máximo de 44 píxeles para el título. Se conservan los colores elegidos y los componentes de Sneat.
- El encabezado permanece visible al desplazarse. Sus tres enlaces aparecen también en móvil y muestran una línea roja que crece desde la izquierda al pasar el cursor o enfocarlos por teclado.
- Los botones cambian suavemente de color y sombra, se elevan 2 píxeles y alcanzan una escala de 1,05 con cursor preciso. El icono de la acción principal pulsa una vez, durante 450 ms; no es un contador de «Me gusta» ni registra acciones.
- Las tarjetas se elevan 6 píxeles, profundizan su sombra y muestran un acento rojo. Cada una incorpora un enlace a su módulo; los visitantes acceden primero al inicio de sesión.
- Los cinco bloques de contenido entran juntos con una variación breve de opacidad y un desplazamiento de 12 píxeles, durante 420 ms. Se ejecuta una vez por bloque y el contenido permanece visible desde la carga inicial.
- El spinner CSS aparece en un aviso discreto al navegar a otra ruta local. La navegación permanece nativa y comienza inmediatamente; no se añade espera artificial ni se tapa o bloquea la portada. Anclas, descargas, otras pestañas y clics con modificadores conservan su comportamiento.
- El aviso se restaura al volver con el historial, cambiar de página o cancelar la navegación. Un límite de recuperación evita dejar el enlace marcado como ocupado después de una navegación fallida.
- Movimiento reducido desactiva desplazamiento, escalado, pulso y giro del spinner. Los cambios de color y el aviso textual permanecen. Sin JavaScript se conservan el contenido y la navegación.

## Verificación

- Vite y la revisión de sintaxis del módulo JavaScript aprobaron.
- **9 pruebas aprobadas y 30 aserciones**, en **21,17 segundos**: portada y autenticación, incluida entrada al panel. Se utilizó la base separada `memorylab_testing`.
- La portada se comprobó a **1366, 768, 390 y 320 píxeles**, conservando los cinco integrantes, el escudo y el icono de pestaña, sin desbordamiento horizontal ni imágenes rotas. Se revisaron las páginas completas y las capturas de escritorio y móvil.
- Se comprobaron los cambios reales de color, sombra, escala y posición con hover, el subrayado desde la izquierda y el pulso único en la acción principal.
- El spinner se comprobó durante una navegación nativa al login: se retuvo brevemente su respuesta en el navegador de pruebas, sin modificar el servidor ni introducir espera en la aplicación. El aviso se mostró con `ml-spin` activo; la navegación llegó al formulario y el regreso con Atrás restauró la portada desde la caché del navegador con el aviso oculto.
- Anclas y clics con modificadores mantuvieron el aviso oculto. Movimiento reducido desactivó transformaciones y animaciones; con JavaScript deshabilitado permanecieron el contenido, los cinco integrantes y los enlaces. No se registraron excepciones JavaScript ni respuestas HTTP 4xx/5xx.
- No se inició sesión ni se ejecutaron acciones sobre escenarios, procesos o memoria durante la comprobación de navegador.

Capturas:

- [Portada en escritorio](portada-interactiva-escritorio.png).
- [Portada en móvil](portada-interactiva-movil.png).
- [Indicador durante la navegación](portada-interactiva-carga.png).

## Actualización del hosting

```sh
git pull --ff-only origin main
npm ci
npm run build
php artisan optimize:clear
```

Si el hosting no dispone de npm, cargar la carpeta `build/` del paquete de recursos compilados actualizado dentro de `public/`, en lugar de ejecutar los dos comandos npm. Los cambios no requieren migraciones ni seeders. La revisión se realiza localmente y conserva los escenarios del simulador.
