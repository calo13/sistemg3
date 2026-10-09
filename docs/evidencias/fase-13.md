# Fase 13 — Mapa visual de RAM

## Autorización y alcance

Continúa la autorización del usuario para completar todas las fases en orden sin nuevas confirmaciones. La Fase 13 representa los marcos de RAM simulada y las páginas ausentes del proceso seleccionado dentro del módulo de paginación. La visualización utiliza relaciones persistidas y no asigna, carga, libera ni solicita páginas.

Se revisaron el snapshot de PagingService, relación MemoryFrame.page, páginas y procesos, paneles existentes y controles de paginación. Se conserva la tabla detallada de la Fase 12 y los permisos de lectura del simulador.

## Implementación

- PagingService agrega una colección de marcos al snapshot transaccional, ordenada por frame_number y con page.process cargados anticipadamente. Valida geometría, rango de marcos y límite de 1.024 antes de obtener los datos.
- Componente Blade paging.memory-grid: cada bloque identifica número de marco y estado. Libre significa que no existe una página asignada; ocupado muestra nombre del proceso y número de página.
- Gris para libre, azul para ocupado y borde morado para páginas pertenecientes al proceso seleccionado. La leyenda y los textos hacen que el estado no dependa solo del color.
- El mapa representa todos los marcos y procesos del escenario, incluso cuando todavía no se ha elegido un proceso. Seleccionar un proceso resalta sus asignaciones sin ocultar las demás.
- Distribución responsive Bootstrap de dos columnas en móvil, cuatro desde sm y seis desde xl. Los nombres largos permiten salto de línea.
- El panel conserva RAM total, utilizada, disponible y marcos totales, ocupados y libres. Todos esos indicadores corresponden al escenario completo.
- Paginación de 64 bloques mediante framesPage para limitar el contenido visible. La tabla de páginas mantiene pagesPage con 15 filas y secundaria usa secondaryPage con 15 páginas ausentes del proceso.
- Secundaria muestra badges con nombre del proceso y número de página; conserva las capacidades globales del escenario. Las páginas con marco se excluyen de esa lista.
- Las tres paginaciones usan las colecciones del mismo snapshot y normalizan los valores manipulados o fuera de rango. Cambiar escenario reinicia las tres; cambiar proceso reinicia la tabla y secundaria.
- Los componentes Blade reciben los datos preparados por el padre. No se agregan consultas SQL, lógica de asignación o estado persistido independiente en las vistas.
- Los tres roles consultan el mapa con memory.view, tables.view y simulations.view. La actualización visible cada cinco segundos mantiene la tabla, mapa y capacidades coherentes con las relaciones existentes.

## Verificación

- MemoryGridTest: **seis pruebas aprobadas, 61 assertions**. Comprueba bloques libres y ocupados, procesos y páginas, resaltado del proceso elegido, aislamiento y paginación del mapa y secundaria.
- Regresión conjunta de PagingSimulatorTest y PageTableTest: **45 pruebas aprobadas, 202 assertions**.
- Compilación Blade mediante artisan view:cache: correcta.
- Chrome verificó el mapa, leyenda, páginas residentes, secundaria y cambio de procesos/escenarios con Administrador, Operador y Observador.
- Anchos 1366, 768, 390 y 320 px verificados; distribución responsive y nombres largos sin desbordamiento de la página.
- La firma de las siete tablas del dominio permaneció durante la consulta, cambios de selección, paginación y polling. No se produjeron escrituras de páginas, marcos, procesos o eventos por visualizar el mapa.
- Sin errores JavaScript ni recursos necesarios fallidos en la revisión de navegador. Se conservaron capturas de escritorio y móvil.
- No se agregaron migraciones ni paquetes. Las asignaciones utilizadas para verificar la lectura pertenecían a fixtures temporales del navegador.

## Capturas

- [Mapa de RAM en escritorio](fase-13-paginacion-escritorio.png).
- [Mapa de RAM en móvil](fase-13-paginacion-movil.png).

## Uso y continuación

Abre http://localhost/sistemg3/public/paginacion y selecciona un escenario configurado. RAM muestra el conjunto de marcos, libres u ocupados según las asignaciones existentes. Selecciona un proceso para destacar sus páginas residentes y consultar las que permanecen en secundaria.

Los procesos de la Fase 10 nacen con todas sus páginas en DISCO. Mientras no exista una asignación persistida, el mapa conserva sus marcos libres; consultar la visualización no carga páginas automáticamente.

Fase 13 completada. Continúa la Fase 14, solicitud de CPU, conforme a la autorización general del usuario.

Commit sugerido: `feat: visualize simulated RAM frames`.
