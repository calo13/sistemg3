# Ajuste de portada: UMG, tipografía y animaciones

Fecha: 2026-10-09.

## Solicitud

Después de revisar la primera portada, el usuario pidió retirar el bloque «Un vistazo a la RAM», colocar el logo de la universidad en ese espacio, respetar la tipografía y el tema de Sneat, animar los elementos de la página principal y añadir un icono de pestaña.

## Cambios

- Se retiró la ilustración de RAM y sus estilos. La tarjeta principal presenta el escudo original de la UMG, la universidad, la carrera, el curso y el grupo.
- Se mantiene **Public Sans**, la familia configurada en los archivos de Sneat. La portada utiliza los pesos locales 400, 500 y 600; el título principal tiene como máximo 46 píxeles, peso 600 y espaciado normal. Se eliminaron los pesos 750/800, los títulos desproporcionados y varias frases promocionales.
- Se reutilizan los botones `btn`, las tarjetas `card`, el fondo, los bordes, las sombras y la escala tipográfica de Bootstrap/Sneat. La paleta azul marino, rojo y blanco permanece.
- La entrada y el desplazamiento revelan progresivamente el encabezado, los textos, las acciones, la tarjeta de la universidad, los conceptos, los pasos, los integrantes y el cierre. Los botones, tarjetas, filas y escudo tienen efectos al pasar el cursor. No hay movimiento continuo.
- La animación usa `IntersectionObserver`, sin dependencias nuevas. Se desconecta al navegar y respeta movimiento reducido; sin JavaScript el contenido permanece visible. El foco por teclado revela las acciones antes de utilizarlas.
- Un componente compartido añade el icono de pestaña de la UMG y el color de tema a portada/autenticación, panel y presentación. Se reutiliza el PNG local original, sin alterar ni redibujar el escudo.

## Verificación

- Vite y la revisión de sintaxis del nuevo módulo JavaScript aprobaron.
- **18 pruebas aprobadas, una omisión condicional y 51 aserciones**, en 20,44 segundos: portada, autenticación, registro, recuperación y perfil. Se utilizó la base de pruebas separada `memorylab_testing`.
- Chrome verificó la portada a 1366, 768, 390 y 320 píxeles. Se observaron animaciones reales de entrada, efectos de cursor y foco por teclado; no hubo desbordamiento horizontal ni imágenes rotas. Los cinco integrantes y sus carnés permanecen.
- Se comprobó Public Sans cargada, título de peso 600, tamaño máximo de 46 píxeles y espaciado normal. Todos los botones y las tarjetas de conceptos conservan las clases de la plantilla.
- El icono respondió HTTP 200 como PNG de 57.582 bytes. Se comprobó también en el login a 1366 y 390 píxeles.
- Con movimiento reducido y con JavaScript deshabilitado, el contenido sigue visible. No se registraron errores de JavaScript ni respuestas HTTP 4xx/5xx en la comprobación.
- Revisión visual de la portada completa y de las capturas en escritorio/móvil.

Capturas:

- [Portada UMG en escritorio](portada-umg-escritorio.png).
- [Portada UMG en móvil](portada-umg-movil.png).

## Actualización

Los cambios se compilaron y verificaron localmente. Para aplicar esta versión en el hosting, actualizar el código y compilar los recursos antes de limpiar la caché:

```sh
git pull --ff-only origin main
npm ci
npm run build
php artisan optimize:clear
```

Si el servidor no dispone de npm, cargar el paquete de recursos compilados actualizado en lugar de los dos comandos npm: su carpeta `build/` debe quedar dentro de `public/`. El icono está en `public/images/umg-logo.png`, incluido en Git. No se necesitan migraciones para este cambio visual.

No se accedió al hosting ni se ejecutaron acciones sobre escenarios en las comprobaciones de navegador.
