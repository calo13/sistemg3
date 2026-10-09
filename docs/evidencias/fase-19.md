# Fase 19 — Accesos y fallos de segmentación

Fecha de cierre: **8 de octubre de 2026**.

## Autorización y alcance

Continúa la autorización del usuario para completar las fases en orden sin nuevas confirmaciones. La Fase 19 incorpora accesos CPU por segmento y offset, valida el límite antes de sumar la base y muestra el resultado válido o SEGMENTATION FAULT.

Reutiliza el snapshot, bloqueo y reglas de segmentación de la Fase 18. La consulta de tabla, mapa y dashboard se conserva para los tres roles.

## Comportamiento implementado

- Administrador y Operador seleccionan un segmento activo e introducen un offset entero en bytes. Observador consulta tabla y mapa.
- El servicio recarga al actor, comprueba cinco permisos de lectura/escritura y toma el mismo bloqueo de escenario usado por la asignación.
- Se validan escenario listo/en ejecución, pertenencia del proceso, proceso no finalizado y segmento ACTIVE. Segmentos liberados, inexistentes o ajenos no producen eventos de acceso.
- Número de segmento y offset admiten enteros de cero a 4294967295. Negativos y valores fuera de ese rango se rechazan antes de registrar eventos.
- Si offset < size_bytes, dirección física = base + offset. Se registra exactamente un SEGMENT_ACCESS y escenario/proceso pasan a RUNNING dentro de la transacción.
- Si offset ≥ size_bytes, se registra exactamente un SEGMENTATION_FAULT, con dirección física null y estados anteriores conservados. El último offset válido es size_bytes − 1.
- Metadata registra contexto canónico, segmento, nombre, base, tamaño, offset, último offset válido y resultado para interpretar el historial.
- Ambos resultados conservan segmentos, límites y RAM ocupada. El acceso no reasigna memoria ni termina procesos automáticamente.
- SegmentAccess protege su resultado mediante Locked y recibe contexto Reactive. La clave del padre recrea un único componente al cambiar escenario o proceso. memory-updated refresca la lectura tras guardar el acceso.
- Se reutilizan tablas y tipos de evento existentes; no hay una migración nueva.

## Verificación

- SegmentationAccessTest: **32 pruebas aprobadas** después de agregar la regresión de selección con S0 liberado y S1 activo. La verificación inicial aprobó 31 pruebas y 170 assertions.
- Regresión SegmentationSimulatorTest: **30 pruebas aprobadas, 154 assertions**.
- Verificación inicial de acceso y simulador: **61 pruebas aprobadas, 324 assertions**. El caso adicional se verificó con la Fase 20: comparación y acceso aprobaron 69 pruebas y 1572 assertions en conjunto.
- Pint y caché de vistas Blade: **aprobados**.
- Chrome verificó un acceso de Operador con base **1000** y offset **100**, que produjo dirección física **1100**.
- Offset **1200**, igual al límite de **1200 bytes**, produjo SEGMENTATION FAULT sin dirección física. Se registraron exactamente dos eventos entre ambos accesos: uno válido y un fallo.
- RAM utilizada permaneció en **2600 bytes** después de los accesos.
- Las consultas de los tres roles conservaron la firma de las siete tablas del dominio. Dashboard mantuvo cuatro indicadores «No aplica» para segmentación.
- Se comprobaron anchos de 1366, 768, 390 y 320 píxeles, sin errores JavaScript. Los fixtures temporales se limpiaron.
- El selector se inicializa con el primer segmento ACTIVE cuando existe, o null con una opción vacía cuando no existe. Un S0 liberado ya no deja seleccionada una referencia que no aparece entre las opciones activas.

## Capturas

- [Acceso válido](fase-19-acceso-valido.png).
- [Fallo de segmentación en escritorio](fase-19-segmentation-fault-escritorio.png).
- [Fallo de segmentación en móvil](fase-19-segmentation-fault-movil.png).

## Uso y continuación

En Segmentación selecciona escenario y proceso. En Direccionamiento por segmentación elige un segmento activo, introduce un offset y pulsa Acceder. Compara primero el offset con el tamaño: para un segmento de 1200 bytes son válidos 0 a 1199; 1200 ya queda fuera. La pantalla explica la suma de base y offset o el motivo del fallo.

Fase 19 completada con pruebas y navegador verificados. Continúa la Fase 20, comparación de técnicas, según la autorización general.

Commit sugerido: `feat: validate segmented addresses and record segmentation faults`.
