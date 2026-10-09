# Fase 23 — Historial de simulación

Fecha de cierre: **8 de octubre de 2026**.

## Autorización y alcance

Continúa la autorización del usuario para completar las fases en orden sin nuevas confirmaciones. Esta fase permite consultar los eventos persistidos de memoria mediante filtros y paginación. La consulta conserva las asignaciones y la historia anterior.

## Comportamiento implementado

- SimulationHistoryService recarga al usuario y autoriza history.view; el catálogo reserva esta consulta a Administrador.
- La ruta /historial, el componente Livewire y el menú aplican el permiso. Operador y Observador reciben 403.
- Filtros por escenario, proceso, usuario, tipo de evento y fecha inicial/final. El proceso debe corresponder al escenario elegido.
- Se rechazan IDs inexistentes, campos desconocidos, tipos inválidos y filtros presentes compuestos solo por espacios.
- Fechas estrictas YYYY-MM-DD entre 1000-01-01 y 9999-12-30, con inicio no posterior al fin.
- Los días se interpretan en Guatemala y se convierten a UTC: inicio inclusivo, comienzo del día siguiente al fin exclusivo. La presentación vuelve a Guatemala.
- Orden por occurred_at e ID descendentes, con 20 filas por página. Cuenta y filas pertenecen a la misma transacción de lectura.
- Cambiar filtros vuelve a la primera página; una página fuera de rango se normaliza. Limpiar filtros conserva el historial y recupera la consulta completa.
- Las relaciones se cargan en el servicio; Blade presenta sus datos sin consultas adicionales.
- No hay migración, modificación de eventos o cambio de selección de escenario del simulador.

## Revisión y correcciones

La revisión de lectura detectó el borde del último día del año 9999: calcular su día siguiente produciría una fecha fuera del rango de MySQL. La validación limita ambos extremos a 9999-12-30 y presenta mensajes en español. También se rechazaron explícitamente los filtros de espacios antes del validador nullable para evitar interpretarlos como ID cero.

## Verificación

- **36 pruebas aprobadas y 213 assertions**, en 7,55 segundos.
- Chrome comprobó un fixture de **21 eventos**, con **20 filas** en la primera página.
- El filtro Fault mostró **cinco eventos** y el filtro de Chrome **dos**.
- Operador y Observador recibieron 403. La lectura y los filtros conservaron el hash de las siete tablas del dominio.
- Se comprobaron 1366, 768, 390 y 320 píxeles sin errores JavaScript, de recursos o desbordamiento.
- Los datos temporales de la comprobación se limpiaron al finalizar.

## Capturas

- [Historial en escritorio](fase-23-historial-escritorio.png).
- [Historial en móvil](fase-23-historial-movil.png).

## Continuación

Fase 23 completada con pruebas y navegador verificados. Continúa la Fase 24, terminal educativa, según la autorización general.

Commit sugerido: `feat: add authorized simulation history filters`.
