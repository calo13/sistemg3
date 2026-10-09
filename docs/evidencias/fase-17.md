# Fase 17 — Traducción de direcciones

Fecha de cierre: **8 de octubre de 2026**.

## Autorización y alcance

Continúa la autorización del usuario para completar las fases en orden sin nuevas confirmaciones. La Fase 17 agrega una consulta educativa que descompone direcciones lógicas en página y offset, encuentra el marco actual y calcula la dirección física cuando la página está presente.

El cálculo utiliza datos reales del simulador y conserva memoria e historial. La carga y reemplazo de páginas siguen correspondiendo a Paginación.

## Comportamiento implementado

- AddressTranslationService exige los permisos actuales results.view, memory.view, tables.view y simulations.view, y consulta un snapshot transaccional.
- Escenario y proceso se seleccionan explícitamente. Se validan pertenencia, geometría y numeración de páginas.
- La entrada es una dirección lógica entera en bytes desde cero. El límite es size_bytes − 1; se rechazan negativos, el primer byte fuera del proceso y el relleno de la última página.
- Página = floor(dirección lógica / tamaño de página). Offset = dirección lógica % tamaño de página.
- La tabla determina frame_number. Si la página está presente, dirección física = frame_number × tamaño de página + offset. El marco cero se conserva como un valor válido.
- Si la página está ausente, marco y dirección física son null. La interfaz permite volver a Paginación para solicitar la página y recalcular después.
- Los tres roles pueden consultar con sus permisos. El resultado está protegido mediante Locked y se limpia al cambiar escenario, proceso o dirección.
- Traducir no registra eventos CPU, no asigna marcos y no cambia presencia, estados o fechas FIFO. No agrega columnas ni tablas.

## Verificación

- AddressTranslationTest: **29 pruebas aprobadas, 97 assertions**.
- Pint, caché de vistas Blade y revisión de rutas: **aprobados**.
- Chrome verificó formulario y cálculo para Administrador, Operador y Observador.
- En el fixture después de cargar la página, dirección lógica **1500** produjo página **1**, offset **476**, marco **0** y dirección física **476**.
- La dirección **2500** correspondió a una página ausente y no produjo dirección física. La dirección **4096** fue rechazada en un proceso de **4 KB**.
- La firma de las siete tablas del dominio permaneció igual durante las consultas de traducción.
- Se comprobaron anchos de 1366, 390 y 320 píxeles, sin errores JavaScript. Los datos temporales del navegador se limpiaron.

## Capturas

- [Traducción en escritorio](fase-17-traduccion-escritorio.png).
- [Traducción en móvil](fase-17-traduccion-movil.png).

## Uso y continuación

Abre Traducción en el menú, selecciona un escenario de paginación y después su proceso. Introduce una dirección dentro del rango mostrado y pulsa Traducir dirección. La pantalla explica el cálculo y el estado de la página consultada. Si otra operación cambia la asignación, vuelve a calcular para consultar su nueva ubicación.

Fase 17 completada con pruebas y navegador verificados. Continúa la Fase 18, segmentación, según la autorización general.

Commit sugerido: `feat: translate logical paging addresses without memory mutations`.
