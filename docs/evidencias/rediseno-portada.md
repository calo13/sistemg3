# Nueva portada e identidad visual

Fecha: 2026-10-09.

## Solicitud

El usuario pidió mejorar la página principal, incorporar el logo de la Universidad Mariano Gálvez y los cinco integrantes, explicar conceptos como segmentación y retirar el crédito visible «Diseño Sneat por ThemeSelection». Eligió expresamente la paleta **azul marino, rojo y blanco**.

## Resultado

- Portada con navegación a conceptos, secuencia de uso e integrantes, y acceso al laboratorio según la sesión.
- Ejemplo visual de RAM con ocho marcos, cuatro ocupados y una pequeña tabla de páginas. Está identificado como ejemplo y no representa el estado de un escenario guardado.
- Explicaciones breves de paginación, segmentación y traducción de direcciones; aviso de que la memoria es simulada.
- Tres pasos de inicio y los nombres/carnés de los cinco integrantes obtenidos de `config/memorylab.php`, conservando los ceros iniciales.
- Colores principales azul marino `#16324f`, rojo `#c63d44` y blanco, aplicados también a autenticación y al simulador. Los estados conservan colores semánticos y textos legibles.
- Logo original de la UMG, servido desde el propio proyecto y conservando sus proporciones. [Procedencia y validación](../../public/images/UMG-SOURCE.md).
- Pies de página con identidad académica. Los avisos de autoría y la licencia MIT de Sneat permanecen en los archivos distribuidos. [Procedencia de Sneat](../../resources/vendor/sneat/PROVENANCE.md).

La portada incluye acceso por teclado, enlace para saltar al contenido, foco visible y adaptación a movimiento reducido. El cambio mantiene los formularios y las acciones existentes del simulador.

## Verificación

- Compilación de recursos con Vite aprobada y `git diff --check` sin errores.
- **89 pruebas aprobadas, una omisión condicional y 356 aserciones**, en 38,45 segundos. Se ejecutaron pruebas existentes de portada, autenticación, registro, recuperación, perfil, paginación y segmentación contra la base separada `memorylab_testing`.
- Navegador Chrome: portada a 1366, 768, 390 y 320 píxeles; login a 1366 y 390. No se detectaron desbordamiento horizontal, imágenes rotas, iconos sin máscara, errores de JavaScript ni respuestas HTTP 4xx/5xx.
- Las cuatro medidas de portada mostraron cinco integrantes, ocho marcos del ejemplo, enlaces internos válidos y ausencia del crédito visual anterior.
- Revisión visual de la página completa, integrantes y login, en escritorio y móvil. No se iniciaron sesiones ni se ejecutaron acciones sobre escenarios durante esta comprobación.

Capturas verificadas:

- [Portada en escritorio](portada-nueva-escritorio.png).
- [Portada en móvil](portada-nueva-movil.png).

## Actualización en el hosting

La implementación y las comprobaciones se realizaron localmente. No se accedió al hosting ni se verificó allí esta nueva apariencia.

Desde la carpeta del proyecto en el servidor:

```sh
git pull --ff-only origin main
npm ci
npm run build
php artisan optimize:clear
```

`public/build` no se versiona: actualizar solo el código no reemplaza los estilos compilados. Si el hosting no dispone de Node/npm, se puede sustituir la compilación por la carga de los recursos ya compilados: extraer la carpeta `build/` del paquete entregado dentro de `public/`, de forma que quede `public/build/manifest.json` junto a `public/build/assets/`. Después ejecutar `php artisan optimize:clear` y recargar el navegador.

El paquete incluye exclusivamente recursos públicos compilados y su manifiesto. Este cambio visual no añade migraciones.
